<?php

namespace App\Features\Authentication\Http\Controllers;

use App\Features\Authentication\Actions\DeleteAccount;
use App\Features\Authentication\Actions\FirebaseLogin;
use App\Features\Authentication\Actions\ForgotPassword;
use App\Features\Authentication\Actions\GuestLogin;
use App\Features\Authentication\Actions\LinkFirebase;
use App\Features\Authentication\Actions\Login;
use App\Features\Authentication\Actions\Logout;
use App\Features\Authentication\Actions\RefreshToken;
use App\Features\Authentication\Actions\Register;
use App\Features\Authentication\Actions\ResetPassword;
use App\Features\Authentication\Http\Requests\DeleteAccountRequest;
use App\Features\Authentication\Http\Requests\FirebaseLoginRequest;
use App\Features\Authentication\Http\Requests\ForgotPasswordRequest;
use App\Features\Authentication\Http\Requests\GuestRequest;
use App\Features\Authentication\Http\Requests\LinkFirebaseRequest;
use App\Features\Authentication\Http\Requests\LoginRequest;
use App\Features\Authentication\Http\Requests\RefreshTokenRequest;
use App\Features\Authentication\Http\Requests\RegisterRequest;
use App\Features\Authentication\Http\Requests\ResetPasswordRequest;
use App\Features\Authentication\Http\Resources\TokenPairResource;
use App\Features\Profiles\Http\Resources\ProfileResource;
use App\Features\Users\Http\Resources\UserResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class AuthenticationController extends Controller
{
    public function __construct(
        private readonly Register $register,
        private readonly Login $login,
        private readonly FirebaseLogin $firebaseLogin,
        private readonly GuestLogin $guestLogin,
        private readonly RefreshToken $refreshToken,
        private readonly Logout $logout,
        private readonly ForgotPassword $forgotPassword,
        private readonly ResetPassword $resetPassword,
        private readonly LinkFirebase $linkFirebase,
        private readonly DeleteAccount $deleteAccount,
    ) {}

    public function register(RegisterRequest $request): JsonResponse
    {
        return (new TokenPairResource($this->register->execute($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        return (new TokenPairResource($this->login->execute($request->validated())))->response();
    }

    public function firebaseLogin(FirebaseLoginRequest $request): JsonResponse
    {
        return (new TokenPairResource(
            $this->firebaseLogin->execute($request->validated(), $request->ip()),
        ))->response();
    }

    public function guest(GuestRequest $request): JsonResponse
    {
        return (new TokenPairResource($this->guestLogin->execute($request->validated('device'))))
            ->response()
            ->setStatusCode(201);
    }

    public function refresh(RefreshTokenRequest $request): JsonResponse
    {
        return (new TokenPairResource($this->refreshToken->execute($request->validated('refresh_token'))))->response();
    }

    public function logout(Request $request): JsonResponse
    {
        $this->logout->execute($request->user());

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $user->profile()->with('country', 'user.socialStatus')->firstOrFail();

        return response()->json([
            'user' => (new UserResource($user))->resolve($request),
            'profile' => (new ProfileResource($profile))->resolve($request),
        ]);
    }

    public function forgotPassword(ForgotPasswordRequest $request): JsonResponse
    {
        $this->forgotPassword->execute($request->validated('email'));

        return response()->json(['message' => 'If the account exists, a reset link has been sent.']);
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        return response()->json(['message' => $this->resetPassword->execute($request->validated())]);
    }

    public function linkFirebase(LinkFirebaseRequest $request): JsonResponse
    {
        return response()->json([
            'user' => (new UserResource(
                $this->linkFirebase->execute($request->user(), $request->validated('id_token')),
            ))->resolve($request),
        ]);
    }

    public function deleteAccount(DeleteAccountRequest $request): JsonResponse
    {
        $this->deleteAccount->execute($request->user(), $request->validated('password'));

        return response()->json(status: 204);
    }
}
