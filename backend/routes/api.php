<?php

use App\Features\Agency\Http\Controllers\AgencyController;
use App\Features\Authentication\Http\Controllers\AuthenticationController;
use App\Features\Authentication\Http\Controllers\EmailVerificationController;
use App\Features\Blocking\Http\Controllers\BlockController;
use App\Features\Countries\Http\Controllers\CountryController;
use App\Features\Devices\Http\Controllers\DeviceController;
use App\Features\Followers\Http\Controllers\FollowerController;
use App\Features\Gifts\Http\Controllers\GiftController;
use App\Features\LiveChat\Http\Controllers\LiveChatController;
use App\Features\LiveStreaming\Http\Controllers\LiveCategoryController;
use App\Features\LiveStreaming\Http\Controllers\LiveRoomController;
use App\Features\PkBattle\Http\Controllers\PkBattleController;
use App\Features\Notifications\Http\Controllers\NotificationController;
use App\Features\Profiles\Http\Controllers\ProfileController;
use App\Features\Ranking\Http\Controllers\RankingController;
use App\Features\Reports\Http\Controllers\ReportController;
use App\Features\Settings\Http\Controllers\SettingController;
use App\Features\Users\Http\Controllers\CurrentUserController;
use App\Features\UserSearch\Http\Controllers\UserSearchController;
use App\Features\UserStatus\Http\Controllers\UserStatusController;
use App\Features\Vip\Http\Controllers\VipController;
use App\Features\VoiceRoom\Http\Controllers\VoiceRoomController;
use App\Features\Wallet\Http\Controllers\WalletController;
use App\Features\Wallet\Http\Controllers\WithdrawRequestController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::prefix('auth')->name('auth.')->group(function (): void {
        Route::post('register', [AuthenticationController::class, 'register'])->middleware('throttle:5,1')->name('register');
        Route::post('login', [AuthenticationController::class, 'login'])->middleware('throttle:10,1')->name('login');
        Route::post('firebase/login', [AuthenticationController::class, 'firebaseLogin'])
            ->middleware('throttle:10,1')
            ->name('firebase.login');
        Route::post('guest', [AuthenticationController::class, 'guest'])->middleware('throttle:10,1')->name('guest');
        Route::post('refresh', [AuthenticationController::class, 'refresh'])->middleware('throttle:20,1')->name('refresh');
        Route::post('forgot-password', [AuthenticationController::class, 'forgotPassword'])->middleware('throttle:5,1')->name('forgot');
        Route::post('reset-password', [AuthenticationController::class, 'resetPassword'])->middleware('throttle:5,1')->name('reset');
        Route::get('verify-email/{user}/{hash}', [EmailVerificationController::class, 'verify'])
            ->middleware('signed')
            ->name('verification.verify');
    });

    Route::get('countries', [CountryController::class, 'index']);
    Route::get('countries/{country}', [CountryController::class, 'show']);
    Route::get('settings', [SettingController::class, 'publicIndex']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('auth/logout', [AuthenticationController::class, 'logout']);
        Route::get('auth/me', [AuthenticationController::class, 'me'])->name('auth.me');
        Route::post('auth/firebase/link', [AuthenticationController::class, 'linkFirebase'])
            ->middleware('throttle:5,1')
            ->name('auth.firebase');
        Route::delete('auth/account', [AuthenticationController::class, 'deleteAccount'])->name('auth.delete');
        Route::post('auth/email/verification-notification', [EmailVerificationController::class, 'notice'])
            ->middleware('throttle:6,1');

        Route::get('user', [CurrentUserController::class, 'show']);
        Route::patch('user', [CurrentUserController::class, 'update']);
        Route::get('profile', [ProfileController::class, 'show']);
        Route::patch('profile', [ProfileController::class, 'update']);
        Route::get('users/search', UserSearchController::class)->middleware('throttle:60,1');
        Route::get('users/{user}/profile', [ProfileController::class, 'publicShow']);
        Route::get('users/{user}/gift-history', [GiftController::class, 'history'])
            ->middleware('throttle:60,1')
            ->name('gifts.history');
        Route::get('gifts', [GiftController::class, 'index'])->name('gifts.index');

        Route::post('users/{user}/follow', [FollowerController::class, 'store']);
        Route::delete('users/{user}/follow', [FollowerController::class, 'destroy']);
        Route::get('users/{user}/followers', [FollowerController::class, 'followers']);
        Route::get('users/{user}/following', [FollowerController::class, 'following']);
        Route::get('follower-requests', [FollowerController::class, 'requests']);
        Route::post('follower-requests/{follow}/accept', [FollowerController::class, 'accept']);
        Route::delete('follower-requests/{follow}', [FollowerController::class, 'reject']);

        Route::get('blocks', [BlockController::class, 'index']);
        Route::post('users/{user}/block', [BlockController::class, 'store']);
        Route::delete('users/{user}/block', [BlockController::class, 'destroy']);

        Route::get('report-categories', [ReportController::class, 'categories']);
        Route::post('users/{user}/reports', [ReportController::class, 'store'])->middleware('throttle:10,1');
        Route::get('reports/history', [ReportController::class, 'history']);
        Route::get('admin/reports', [ReportController::class, 'adminIndex']);
        Route::patch('admin/reports/{report}', [ReportController::class, 'review']);
        Route::get('admin/withdraw-requests', [WithdrawRequestController::class, 'index']);
        Route::patch('admin/withdraw-requests/{withdrawRequest}', [WithdrawRequestController::class, 'review']);

        Route::get('wallet', [WalletController::class, 'show'])->name('wallet.show');
        Route::get('wallet/transactions', [WalletController::class, 'transactions'])
            ->middleware('throttle:60,1')
            ->name('wallet.transactions');
        Route::post('wallet/recharge', [WalletController::class, 'recharge'])
            ->middleware('throttle:10,1')
            ->name('wallet.recharge');
        Route::post('wallet/withdraw', [WalletController::class, 'withdraw'])
            ->middleware('throttle:5,1')
            ->name('wallet.withdraw');

        Route::get('user-status', [UserStatusController::class, 'show']);
        Route::patch('user-status', [UserStatusController::class, 'update']);

        Route::get('devices', [DeviceController::class, 'index']);
        Route::delete('devices/{device}', [DeviceController::class, 'destroy']);
        Route::get('user-settings', [SettingController::class, 'userIndex']);
        Route::put('user-settings', [SettingController::class, 'userUpdate']);
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::patch('notifications/read-all', [NotificationController::class, 'readAll']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::delete('notifications/{notification}', [NotificationController::class, 'destroy']);

        Route::prefix('live')->name('live.')->middleware('throttle:60,1')->group(function (): void {
            Route::post('create', [LiveRoomController::class, 'store'])->middleware('throttle:10,1')->name('create');
            Route::get('categories', [LiveCategoryController::class, 'index'])->name('categories');
            Route::get('/', [LiveRoomController::class, 'index'])->name('index');
            Route::post('{room}/start', [LiveRoomController::class, 'start'])->middleware('throttle:10,1')->name('start');
            Route::post('{room}/end', [LiveRoomController::class, 'end'])->middleware('throttle:10,1')->name('end');
            Route::get('{room}', [LiveRoomController::class, 'show'])->name('show');
            Route::post('{room}/join', [LiveRoomController::class, 'join'])->middleware('throttle:30,1')->name('join');
            Route::post('{room}/leave', [LiveRoomController::class, 'leave'])->middleware('throttle:30,1')->name('leave');
            Route::get('{room}/messages', [LiveChatController::class, 'index'])->name('messages.index');
            Route::post('{room}/messages', [LiveChatController::class, 'store'])->middleware('throttle:20,1')->name('messages.store');
            Route::delete('{room}/messages/{message}', [LiveChatController::class, 'destroy'])
                ->middleware('throttle:30,1')
                ->name('messages.destroy');
            Route::post('{room}/gifts/send', [GiftController::class, 'send'])
                ->middleware('throttle:20,1')
                ->name('gifts.send');
            Route::post('{room}/pk/request', [PkBattleController::class, 'request'])
                ->middleware('throttle:10,1')
                ->name('pk.request');
            Route::post('{room}/pk/accept', [PkBattleController::class, 'accept'])
                ->middleware('throttle:10,1')
                ->name('pk.accept');
        });

        Route::prefix('pk')->name('pk.')->middleware('throttle:60,1')->group(function (): void {
            Route::post('{battle}/start', [PkBattleController::class, 'start'])
                ->middleware('throttle:10,1')
                ->name('start');
            Route::post('{battle}/end', [PkBattleController::class, 'end'])
                ->middleware('throttle:10,1')
                ->name('end');
            Route::get('{battle}', [PkBattleController::class, 'show'])->name('show');
        });

        Route::prefix('voice')->name('voice.')->middleware('throttle:60,1')->group(function (): void {
            Route::post('create', [VoiceRoomController::class, 'store'])->middleware('throttle:10,1')->name('create');
            Route::post('{room}/join', [VoiceRoomController::class, 'join'])->middleware('throttle:30,1')->name('join');
            Route::post('{room}/leave', [VoiceRoomController::class, 'leave'])->middleware('throttle:30,1')->name('leave');
            Route::post('{room}/seat/request', [VoiceRoomController::class, 'requestSeat'])
                ->middleware('throttle:20,1')
                ->name('seat.request');
            Route::post('{room}/seat/approve', [VoiceRoomController::class, 'approveSeat'])
                ->middleware('throttle:20,1')
                ->name('seat.approve');
            Route::post('{room}/seat/reject', [VoiceRoomController::class, 'rejectSeat'])
                ->middleware('throttle:20,1')
                ->name('seat.reject');
            Route::post('{room}/seat/remove', [VoiceRoomController::class, 'removeSpeaker'])
                ->middleware('throttle:20,1')
                ->name('seat.remove');
            Route::post('{room}/seat/mute', [VoiceRoomController::class, 'muteSpeaker'])
                ->middleware('throttle:20,1')
                ->name('seat.mute');
            Route::post('{room}/end', [VoiceRoomController::class, 'end'])->middleware('throttle:10,1')->name('end');
        });

        Route::prefix('rankings')->name('rankings.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('hosts', [RankingController::class, 'hosts'])->name('hosts');
            Route::get('gifters', [RankingController::class, 'gifters'])->name('gifters');
            Route::get('pk', [RankingController::class, 'pk'])->name('pk');
            Route::get('voice', [RankingController::class, 'voice'])->name('voice');
        });

        Route::prefix('vip')->name('vip.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('levels', [VipController::class, 'levels'])->name('levels');
            Route::get('me', [VipController::class, 'me'])->name('me');
            Route::post('purchase', [VipController::class, 'purchase'])
                ->middleware('throttle:10,1')
                ->name('purchase');
            Route::post('upgrade', [VipController::class, 'upgrade'])
                ->middleware('throttle:10,1')
                ->name('upgrade');
        });

        Route::prefix('agencies')->name('agencies.')->middleware('throttle:60,1')->group(function (): void {
            Route::post('create', [AgencyController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('create');
            Route::get('{agency}', [AgencyController::class, 'show'])->name('show');
            Route::post('{agency}/apply', [AgencyController::class, 'apply'])
                ->middleware('throttle:10,1')
                ->name('apply');
            Route::post('{agency}/approve', [AgencyController::class, 'approve'])
                ->middleware('throttle:20,1')
                ->name('approve');
            Route::post('{agency}/reject', [AgencyController::class, 'reject'])
                ->middleware('throttle:20,1')
                ->name('reject');
            Route::delete('{agency}/members/{user}', [AgencyController::class, 'removeMember'])
                ->middleware('throttle:20,1')
                ->name('members.remove');
            Route::get('{agency}/hosts', [AgencyController::class, 'hosts'])->name('hosts');
            Route::get('{agency}/earnings', [AgencyController::class, 'earnings'])->name('earnings');
        });
    });
});
