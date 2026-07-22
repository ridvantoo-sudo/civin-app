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

        if (! is_string($credentials) || $credentials === '') {
            throw new RuntimeException('Firebase credentials are not configured.');
        }

        try {
            $token = (new Factory)
                ->withServiceAccount($credentials)
                ->createAuth()
                ->verifyIdToken($idToken);
            $claims = $token->claims();
            $uid = $claims->get('sub');
            $expiresAt = $claims->get('exp');
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
}
