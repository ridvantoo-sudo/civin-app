<?php

namespace App\Providers;

use App\Features\Admin\Support\AdminRole;
use App\Support\CacheStoreFailover;
use App\Features\Ads\Models\AdCampaign;
use App\Features\Ads\Policies\AdCampaignPolicy;
use App\Features\Ads\Repositories\Contracts\AdRepository;
use App\Features\Ads\Repositories\Eloquent\EloquentAdRepository;
use App\Features\Agency\Listeners\CreateAgencyCommissionFromGift;
use App\Features\Agency\Models\Agency;
use App\Features\Agency\Policies\AgencyPolicy;
use App\Features\Agency\Repositories\Contracts\AgencyRepository;
use App\Features\Agency\Repositories\Eloquent\EloquentAgencyRepository;
use App\Features\Authentication\Events\UserLoggedIn;
use App\Features\Authentication\Events\UserRegistered;
use App\Features\Authentication\Listeners\SendEmailVerification;
use App\Features\Authentication\Repositories\Contracts\RefreshTokenRepository;
use App\Features\Authentication\Repositories\Eloquent\EloquentRefreshTokenRepository;
use App\Features\Authentication\Services\FirebaseTokenVerifier;
use App\Features\Authentication\Services\KreaitFirebaseTokenVerifier;
use App\Features\Blocking\Repositories\Contracts\BlockRepository;
use App\Features\Blocking\Repositories\Eloquent\EloquentBlockRepository;
use App\Features\Challenges\Listeners\TrackChallengeFromGift;
use App\Features\Challenges\Listeners\TrackChallengeFromLive;
use App\Features\Challenges\Listeners\TrackChallengeFromLogin;
use App\Features\Challenges\Listeners\TrackChallengeFromPk;
use App\Features\Challenges\Listeners\TrackChallengeFromWatch;
use App\Features\CoinSeller\Models\CoinSeller;
use App\Features\CoinSeller\Policies\CoinSellerPolicy;
use App\Features\CoinSeller\Repositories\Contracts\CoinSellerRepository;
use App\Features\CoinSeller\Repositories\Eloquent\EloquentCoinSellerRepository;
use App\Features\Countries\Repositories\Contracts\CountryRepository;
use App\Features\Countries\Repositories\Eloquent\EloquentCountryRepository;
use App\Features\Couple\Models\Couple;
use App\Features\Couple\Models\CoupleProposal;
use App\Features\Couple\Policies\CouplePolicy;
use App\Features\Couple\Repositories\Contracts\CoupleRepository;
use App\Features\Couple\Repositories\Eloquent\EloquentCoupleRepository;
use App\Features\Devices\Models\Device;
use App\Features\Devices\Policies\DevicePolicy;
use App\Features\Devices\Repositories\Contracts\DeviceRepository;
use App\Features\Devices\Repositories\Eloquent\EloquentDeviceRepository;
use App\Features\Discovery\Listeners\BroadcastOnlineUserUpdated;
use App\Features\Discovery\Repositories\Contracts\DiscoveryRepository;
use App\Features\Discovery\Repositories\Eloquent\EloquentDiscoveryRepository;
use App\Features\FamilyClub\Models\Family;
use App\Features\FamilyClub\Policies\FamilyPolicy;
use App\Features\FamilyClub\Repositories\Contracts\FamilyRepository;
use App\Features\FamilyClub\Repositories\Eloquent\EloquentFamilyRepository;
use App\Features\Followers\Models\Follow;
use App\Features\Followers\Policies\FollowPolicy;
use App\Features\Followers\Repositories\Contracts\FollowRepository;
use App\Features\Followers\Repositories\Eloquent\EloquentFollowRepository;
use App\Features\Games\Core\GamePluginBootstrap;
use App\Features\Games\Core\GameRegistry;
use App\Features\Games\Models\RedEnvelope;
use App\Features\Games\Models\TreasureBox;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceJackpotWon;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceLost;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDicePlayed;
use App\Features\Games\Plugins\LuckyDice\Events\LuckyDiceWon;
use App\Features\Games\Plugins\LuckyDice\Listeners\RecordLuckyDiceAnalytics;
use App\Features\Games\Plugins\LuckyDice\Listeners\RecordLuckyDiceHistory;
use App\Features\Games\Plugins\LuckyDice\Listeners\RecordLuckyDiceRewardAnalytics;
use App\Features\Games\Plugins\LuckyDice\Listeners\UpdateLuckyDiceLeaderboard;
use App\Features\Games\Plugins\LuckyDice\Models\LuckyDiceBet;
use App\Features\Games\Plugins\LuckyDice\Policies\LuckyDicePolicy;
use App\Features\Games\Plugins\LuckyDice\Repositories\Contracts\LuckyDiceRepository;
use App\Features\Games\Plugins\LuckyDice\Repositories\Eloquent\EloquentLuckyDiceRepository;
use App\Features\Games\Plugins\LuckyNumber\Console\ProcessLuckyNumberDrawsCommand;
use App\Features\Games\Plugins\LuckyNumber\Console\ProcessLuckyNumberJackpotsCommand;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberDrawFinished;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberJackpotWon;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberTicketPurchased;
use App\Features\Games\Plugins\LuckyNumber\Events\LuckyNumberWon;
use App\Features\Games\Plugins\LuckyNumber\Listeners\RecordLuckyNumberAnalytics;
use App\Features\Games\Plugins\LuckyNumber\Listeners\RecordLuckyNumberHistory;
use App\Features\Games\Plugins\LuckyNumber\Listeners\UpdateLuckyNumberLeaderboard;
use App\Features\Games\Plugins\LuckyNumber\Listeners\UpdateLuckyNumberStatistics;
use App\Features\Games\Plugins\LuckyNumber\Models\LuckyNumberTicket;
use App\Features\Games\Plugins\LuckyNumber\Policies\LuckyNumberPolicy;
use App\Features\Games\Plugins\LuckyNumber\Repositories\Contracts\LuckyNumberRepository;
use App\Features\Games\Plugins\LuckyNumber\Repositories\Eloquent\EloquentLuckyNumberRepository;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelJackpotWon;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelPlayed;
use App\Features\Games\Plugins\LuckyWheel\Events\LuckyWheelRewardWon;
use App\Features\Games\Plugins\LuckyWheel\Listeners\RecordLuckyWheelAnalytics;
use App\Features\Games\Plugins\LuckyWheel\Listeners\RecordLuckyWheelHistory;
use App\Features\Games\Plugins\LuckyWheel\Listeners\RecordLuckyWheelRewardAnalytics;
use App\Features\Games\Plugins\LuckyWheel\Listeners\UpdateLuckyWheelLeaderboard;
use App\Features\Games\Plugins\LuckyWheel\Models\LuckyWheelSpin;
use App\Features\Games\Plugins\LuckyWheel\Policies\LuckyWheelPolicy;
use App\Features\Games\Plugins\LuckyWheel\Repositories\Contracts\LuckyWheelRepository;
use App\Features\Games\Plugins\LuckyWheel\Repositories\Eloquent\EloquentLuckyWheelRepository;
use App\Features\Games\Plugins\Ludo\Console\ProcessLudoMatchesCommand;
use App\Features\Games\Plugins\Ludo\Console\ProcessLudoTournamentsCommand;
use App\Features\Games\Plugins\Ludo\Events\DiceRolled;
use App\Features\Games\Plugins\Ludo\Events\LeaderboardUpdated as LudoLeaderboardUpdated;
use App\Features\Games\Plugins\Ludo\Events\LudoMatchCancelled;
use App\Features\Games\Plugins\Ludo\Events\LudoMatchFinished;
use App\Features\Games\Plugins\Ludo\Events\LudoMatchStarted;
use App\Features\Games\Plugins\Ludo\Events\LudoPlayerJoined;
use App\Features\Games\Plugins\Ludo\Events\LudoPlayerReady;
use App\Features\Games\Plugins\Ludo\Events\LudoRewardGranted;
use App\Features\Games\Plugins\Ludo\Events\LudoRoomCreated;
use App\Features\Games\Plugins\Ludo\Events\PieceMoved;
use App\Features\Games\Plugins\Ludo\Events\PlayerCaptured;
use App\Features\Games\Plugins\Ludo\Events\PlayerDisconnected;
use App\Features\Games\Plugins\Ludo\Events\PlayerReconnected;
use App\Features\Games\Plugins\Ludo\Events\ReplayFinished;
use App\Features\Games\Plugins\Ludo\Events\ReplayStarted;
use App\Features\Games\Plugins\Ludo\Events\TournamentFinished as LudoTournamentFinished;
use App\Features\Games\Plugins\Ludo\Events\TournamentStarted as LudoTournamentStarted;
use App\Features\Games\Plugins\Ludo\Events\TurnChanged;
use App\Features\Games\Plugins\Ludo\Listeners\RecordLudoAnalytics;
use App\Features\Games\Plugins\Ludo\Listeners\RecordLudoHistory;
use App\Features\Games\Plugins\Ludo\Listeners\UpdateLudoLeaderboard;
use App\Features\Games\Plugins\Ludo\Listeners\UpdateLudoStatistics;
use App\Features\Games\Plugins\Ludo\Models\LudoMatch;
use App\Features\Games\Plugins\Ludo\Policies\LudoPolicy;
use App\Features\Games\Plugins\Ludo\Repositories\Contracts\LudoRepository;
use App\Features\Games\Plugins\Ludo\Repositories\Eloquent\EloquentLudoRepository;
use App\Features\Games\Plugins\Quiz\Console\ProcessQuizTournamentsCommand;
use App\Features\Games\Plugins\Quiz\Console\ProcessQuizzesCommand;
use App\Features\Games\Plugins\Quiz\Events\AnswerSubmitted;
use App\Features\Games\Plugins\Quiz\Events\QuizFinished;
use App\Features\Games\Plugins\Quiz\Events\QuizJoined;
use App\Features\Games\Plugins\Quiz\Events\QuizStarted;
use App\Features\Games\Plugins\Quiz\Events\RewardGranted;
use App\Features\Games\Plugins\Quiz\Events\TournamentFinished;
use App\Features\Games\Plugins\Quiz\Listeners\RecordQuizAnalytics;
use App\Features\Games\Plugins\Quiz\Listeners\RecordQuizHistory;
use App\Features\Games\Plugins\Quiz\Listeners\UpdateQuizLeaderboard;
use App\Features\Games\Plugins\Quiz\Listeners\UpdateQuizStatistics;
use App\Features\Games\Plugins\Quiz\Models\QuizSession;
use App\Features\Games\Plugins\Quiz\Policies\QuizPolicy;
use App\Features\Games\Plugins\Quiz\Repositories\Contracts\QuizQuestionRepository;
use App\Features\Games\Plugins\Quiz\Repositories\Contracts\QuizRepository;
use App\Features\Games\Plugins\Quiz\Repositories\Eloquent\EloquentQuizQuestionRepository;
use App\Features\Games\Plugins\Quiz\Repositories\Eloquent\EloquentQuizRepository;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeClaimed;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeCreated;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeExpired;
use App\Features\Games\Plugins\RedEnvelope\Events\EnvelopeFinished;
use App\Features\Games\Plugins\RedEnvelope\Listeners\RecordRedEnvelopeAnalytics;
use App\Features\Games\Plugins\RedEnvelope\Listeners\RecordRedEnvelopeHistory;
use App\Features\Games\Plugins\RedEnvelope\Listeners\UpdateRedEnvelopeLeaderboard;
use App\Features\Games\Plugins\RedEnvelope\Policies\RedEnvelopePolicy;
use App\Features\Games\Plugins\RedEnvelope\Repositories\Contracts\RedEnvelopeRepository;
use App\Features\Games\Plugins\RedEnvelope\Repositories\Eloquent\EloquentRedEnvelopeRepository;
use App\Features\Games\Plugins\RockPaperScissors\Console\ProcessRpsMatchesCommand;
use App\Features\Games\Plugins\RockPaperScissors\Console\ProcessRpsTournamentsCommand;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsChallengeAccepted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsChallengeCreated;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMatchFinished;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMatchStarted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsMoveSubmitted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRewardGranted;
use App\Features\Games\Plugins\RockPaperScissors\Events\RpsRoundFinished;
use App\Features\Games\Plugins\RockPaperScissors\Events\TournamentFinished as RpsTournamentFinished;
use App\Features\Games\Plugins\RockPaperScissors\Events\TournamentStarted as RpsTournamentStarted;
use App\Features\Games\Plugins\RockPaperScissors\Listeners\RecordRpsAnalytics;
use App\Features\Games\Plugins\RockPaperScissors\Listeners\RecordRpsHistory;
use App\Features\Games\Plugins\RockPaperScissors\Listeners\UpdateRpsLeaderboard;
use App\Features\Games\Plugins\RockPaperScissors\Listeners\UpdateRpsStatistics;
use App\Features\Games\Plugins\RockPaperScissors\Models\RpsMatch;
use App\Features\Games\Plugins\RockPaperScissors\Policies\RpsPolicy;
use App\Features\Games\Plugins\RockPaperScissors\Repositories\Contracts\RpsRepository;
use App\Features\Games\Plugins\RockPaperScissors\Repositories\Eloquent\EloquentRpsRepository;
use App\Features\Games\Plugins\SlotMachine\Events\BonusTriggered as SlotBonusTriggered;
use App\Features\Games\Plugins\SlotMachine\Events\JackpotWon as SlotJackpotWon;
use App\Features\Games\Plugins\SlotMachine\Events\SlotSpinFinished;
use App\Features\Games\Plugins\SlotMachine\Listeners\RecordSlotMachineAnalytics;
use App\Features\Games\Plugins\SlotMachine\Listeners\RecordSlotMachineHistory;
use App\Features\Games\Plugins\SlotMachine\Listeners\UpdateSlotMachineLeaderboard;
use App\Features\Games\Plugins\SlotMachine\Listeners\UpdateSlotMachineStatistics;
use App\Features\Games\Plugins\SlotMachine\Models\SlotMachineSpin;
use App\Features\Games\Plugins\SlotMachine\Policies\SlotMachinePolicy;
use App\Features\Games\Plugins\SlotMachine\Repositories\Contracts\SlotMachineRepository;
use App\Features\Games\Plugins\SlotMachine\Repositories\Eloquent\EloquentSlotMachineRepository;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureCreated;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureExpired;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureFinished;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureJoined;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureOpened;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureRewarded;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureScheduled;
use App\Features\Games\Plugins\TreasureBox\Events\TreasureWinnerSelected;
use App\Features\Games\Plugins\TreasureBox\Listeners\RecordTreasureBoxAnalytics;
use App\Features\Games\Plugins\TreasureBox\Listeners\RecordTreasureBoxHistory;
use App\Features\Games\Plugins\TreasureBox\Listeners\UpdateTreasureBoxLeaderboard;
use App\Features\Games\Plugins\TreasureBox\Listeners\UpdateTreasureBoxStatistics;
use App\Features\Games\Plugins\TreasureBox\Policies\TreasureBoxPolicy;
use App\Features\Games\Plugins\TreasureBox\Repositories\Contracts\TreasureBoxRepository;
use App\Features\Games\Plugins\TreasureBox\Repositories\Eloquent\EloquentTreasureBoxRepository;
use App\Features\Games\Repositories\Contracts\GameRepository;
use App\Features\Games\Repositories\Eloquent\EloquentGameRepository;
use App\Features\Games\Services\GameRng;
use App\Features\Games\Services\SecureGameRng;
use App\Features\GeoDiscovery\Repositories\Contracts\GeoDiscoveryRepository;
use App\Features\GeoDiscovery\Repositories\Eloquent\EloquentGeoDiscoveryRepository;
use App\Features\Gifts\Events\GiftSent;
use App\Features\Gifts\Models\GiftTransaction;
use App\Features\Gifts\Policies\GiftTransactionPolicy;
use App\Features\Gifts\Repositories\Contracts\GiftRepository;
use App\Features\Gifts\Repositories\Eloquent\EloquentGiftRepository;
use App\Features\Kyc\Models\KycSubmission;
use App\Features\Kyc\Policies\KycSubmissionPolicy;
use App\Features\Kyc\Repositories\Contracts\KycRepository;
use App\Features\Kyc\Repositories\Eloquent\EloquentKycRepository;
use App\Features\Levels\Listeners\AwardXpFromGift;
use App\Features\Levels\Listeners\AwardXpFromLiveEnded;
use App\Features\Levels\Listeners\AwardXpFromLiveViewerLeft;
use App\Features\Levels\Listeners\AwardXpFromLogin;
use App\Features\LiveChat\Models\LiveMessage;
use App\Features\LiveChat\Policies\LiveMessagePolicy;
use App\Features\LiveChat\Repositories\Contracts\LiveMessageRepository;
use App\Features\LiveChat\Repositories\Eloquent\EloquentLiveMessageRepository;
use App\Features\LiveShopping\Models\ShopOrder;
use App\Features\LiveShopping\Models\StoreProduct;
use App\Features\LiveShopping\Policies\ShopOrderPolicy;
use App\Features\LiveShopping\Policies\StoreProductPolicy;
use App\Features\LiveShopping\Repositories\Contracts\LiveShoppingRepository;
use App\Features\LiveShopping\Repositories\Eloquent\EloquentLiveShoppingRepository;
use App\Features\LiveStreaming\Events\LiveEnded;
use App\Features\LiveStreaming\Events\LiveViewerLeft;
use App\Features\LiveStreaming\Models\LiveRoom;
use App\Features\LiveStreaming\Policies\LiveRoomPolicy;
use App\Features\LiveStreaming\Repositories\Contracts\LiveRoomRepository;
use App\Features\LiveStreaming\Repositories\Eloquent\EloquentLiveRoomRepository;
use App\Features\Marketplace\Models\UserMarketItem;
use App\Features\Marketplace\Policies\UserMarketItemPolicy;
use App\Features\Marketplace\Repositories\Contracts\MarketplaceRepository;
use App\Features\Marketplace\Repositories\Eloquent\EloquentMarketplaceRepository;
use App\Features\MultiPkBattle\Listeners\UpdateMultiPkScoreFromGift;
use App\Features\MultiPkBattle\Models\MultiPkBattle;
use App\Features\MultiPkBattle\Policies\MultiPkBattlePolicy;
use App\Features\Notifications\Repositories\Contracts\NotificationRepository;
use App\Features\Notifications\Repositories\Eloquent\EloquentNotificationRepository;
use App\Features\PkBattle\Events\PkFinished;
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
use App\Features\Svip\Models\UserSvip;
use App\Features\Svip\Policies\UserSvipPolicy;
use App\Features\Svip\Repositories\Contracts\SvipRepository;
use App\Features\Svip\Repositories\Eloquent\EloquentSvipRepository;
use App\Features\Users\Models\User;
use App\Features\Users\Repositories\Contracts\UserRepository;
use App\Features\Users\Repositories\Eloquent\EloquentUserRepository;
use App\Features\UserSearch\Repositories\Contracts\UserSearchRepository;
use App\Features\UserSearch\Repositories\Eloquent\EloquentUserSearchRepository;
use App\Features\UserStatus\Events\UserStatusChanged;
use App\Features\UserStatus\Repositories\Contracts\UserStatusRepository;
use App\Features\UserStatus\Repositories\Eloquent\EloquentUserStatusRepository;
use App\Features\Vip\Models\UserVip;
use App\Features\Vip\Policies\UserVipPolicy;
use App\Features\Vip\Repositories\Contracts\VipRepository;
use App\Features\Vip\Repositories\Eloquent\EloquentVipRepository;
use App\Features\VoicePkBattle\Listeners\UpdateVoicePkScoreFromGift;
use App\Features\VoicePkBattle\Models\VoicePkBattle;
use App\Features\VoicePkBattle\Policies\VoicePkBattlePolicy;
use App\Features\VoiceRoom\Models\VoiceRoom;
use App\Features\VoiceRoom\Policies\VoiceRoomPolicy;
use App\Features\VoiceRoom\Repositories\Contracts\VoiceRoomRepository;
use App\Features\VoiceRoom\Repositories\Eloquent\EloquentVoiceRoomRepository;
use App\Features\Wallet\Models\OfflineRechargeRequest;
use App\Features\Wallet\Models\Wallet;
use App\Features\Wallet\Models\WithdrawRequest;
use App\Features\Wallet\Policies\OfflineRechargeRequestPolicy;
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
        $this->mergeConfigFrom(
            __DIR__.'/../../config/marketplace.php',
            'marketplace',
        );
        $this->mergeConfigFrom(
            __DIR__.'/../../config/live_shopping.php',
            'live_shopping',
        );

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
        $this->app->bind(DiscoveryRepository::class, EloquentDiscoveryRepository::class);
        $this->app->bind(GeoDiscoveryRepository::class, EloquentGeoDiscoveryRepository::class);
        $this->app->bind(LiveRoomRepository::class, EloquentLiveRoomRepository::class);
        $this->app->bind(LiveMessageRepository::class, EloquentLiveMessageRepository::class);
        $this->app->bind(GiftRepository::class, EloquentGiftRepository::class);
        $this->app->bind(WalletRepository::class, EloquentWalletRepository::class);
        $this->app->bind(PkBattleRepository::class, EloquentPkBattleRepository::class);
        $this->app->bind(VoiceRoomRepository::class, EloquentVoiceRoomRepository::class);
        $this->app->bind(RankingRepository::class, EloquentRankingRepository::class);
        $this->app->bind(VipRepository::class, EloquentVipRepository::class);
        $this->app->bind(SvipRepository::class, EloquentSvipRepository::class);
        $this->app->bind(AdRepository::class, EloquentAdRepository::class);
        $this->app->bind(MarketplaceRepository::class, EloquentMarketplaceRepository::class);
        $this->app->bind(GameRepository::class, EloquentGameRepository::class);
        $this->app->bind(LuckyWheelRepository::class, EloquentLuckyWheelRepository::class);
        $this->app->bind(SlotMachineRepository::class, EloquentSlotMachineRepository::class);
        $this->app->bind(LuckyNumberRepository::class, EloquentLuckyNumberRepository::class);
        $this->app->bind(LuckyDiceRepository::class, EloquentLuckyDiceRepository::class);
        $this->app->bind(RedEnvelopeRepository::class, EloquentRedEnvelopeRepository::class);
        $this->app->bind(TreasureBoxRepository::class, EloquentTreasureBoxRepository::class);
        $this->app->bind(QuizRepository::class, EloquentQuizRepository::class);
        $this->app->bind(QuizQuestionRepository::class, EloquentQuizQuestionRepository::class);
        $this->app->bind(RpsRepository::class, EloquentRpsRepository::class);
        $this->app->bind(LudoRepository::class, EloquentLudoRepository::class);
        $this->app->bind(GameRng::class, SecureGameRng::class);
        $this->app->singleton(GameRegistry::class, function ($app): GameRegistry {
            $registry = new GameRegistry;
            $app->make(GamePluginBootstrap::class)->register($registry);

            return $registry;
        });
        $this->app->bind(LiveShoppingRepository::class, EloquentLiveShoppingRepository::class);
        $this->app->bind(AgencyRepository::class, EloquentAgencyRepository::class);
        $this->app->bind(CoinSellerRepository::class, EloquentCoinSellerRepository::class);
        $this->app->bind(KycRepository::class, EloquentKycRepository::class);
        $this->app->bind(FamilyRepository::class, EloquentFamilyRepository::class);
        $this->app->bind(CoupleRepository::class, EloquentCoupleRepository::class);
        $this->app->bind(FirebaseTokenVerifier::class, KreaitFirebaseTokenVerifier::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Must run before Filament/Livewire touch the cache store. A dead Redis
        // (common on Laravel Cloud before a cache addon is attached) otherwise
        // surfaces as admin HTTP 500 / Livewire 419.
        CacheStoreFailover::ensureReachable();

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProcessLuckyNumberDrawsCommand::class,
                ProcessLuckyNumberJackpotsCommand::class,
                ProcessQuizTournamentsCommand::class,
                ProcessQuizzesCommand::class,
                ProcessRpsMatchesCommand::class,
                ProcessRpsTournamentsCommand::class,
                ProcessLudoMatchesCommand::class,
                ProcessLudoTournamentsCommand::class,
            ]);
        }

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
        Gate::policy(OfflineRechargeRequest::class, OfflineRechargeRequestPolicy::class);
        Gate::policy(PkBattle::class, PkBattlePolicy::class);
        Gate::policy(MultiPkBattle::class, MultiPkBattlePolicy::class);
        Gate::policy(VoicePkBattle::class, VoicePkBattlePolicy::class);
        Gate::policy(VoiceRoom::class, VoiceRoomPolicy::class);
        Gate::policy(Ranking::class, RankingPolicy::class);
        Gate::policy(UserVip::class, UserVipPolicy::class);
        Gate::policy(UserSvip::class, UserSvipPolicy::class);
        Gate::policy(AdCampaign::class, AdCampaignPolicy::class);
        Gate::policy(UserMarketItem::class, UserMarketItemPolicy::class);
        Gate::policy(StoreProduct::class, StoreProductPolicy::class);
        Gate::policy(ShopOrder::class, ShopOrderPolicy::class);
        Gate::policy(Agency::class, AgencyPolicy::class);
        Gate::policy(CoinSeller::class, CoinSellerPolicy::class);
        Gate::policy(KycSubmission::class, KycSubmissionPolicy::class);
        Gate::policy(Family::class, FamilyPolicy::class);
        Gate::policy(Couple::class, CouplePolicy::class);
        Gate::policy(CoupleProposal::class, CouplePolicy::class);
        Gate::policy(LuckyWheelSpin::class, LuckyWheelPolicy::class);
        Gate::policy(SlotMachineSpin::class, SlotMachinePolicy::class);
        Gate::policy(LuckyNumberTicket::class, LuckyNumberPolicy::class);
        Gate::policy(LuckyDiceBet::class, LuckyDicePolicy::class);
        Gate::policy(RedEnvelope::class, RedEnvelopePolicy::class);
        Gate::policy(TreasureBox::class, TreasureBoxPolicy::class);
        Gate::policy(QuizSession::class, QuizPolicy::class);
        Gate::policy(RpsMatch::class, RpsPolicy::class);
        Gate::policy(LudoMatch::class, LudoPolicy::class);
        Gate::before(function (?User $user, string $ability) {
            if ($user?->hasRole(AdminRole::SUPER_ADMIN)) {
                return true;
            }

            return null;
        });
        Gate::define('viewGiftHistory', fn (User $actor, User $subject): bool => $actor->getKey() === $subject->getKey());
        Event::listen(UserRegistered::class, SendEmailVerification::class);
        Event::listen(UserStatusChanged::class, BroadcastOnlineUserUpdated::class);
        Event::listen(GiftSent::class, UpdatePkScoreFromGift::class);
        Event::listen(GiftSent::class, UpdateMultiPkScoreFromGift::class);
        Event::listen(GiftSent::class, UpdateVoicePkScoreFromGift::class);
        Event::listen(GiftSent::class, CreateAgencyCommissionFromGift::class);
        Event::listen(GiftSent::class, AwardXpFromGift::class);
        Event::listen(GiftSent::class, TrackChallengeFromGift::class);
        Event::listen(LiveEnded::class, AwardXpFromLiveEnded::class);
        Event::listen(LiveEnded::class, TrackChallengeFromLive::class);
        Event::listen(LiveViewerLeft::class, AwardXpFromLiveViewerLeft::class);
        Event::listen(LiveViewerLeft::class, TrackChallengeFromWatch::class);
        Event::listen(UserLoggedIn::class, AwardXpFromLogin::class);
        Event::listen(UserLoggedIn::class, TrackChallengeFromLogin::class);
        Event::listen(PkFinished::class, TrackChallengeFromPk::class);
        Event::listen(LuckyWheelPlayed::class, RecordLuckyWheelAnalytics::class);
        Event::listen(LuckyWheelPlayed::class, RecordLuckyWheelHistory::class);
        Event::listen(LuckyWheelPlayed::class, UpdateLuckyWheelLeaderboard::class);
        Event::listen(LuckyWheelRewardWon::class, RecordLuckyWheelRewardAnalytics::class);
        Event::listen(LuckyWheelJackpotWon::class, RecordLuckyWheelRewardAnalytics::class);
        Event::listen(SlotSpinFinished::class, RecordSlotMachineAnalytics::class);
        Event::listen(SlotSpinFinished::class, RecordSlotMachineHistory::class);
        Event::listen(SlotSpinFinished::class, UpdateSlotMachineLeaderboard::class);
        Event::listen(SlotSpinFinished::class, UpdateSlotMachineStatistics::class);
        Event::listen(SlotBonusTriggered::class, RecordSlotMachineAnalytics::class);
        Event::listen(SlotJackpotWon::class, RecordSlotMachineAnalytics::class);
        Event::listen(LuckyNumberDrawFinished::class, RecordLuckyNumberAnalytics::class);
        Event::listen(LuckyNumberDrawFinished::class, RecordLuckyNumberHistory::class);
        Event::listen(LuckyNumberDrawFinished::class, UpdateLuckyNumberLeaderboard::class);
        Event::listen(LuckyNumberDrawFinished::class, UpdateLuckyNumberStatistics::class);
        Event::listen(LuckyNumberWon::class, RecordLuckyNumberAnalytics::class);
        Event::listen(LuckyNumberJackpotWon::class, RecordLuckyNumberAnalytics::class);
        Event::listen(LuckyNumberTicketPurchased::class, RecordLuckyNumberAnalytics::class);
        Event::listen(LuckyDicePlayed::class, RecordLuckyDiceAnalytics::class);
        Event::listen(LuckyDiceWon::class, RecordLuckyDiceAnalytics::class);
        Event::listen(LuckyDiceLost::class, RecordLuckyDiceAnalytics::class);
        Event::listen(LuckyDicePlayed::class, RecordLuckyDiceHistory::class);
        Event::listen(LuckyDicePlayed::class, UpdateLuckyDiceLeaderboard::class);
        Event::listen(LuckyDiceJackpotWon::class, RecordLuckyDiceRewardAnalytics::class);
        Event::listen(EnvelopeCreated::class, RecordRedEnvelopeAnalytics::class);
        Event::listen(EnvelopeClaimed::class, RecordRedEnvelopeAnalytics::class);
        Event::listen(EnvelopeExpired::class, RecordRedEnvelopeAnalytics::class);
        Event::listen(EnvelopeFinished::class, RecordRedEnvelopeAnalytics::class);
        Event::listen(EnvelopeCreated::class, RecordRedEnvelopeHistory::class);
        Event::listen(EnvelopeClaimed::class, RecordRedEnvelopeHistory::class);
        Event::listen(EnvelopeCreated::class, UpdateRedEnvelopeLeaderboard::class);
        Event::listen(EnvelopeClaimed::class, UpdateRedEnvelopeLeaderboard::class);
        Event::listen(TreasureCreated::class, RecordTreasureBoxAnalytics::class);
        Event::listen(TreasureJoined::class, RecordTreasureBoxAnalytics::class);
        Event::listen(TreasureOpened::class, RecordTreasureBoxAnalytics::class);
        Event::listen(TreasureRewarded::class, RecordTreasureBoxAnalytics::class);
        Event::listen(TreasureExpired::class, RecordTreasureBoxAnalytics::class);
        Event::listen(TreasureFinished::class, RecordTreasureBoxAnalytics::class);
        Event::listen(TreasureCreated::class, RecordTreasureBoxHistory::class);
        Event::listen(TreasureScheduled::class, RecordTreasureBoxHistory::class);
        Event::listen(TreasureJoined::class, RecordTreasureBoxHistory::class);
        Event::listen(TreasureOpened::class, RecordTreasureBoxHistory::class);
        Event::listen(TreasureWinnerSelected::class, RecordTreasureBoxHistory::class);
        Event::listen(TreasureRewarded::class, RecordTreasureBoxHistory::class);
        Event::listen(TreasureExpired::class, RecordTreasureBoxHistory::class);
        Event::listen(TreasureFinished::class, RecordTreasureBoxHistory::class);
        Event::listen(TreasureCreated::class, UpdateTreasureBoxLeaderboard::class);
        Event::listen(TreasureJoined::class, UpdateTreasureBoxLeaderboard::class);
        Event::listen(TreasureRewarded::class, UpdateTreasureBoxLeaderboard::class);
        Event::listen(TreasureCreated::class, UpdateTreasureBoxStatistics::class);
        Event::listen(TreasureJoined::class, UpdateTreasureBoxStatistics::class);
        Event::listen(TreasureRewarded::class, UpdateTreasureBoxStatistics::class);
        Event::listen(QuizJoined::class, RecordQuizAnalytics::class);
        Event::listen(QuizStarted::class, RecordQuizAnalytics::class);
        Event::listen(AnswerSubmitted::class, RecordQuizAnalytics::class);
        Event::listen(QuizFinished::class, RecordQuizAnalytics::class);
        Event::listen(RewardGranted::class, RecordQuizAnalytics::class);
        Event::listen(TournamentFinished::class, RecordQuizAnalytics::class);
        Event::listen(QuizStarted::class, RecordQuizHistory::class);
        Event::listen(QuizJoined::class, RecordQuizHistory::class);
        Event::listen(QuizFinished::class, RecordQuizHistory::class);
        Event::listen(QuizFinished::class, UpdateQuizLeaderboard::class);
        Event::listen(RewardGranted::class, UpdateQuizLeaderboard::class);
        Event::listen(AnswerSubmitted::class, UpdateQuizStatistics::class);
        Event::listen(QuizFinished::class, UpdateQuizStatistics::class);
        Event::listen(RewardGranted::class, UpdateQuizStatistics::class);
        Event::listen(RpsChallengeCreated::class, RecordRpsAnalytics::class);
        Event::listen(RpsChallengeAccepted::class, RecordRpsAnalytics::class);
        Event::listen(RpsMatchStarted::class, RecordRpsAnalytics::class);
        Event::listen(RpsMoveSubmitted::class, RecordRpsAnalytics::class);
        Event::listen(RpsRoundFinished::class, RecordRpsAnalytics::class);
        Event::listen(RpsMatchFinished::class, RecordRpsAnalytics::class);
        Event::listen(RpsRewardGranted::class, RecordRpsAnalytics::class);
        Event::listen(RpsTournamentStarted::class, RecordRpsAnalytics::class);
        Event::listen(RpsTournamentFinished::class, RecordRpsAnalytics::class);
        Event::listen(RpsChallengeAccepted::class, RecordRpsHistory::class);
        Event::listen(RpsMatchStarted::class, RecordRpsHistory::class);
        Event::listen(RpsRoundFinished::class, RecordRpsHistory::class);
        Event::listen(RpsMatchFinished::class, RecordRpsHistory::class);
        Event::listen(RpsRoundFinished::class, UpdateRpsStatistics::class);
        Event::listen(RpsMatchFinished::class, UpdateRpsStatistics::class);
        Event::listen(RpsRewardGranted::class, UpdateRpsStatistics::class);
        Event::listen(RpsMatchFinished::class, UpdateRpsLeaderboard::class);
        Event::listen(RpsRewardGranted::class, UpdateRpsLeaderboard::class);
        foreach ([
            LudoRoomCreated::class, LudoPlayerJoined::class, LudoPlayerReady::class,
            LudoMatchStarted::class, DiceRolled::class, PieceMoved::class,
            TurnChanged::class, PlayerCaptured::class, PlayerDisconnected::class,
            PlayerReconnected::class, ReplayStarted::class, ReplayFinished::class,
            LudoTournamentStarted::class, LudoTournamentFinished::class,
            LudoLeaderboardUpdated::class, LudoRewardGranted::class,
            LudoMatchFinished::class, LudoMatchCancelled::class,
        ] as $ludoEvent) {
            Event::listen($ludoEvent, RecordLudoAnalytics::class);
        }
        Event::listen(LudoMatchFinished::class, RecordLudoHistory::class);
        Event::listen(LudoMatchFinished::class, UpdateLudoStatistics::class);
        Event::listen(LudoMatchFinished::class, UpdateLudoLeaderboard::class);
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
