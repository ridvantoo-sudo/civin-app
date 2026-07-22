<?php

namespace App\Features\Authentication\Services;

use App\Features\Authentication\DTOs\FirebaseIdentity;

interface FirebaseTokenVerifier
{
    public function verify(string $idToken): FirebaseIdentity;
}
