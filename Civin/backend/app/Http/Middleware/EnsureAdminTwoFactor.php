<?php

namespace App\Http\Middleware;

use App\Features\Users\Models\User;
use App\Filament\Pages\TwoFactorChallenge;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminTwoFactor
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->hasTwoFactorEnabled()) {
            return $next($request);
        }

        if ($request->session()->get('admin_2fa_passed') === $user->getKey()) {
            return $next($request);
        }

        if ($request->routeIs('filament.admin.pages.two-factor-challenge')) {
            return $next($request);
        }

        return redirect()->to(TwoFactorChallenge::getUrl());
    }
}
