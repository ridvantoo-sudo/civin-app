<?php

namespace App\Providers;

use App\Features\Admin\Support\AdminRole;
use App\Features\Agency\Listeners\CreateAgencyCommissionFromGift;
use App\Features\Agency\Models\Agency;
use App\Features\Agency\Policies\AgencyPolicy;
use App\Features\Agency\Repositories\Contracts\AgencyRepository;
use App\Features\Agency\Repositories\Eloquent\EloquentAgencyRepository;
use App\Features\Authentication\Events\UserRegistered;
use App\Features\Authentication\Listeners\SendEmailVerification;
use App\Features\Authentication\Repositories\Contracts\RefreshTokenRepository;
use App\Features\Authentication\Repositories\Eloquent\EloquentRefreshTokenRepository;
use App\Features\Authentication\Services\FirebaseTokenVerifier;
use App\Features\Authentication\Services\KreaitFirebaseTokenVerifier;
use App\Features\Blocking\Repositories\Contracts\BlockRepository;
use App\Features\Blocking\Repositories\Eloquent\EloquentBlockRepository;
use App\Features\Countries\Repositories\Contracts\CountryRepository;
use App\Features\Countries\Repositories\Eloquent\EloquentCountryRepository;
use App\Features\Devices\Models\Device;
use App\Features\Devices\Policies\DevicePolicy;
use App\Features\Devices\Repositories\Contracts\DeviceRepository;
use App\Features\Devices\Repositories\Eloquent\EloquentDeviceRepository;
use App\Features\Followers\Models\Follow;
use App\Features\Followers\Policies\FollowPolicy;
use App\Features\Followers\Repositories\Contracts\FollowRepository;
use App\Features\Followers\Repositories\Eloquent\EloquentFollowRepository;
use App\Features\Gifts\Events\GiftSent;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Gifts\Policies\GiftTransactionPolicy;
use App\Features\Gifts\Repositories\Contracts\GiftRepository;
use App\Features\Gifts\Repositories\Eloquent\EloquentGiftRepository;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveChat\Policies\LiveMessagePolicy;
use App\Features\LiveChat\Repositories\Contracts\LiveMessageRepository;
use App\Features\LiveChat\Repositories\Eloquent\EloquentLiveMessageRepository;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Policies\LiveRoomPolicy;
use App\Features\LiveStreaming\Repositories\Contracts\LiveRoomRepository;
use App\Features\LiveStreaming\Repositories\Eloquent\EloquentLiveRoomRepository;
use App\Features\Notifications\Repositories\Contracts\NotificationRepository;
use App\Features\Notifications\Repositories\Eloquent\EloquentNotificationRepository;
use App\Features\PkBattle\Listeners\UpdatePkScoreFromGift;
use App\Features\PkBattle\Models\PkBattle;
use App\Features\PkBattle\Policies\PkBattlePolicy;
use App\Features\PkBattle\Repositories\Contracts\PkBattleRepository;
use App\Features\PkBattle\Repositories\Eloquent\EloquentPkBattleRepository;
use App\Features\Profiles\Repositories\Contracts\ProfileRepository;
use App\Features\Profiles\Repositories\Eloquent\EloquentProfileRepository;
use App\Features\Ranking\Models\Ranking;
use App\Features\Ranking\Policies\RankingPolicy;
use App\Features\Ranking\Repositories\Contracts\RankingRepository;
use App\Features\Ranking\Repositories\Eloquent\EloquentRankingRepository;
use App\Features\Reports\Models\Report;
use App\Features\Reports\Policies\ReportPolicy;
use App\Features\Reports\Repositories\Contracts\ReportRepository;
use App\Features\Reports\Repositories\Eloquent\EloquentReportRepository;
use App\Features\Settings\Repositories\Contracts\SettingRepository;
use App\Features\Settings\Repositories\Eloquent\EloquentSettingRepository;
use App\Features\Users\Models\User;
use App\Features\Users\Repositories\Contracts\UserRepository;
use App\Features\Users\Repositories\Eloquent\EloquentUserRepository;
use App\Features\UserSearch\Repositories\Contracts\UserSearchRepository;
use App\Features\UserSearch\Repositories\Eloquent\EloquentUserSearchRepository;
use App\Features\UserStatus\Repositories\Contracts\UserStatusRepository;
use App\Features\UserStatus\Repositories\Eloquent\EloquentUserStatusRepository;
use App\Features\Vip\Models\UserVip;
use App\Features\Vip\Policies\UserVipPolicy;
use App\Features\Vip\Repositories\Contracts\VipRepository;
use App\Features\Vip\Repositories\Eloquent\EloquentVipRepository;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Policies\VoiceRoomPolicy;
use App\Features\VoiceRoom\Repositories\Contracts\VoiceRoomRepository;
use App\Features\VoiceRoom\Repositories\Eloquent\EloquentVoiceRoomRepository;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WithdrawRequest;
use App\Features\Wallet\Policies\WalletPolicy;
use App\Features\Wallet\Policies\WithdrawRequestPolicy;
use App\Features\Wallet\Repositories\Contracts\WalletRepository;
use App\Features\Wallet\Repositories\Eloquent\EloquentWalletRepository;
use App\Support\Models\PersonalAccessToken;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(ProfileRepository::class, EloquentProfileRepository::class);
        $this->app->bind(DeviceRepository::class, EloquentDeviceRepository::class);
        $this->app->bind(CountryRepository::class, EloquentCountryRepository::class);
        $this->app->bind(SettingRepository::class, EloquentSettingRepository::class);
        $this->app->bind(NotificationRepository::class, EloquentNotificationRepository::class);
        $this->app->bind(RefreshTokenRepository::class, EloquentRefreshTokenRepository::class);
        $this->app->bind(FollowRepository::class, EloquentFollowRepository::class);
        $this->app->bind(BlockRepository::class, EloquentBlockRepository::class);
        $this->app->bind(ReportRepository::class, EloquentReportRepository::class);
        $this->app->bind(UserSearchRepository::class, EloquentUserSearchRepository::class);
        $this->app->bind(UserStatusRepository::class, EloquentUserStatusRepository::class);
        $this->app->bind(LiveRoomRepository::class, EloquentLiveRoomRepository::class);
        $this->app->bind(LiveMessageRepository::class, EloquentLiveMessageRepository::class);
        $this->app->bind(GiftRepository::class, EloquentGiftRepository::class);
        $this->app->bind(WalletRepository::class, EloquentWalletRepository::class);
        $this->app->bind(PkBattleRepository::class, EloquentPkBattleRepository::class);
        $this->app->bind(VoiceRoomRepository::class, EloquentVoiceRoomRepository::class);
        $this->app->bind(RankingRepository::class, EloquentRankingRepository::class);
        $this->app->bind(VipRepository::class, EloquentVipRepository::class);
        $this->app->bind(AgencyRepository::class, EloquentAgencyRepository::class);
        $this->app->bind(FirebaseTokenVerifier::class, KreaitFirebaseTokenVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Sanctum::usePersonalAccessTokenModel(PersonalAccessToken::class);
        Factory::guessFactoryNamesUsing(
            fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory',
        );
        Gate::policy(Device::class, DevicePolicy::class);
        Gate::policy(Follow::class, FollowPolicy::class);
        Gate::policy(Report::class, ReportPolicy::class);
        Gate::policy(LiveRoom::class, LiveRoomPolicy::class);
        Gate::policy(LiveMessage::class, LiveMessagePolicy::class);
        Gate::policy(GiftTransaction::class, GiftTransactionPolicy::class);
        Gate::policy(Wallet::class, WalletPolicy::class);
        Gate::policy(WithdrawRequest::class, WithdrawRequestPolicy::class);
        Gate::policy(PkBattle::class, PkBattlePolicy::class);
        Gate::policy(VoiceRoom::class, VoiceRoomPolicy::class);
        Gate::policy(Ranking::class, RankingPolicy::class);
        Gate::policy(UserVip::class, UserVipPolicy::class);
        Gate::policy(Agency::class, AgencyPolicy::class);
        Gate::before(function (?User $user, string $ability) {
            if ($user?->hasRole(AdminRole::SUPER_ADMIN)) {
                return true;
            }

            return null;
        });
        Gate::define('viewGiftHistory', fn (User $actor, User $subject): bool => $actor->getKey() === $subject->getKey());
        Event::listen(UserRegistered::class, SendEmailVerification::class);
        Event::listen(GiftSent::class, UpdatePkScoreFromGift::class);
        Event::listen(GiftSent::class, CreateAgencyCommissionFromGift::class);
        VerifyEmail::createUrlUsing(fn ($notifiable): string => URL::temporarySignedRoute(
            'api.v1.auth.verification.verify',
            now()->addMinutes(60),
            ['user' => $notifiable->getKey(), 'hash' => sha1($notifiable->getEmailForVerification())],
        ));
        ResetPassword::createUrlUsing(fn ($notifiable, string $token): string => rtrim(
            (string) config('app.frontend_url'),
            '/',
        ).'/reset-password?token='.$token.'&email='.urlencode($notifiable->getEmailForPasswordReset()));
    }
}
