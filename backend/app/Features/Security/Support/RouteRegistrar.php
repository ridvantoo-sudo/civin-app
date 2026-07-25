<?php

namespace App\Features\Security\Support;

use App\Features\Security\Controllers\SecurityController;
use App\Features\Users\Models\User;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;

final class RouteRegistrar
{
    public static function register(): void
    {
        Route::middleware(['api', 'auth:sanctum'])
            ->prefix('api/v1/security')
            ->name('api.v1.security.')
            ->group(function (): void {
                Route::get('me', [SecurityController::class, 'me'])->name('me');
                Route::get('risk', [SecurityController::class, 'risk'])->name('risk');
                Route::get('devices', [SecurityController::class, 'devices'])->name('devices');
                Route::get('history', [SecurityController::class, 'history'])->name('history');
                Route::get('rules', [SecurityController::class, 'rules'])->name('rules');

                Route::post('report', [SecurityController::class, 'report'])->name('report');
                Route::post('device/verify', [SecurityController::class, 'verifyDevice'])->name('device.verify');
                Route::post('session/verify', [SecurityController::class, 'verifySession'])->name('session.verify');
            });
    }

    public static function registerChannels(): void
    {
        // Platform-wide threat feed: staff only.
        Broadcast::channel(
            (string) config('security_platform.realtime.channel', 'security.monitor'),
            static fn (User $user): bool => SecurityPermission::allows($user, SecurityPermission::VIEW_SECURITY),
        );

        // Per-account feed: a user only ever hears about their own account.
        Broadcast::channel(
            'user.security.{userId}',
            static fn (User $user, string $userId): bool => (string) $user->id === $userId,
        );
    }
}
