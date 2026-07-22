<?php

namespace App\Features\Authentication\Services;

use App\Features\Authentication\DTOs\DeviceData;
use App\Features\Authentication\DTOs\FirebaseIdentity;
use App\Features\Authentication\DTOs\LoginData;
use App\Features\Authentication\DTOs\RegisterData;
use App\Features\Authentication\DTOs\TokenPair;
use App\Features\Authentication\Events\AccountDeleted;
use App\Features\Authentication\Events\FirebaseLinked;
use App\Features\Authentication\Events\UserRegistered;
use App\Features\Authentication\Repositories\Contracts\RefreshTokenRepository;
use App\Features\Devices\Actions\UpsertDevice;
use App\Features\Devices\Models\Device;
use App\Features\Devices\Repositories\Contracts\DeviceRepository;
use App\Features\Profiles\Repositories\Contracts\ProfileRepository;
use App\Features\Users\Models\User;
use App\Features\Users\Repositories\Contracts\UserRepository;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AuthenticationService
{
    public function __construct(
        private UserRepository $users,
        private FirebaseTokenVerifier $firebase,
        private UpsertDevice $upsertDevice,
        private ProfileRepository $profiles,
        private WalletRepository $wallets,
        private DeviceRepository $devices,
        private RefreshTokenRepository $refreshTokens,
    ) {}

    public function register(RegisterData $data): TokenPair
    {
        return DB::transaction(function () use ($data): TokenPair {
            $user = $this->users->create([
                'email' => $data->email,
                'username' => $data->username,
                'password' => $data->password,
                'is_guest' => false,
            ]);
            $this->profiles->createForUser($user, $data->displayName);
            $this->wallets->createForUser($user);
            DB::afterCommit(fn () => event(new UserRegistered($user)));

            return $this->issue($user, $data->device);
        });
    }

    public function login(LoginData $data): TokenPair
    {
        $user = $this->users->findByLogin($data->login);

        if (! $user || ! $user->password || ! Hash::check($data->password, $user->password) || $user->status !== 'active') {
            throw ValidationException::withMessages(['login' => ['The credentials are invalid.']]);
        }

        $user = $this->users->update($user, ['last_login_at' => now()]);

        return $this->issue($user, $data->device);
    }

    public function guest(DeviceData $device): TokenPair
    {
        return DB::transaction(function () use ($device): TokenPair {
            $user = $this->users->create([
                'username' => 'guest_'.Str::lower(Str::random(18)),
                'is_guest' => true,
            ]);
            $this->profiles->createForUser($user, 'Guest');
            $this->wallets->createForUser($user);

            return $this->issue($user, $device);
        });
    }

    public function firebaseLogin(string $idToken, DeviceData $device): TokenPair
    {
        try {
            $identity = $this->firebase->verify($idToken);
        } catch (InvalidFirebaseToken) {
            Log::warning('Firebase authentication rejected.', [
                'ip_address' => $device->ipAddress,
            ]);

            throw ValidationException::withMessages(['id_token' => ['The Firebase ID token is invalid or expired.']]);
        }

        try {
            $tokens = DB::transaction(function () use ($identity, $device): TokenPair {
                $user = $this->users->findByFirebaseUid($identity->uid, true);

                if (! $user) {
                    $email = $identity->email && ! $this->users->emailExists($identity->email)
                        ? $identity->email
                        : null;
                    $user = $this->users->create([
                        'firebase_uid' => $identity->uid,
                        'email' => $email,
                        'username' => $this->firebaseUsername($identity),
                        'email_verified_at' => $email && $identity->emailVerified ? now() : null,
                        'last_login_at' => now(),
                        'is_guest' => false,
                    ]);
                    $this->profiles->createForUser(
                        $user,
                        $identity->name ?? ($email ? Str::before($email, '@') : 'Firebase User'),
                        $identity->avatar,
                    );
                    $this->wallets->createForUser($user);
                } elseif ($user->status !== 'active') {
                    throw ValidationException::withMessages(['id_token' => ['The account is not active.']]);
                } else {
                    $user = $this->users->update($user, ['last_login_at' => now()]);
                }

                $user->tokens()->delete();
                $this->refreshTokens->revokeForUser($user->id);

                return $this->issue($user, $device);
            });
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages([
                    'id_token' => ['This Firebase identity could not be linked. Please retry.'],
                ]);
            }

            throw $exception;
        }

        Log::info('Firebase authentication succeeded.', [
            'user_id' => $tokens->user->id,
            'firebase_uid' => $identity->uid,
            'device_id' => $tokens->device->id,
            'ip_address' => $device->ipAddress,
        ]);

        return $tokens;
    }

    public function refresh(string $plainToken): TokenPair
    {
        $result = DB::transaction(function () use ($plainToken): ?TokenPair {
            $token = $this->refreshTokens->findByPlainTokenForUpdate($plainToken);

            if (! $token || $token->expires_at->isPast()) {
                throw ValidationException::withMessages(['refresh_token' => ['The refresh token is invalid.']]);
            }

            if ($token->revoked_at) {
                $this->refreshTokens->revokeFamily($token->family_id);

                return null;
            }

            $this->refreshTokens->revoke($token);
            $user = $this->users->find($token->user_id);
            $device = $this->devices->find($token->device_id);

            if (! $user || ! $device || $user->status !== 'active') {
                throw ValidationException::withMessages(['refresh_token' => ['The refresh token is invalid.']]);
            }

            return $this->issue($user, DeviceData::fromArray($device->toArray()), $device, $token->family_id);
        });

        if (! $result) {
            throw ValidationException::withMessages(['refresh_token' => ['Refresh token replay detected.']]);
        }

        return $result;
    }

    public function logout(User $user): void
    {
        $accessToken = $user->currentAccessToken();
        $deviceId = collect($accessToken?->abilities ?? [])
            ->first(fn (string $ability) => str_starts_with($ability, 'device:'));
        $deviceId = $deviceId ? Str::after($deviceId, 'device:') : null;

        if ($deviceId) {
            $this->refreshTokens->revokeForDevice($user->id, $deviceId);
        }

        $accessToken?->delete();
        Log::info('Authentication session logged out.', [
            'user_id' => $user->id,
            'device_id' => $deviceId,
        ]);
    }

    public function linkFirebase(User $user, string $idToken): User
    {
        try {
            $uid = $this->firebase->verify($idToken)->uid;
        } catch (InvalidFirebaseToken) {
            throw ValidationException::withMessages(['id_token' => ['The Firebase ID token is invalid.']]);
        }

        try {
            $user = DB::transaction(function () use ($user, $uid): User {
                $lockedUser = $this->users->findForUpdate($user->id);

                if (! $lockedUser || $this->users->firebaseUidBelongsToAnother($uid, $user->id)) {
                    throw ValidationException::withMessages(['id_token' => ['This Firebase identity is already linked.']]);
                }

                return $this->users->update($lockedUser, ['firebase_uid' => $uid]);
            });
        } catch (QueryException $exception) {
            if (in_array($exception->getCode(), ['23000', '23505'], true)) {
                throw ValidationException::withMessages(['id_token' => ['This Firebase identity is already linked.']]);
            }

            throw $exception;
        }

        event(new FirebaseLinked($user));

        return $user;
    }

    private function firebaseUsername(FirebaseIdentity $identity): string
    {
        $source = $identity->name ?? ($identity->email ? Str::before($identity->email, '@') : 'firebase_user');
        $base = Str::lower(Str::slug($source, '_'));
        $base = Str::limit($base !== '' ? $base : 'firebase_user', 24, '');

        do {
            $username = $base.'_'.Str::lower(Str::random(8));
        } while ($this->users->usernameExists($username));

        return $username;
    }

    public function delete(User $user, ?string $password): void
    {
        if (! $user->is_guest && (! $password || ! Hash::check($password, (string) $user->password))) {
            throw ValidationException::withMessages(['password' => ['The password is incorrect.']]);
        }

        DB::transaction(function () use ($user): void {
            $user->tokens()->delete();
            $this->refreshTokens->revokeForUser($user->id);
            $user->forceFill([
                'email' => null,
                'firebase_uid' => null,
                'status' => 'deleted',
                'password' => null,
            ])->save();
            $user->delete();
            event(new AccountDeleted($user->id));
        });
    }

    private function issue(User $user, DeviceData $deviceData, ?Device $device = null, ?string $familyId = null): TokenPair
    {
        $device ??= $this->upsertDevice->execute($user, $deviceData);
        $accessExpiresAt = now()->addMinutes((int) config('sanctum.expiration', 60));
        $access = $user->createToken(
            $device->name.' ('.$device->device_uuid.')',
            ['device:'.$device->id],
            $accessExpiresAt,
        );
        $plainRefresh = rtrim(strtr(base64_encode(random_bytes(72)), '+/', '-_'), '=');
        $refreshExpiresAt = now()->addDays((int) config('auth.refresh_expiration_days', 30));
        $this->refreshTokens->create([
            'family_id' => $familyId ?? (string) Str::uuid(),
            'user_id' => $user->id,
            'device_id' => $device->id,
            'token_hash' => hash('sha256', $plainRefresh),
            'expires_at' => $refreshExpiresAt,
        ]);

        return new TokenPair($user, $device, $access->plainTextToken, $plainRefresh, $accessExpiresAt, $refreshExpiresAt);
    }
}
