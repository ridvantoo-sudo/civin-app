<?php

namespace App\Http\Middleware;

use App\Features\Users\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->isBanned()) {
            return $next($request);
        }

        if ($request->is('api/v1/auth/logout') || $request->is('api/v1/auth/account')) {
            return $next($request);
        }

        return response()->json([
            'message' => 'This account is not allowed to use the API.',
        ], 403);
    }
}
