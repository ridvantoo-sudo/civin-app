<?php

namespace App\Features\Authentication\Services;

use App\Features\Authentication\DTOs\FirebaseIdentity;
use DateTimeImmutable;
use DateTimeInterface;
use Kreait\Firebase\Factory;
use RuntimeException;
use Throwable;

final class KreaitFirebaseTokenVerifier implements FirebaseTokenVerifier
{
    public function verify(string $idToken): FirebaseIdentity
    {
        $credentials = config('services.firebase.credentials');

        if (! is_string($credentials) || trim($credentials) === '') {
            throw new RuntimeException('Firebase credentials are not configured.');
        }

        try {
            $token = (new Factory)
                ->withServiceAccount($this->resolveServiceAccount($credentials))
                ->createAuth()
                ->verifyIdToken($idToken);
            $claims = $token->claims();
            $uid = $claims->get('sub');
            $expiresAt = $claims->get('exp');
        } catch (InvalidFirebaseToken $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new InvalidFirebaseToken('The Firebase ID token is invalid.', previous: $exception);
        }

        if (! is_string($uid) || $uid === '') {
            throw new InvalidFirebaseToken('The Firebase ID token has no subject.');
        }

        if (is_int($expiresAt)) {
            $expiresAt = (new DateTimeImmutable)->setTimestamp($expiresAt);
        }

        if (! $expiresAt instanceof DateTimeInterface || $expiresAt->getTimestamp() <= time()) {
            throw new InvalidFirebaseToken('The Firebase ID token has expired.');
        }

        return new FirebaseIdentity(
            uid: $uid,
            email: $this->nullableString($claims->get('email')),
            name: $this->nullableString($claims->get('name')),
            avatar: $this->nullableString($claims->get('picture')),
            emailVerified: $claims->get('email_verified') === true,
            expiresAt: $expiresAt,
        );
    }

    private function nullableString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Accept the service account as a file path (traditional servers), a raw
     * JSON string, or base64-encoded JSON. Platforms like Laravel Cloud have no
     * persistent filesystem for a key file, so the credentials are typically
     * pasted directly into a single environment variable.
     *
     * @return string|array<string, mixed>
     */
    private function resolveServiceAccount(string $credentials): string|array
    {
        $value = trim($credentials);

        // 1. Absolute/relative path to a service-account JSON file.
        if (is_file($value)) {
            return $value;
        }

        // 2. Raw JSON string.
        $decoded = json_decode($value, true);
        if (is_array($decoded) && isset($decoded['private_key'], $decoded['client_email'])) {
            return $decoded;
        }

        // 3. Base64-encoded JSON string.
        $binary = base64_decode($value, true);
        if ($binary !== false) {
            $decoded = json_decode($binary, true);
            if (is_array($decoded) && isset($decoded['private_key'], $decoded['client_email'])) {
                return $decoded;
            }
        }

        throw new RuntimeException(
            'FIREBASE_CREDENTIALS must be a service-account JSON file path, raw JSON, or base64 JSON.',
        );
    }
}
