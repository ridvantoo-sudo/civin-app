<?php

namespace App\Features\Authentication\Http\Controllers;

use App\Features\Authentication\Actions\VerifyEmail;
use App\Features\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class EmailVerificationController extends Controller
{
    public function __construct(private readonly VerifyEmail $verifyEmail) {}

    public function notice(Request $request): JsonResponse
    {
        $this->verifyEmail->sendNotice($request->user());

        return response()->json(['message' => 'Verification notice sent.']);
    }

    public function verify(Request $request, User $user, string $hash): JsonResponse
    {
        $this->verifyEmail->execute($user, $hash);

        return response()->json(['message' => 'Email verified.']);
    }
}
