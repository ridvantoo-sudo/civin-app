<?php

use App\Features\Ads\Http\Controllers\AdController;
use App\Features\Agency\Http\Controllers\AgencyController;
use App\Features\Ai\Http\Controllers\AiController;
use App\Features\Authentication\Http\Controllers\AuthenticationController;
use App\Features\Authentication\Http\Controllers\EmailVerificationController;
use App\Features\Blocking\Http\Controllers\BlockController;
use App\Features\Challenges\Http\Controllers\ChallengeController;
use App\Features\CoinSeller\Http\Controllers\AdminCoinSellerController;
use App\Features\CoinSeller\Http\Controllers\CoinSellerController;
use App\Features\Countries\Http\Controllers\CountryController;
use App\Features\Couple\Http\Controllers\CoupleController;
use App\Features\Devices\Http\Controllers\DeviceController;
use App\Features\Discovery\Http\Controllers\DiscoveryController;
use App\Features\FamilyClub\Http\Controllers\FamilyController;
use App\Features\Followers\Http\Controllers\FollowerController;
use App\Features\Games\Http\Controllers\GameController;
use App\Features\Games\Plugins\LuckyDice\Http\Controllers\LuckyDiceController;
use App\Features\Games\Plugins\LuckyNumber\Http\Controllers\LuckyNumberController;
use App\Features\Games\Plugins\LuckyWheel\Http\Controllers\LuckyWheelController;
use App\Features\Games\Plugins\Ludo\Http\Controllers\LudoController;
use App\Features\Games\Plugins\Quiz\Http\Controllers\QuizController;
use App\Features\Games\Plugins\RedEnvelope\Http\Controllers\RedEnvelopeController;
use App\Features\Games\Plugins\RockPaperScissors\Http\Controllers\RockPaperScissorsController;
use App\Features\Games\Plugins\SlotMachine\Http\Controllers\SlotMachineController;
use App\Features\Games\Plugins\TreasureBox\Http\Controllers\TreasureBoxController;
use App\Features\GeoDiscovery\Http\Controllers\GeoDiscoveryController;
use App\Features\Gifts\Http\Controllers\GiftController;
use App\Features\Global\Http\Controllers\GlobalController;
use App\Features\Kyc\Http\Controllers\AdminKycController;
use App\Features\Kyc\Http\Controllers\KycController;
use App\Features\Levels\Http\Controllers\LevelController;
use App\Features\LiveChat\Http\Controllers\LiveChatController;
use App\Features\LiveShopping\Http\Controllers\CartController;
use App\Features\LiveShopping\Http\Controllers\LiveShoppingPinController;
use App\Features\LiveShopping\Http\Controllers\MerchantAnalyticsController;
use App\Features\LiveShopping\Http\Controllers\OrderController;
use App\Features\LiveShopping\Http\Controllers\StoreCouponController;
use App\Features\LiveShopping\Http\Controllers\StoreProductController;
use App\Features\LiveStreaming\Http\Controllers\LiveCategoryController;
use App\Features\LiveStreaming\Http\Controllers\LiveRoomController;
use App\Features\Marketplace\Http\Controllers\MarketplaceController;
use App\Features\MultiPkBattle\Http\Controllers\MultiPkBattleController;
use App\Features\Notifications\Http\Controllers\NotificationController;
use App\Features\PkBattle\Http\Controllers\PkBattleController;
use App\Features\Profiles\Http\Controllers\ProfileController;
use App\Features\Ranking\Http\Controllers\RankingController;
use App\Features\Reports\Http\Controllers\ReportController;
use App\Features\Settings\Http\Controllers\SettingController;
use App\Features\Svip\Http\Controllers\SvipController;
use App\Features\Users\Http\Controllers\CurrentUserController;
use App\Features\UserSearch\Http\Controllers\UserSearchController;
use App\Features\UserStatus\Http\Controllers\UserStatusController;
use App\Features\Vip\Http\Controllers\VipController;
use App\Features\VoicePkBattle\Http\Controllers\VoicePkBattleController;
use App\Features\VoiceRoom\Http\Controllers\VoiceRoomController;
use App\Features\CreatorTools\Http\Controllers\RtmpIngestController;
use App\Features\CreatorTools\Http\Controllers\StreamAnalyticsController;
use App\Features\CreatorTools\Http\Controllers\StreamClipController;
use App\Features\CreatorTools\Http\Controllers\StreamReplayController;
use App\Features\CreatorTools\Http\Controllers\StreamScheduleController;
use App\Features\Growth\Http\Controllers\CampaignController;
use App\Features\Growth\Http\Controllers\InviteController;
use App\Features\Growth\Http\Controllers\PromoCodeController;
use App\Features\Growth\Http\Controllers\ReferralController;
use App\Features\Payments\Http\Controllers\InvoiceController;
use App\Features\Payments\Http\Controllers\PaymentController;
use App\Features\Payments\Http\Controllers\PaymentWebhookController;
use App\Features\SocialEngagement\Http\Controllers\AchievementController;
use App\Features\SocialEngagement\Http\Controllers\CheckInController;
use App\Features\SocialEngagement\Http\Controllers\MentorshipController;
use App\Features\Wallet\Http\Controllers\OfflineRechargeController;
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

    // Public payment gateway webhooks (verified via per-gateway signatures).
    Route::post('payments/webhooks/{gateway}', PaymentWebhookController::class)
        ->middleware('throttle:120,1')
        ->name('payments.webhook');

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
        Route::get('admin/offline-recharges', [OfflineRechargeController::class, 'adminIndex']);
        Route::patch('admin/offline-recharges/{offlineRechargeRequest}', [OfflineRechargeController::class, 'review']);
        Route::get('admin/coin-sellers', [AdminCoinSellerController::class, 'index'])
            ->middleware('throttle:60,1');
        Route::patch('admin/coin-sellers/{coinSeller}', [AdminCoinSellerController::class, 'review'])
            ->middleware('throttle:30,1');
        Route::post('admin/coin-sellers/{coinSeller}/inventory', [AdminCoinSellerController::class, 'allocateInventory'])
            ->middleware('throttle:30,1');
        Route::get('admin/kyc', [AdminKycController::class, 'index'])
            ->middleware('throttle:60,1');
        Route::patch('admin/kyc/{kycSubmission}', [AdminKycController::class, 'review'])
            ->middleware('throttle:30,1');

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
        Route::get('wallet/offline-recharges', [OfflineRechargeController::class, 'index'])
            ->middleware('throttle:60,1')
            ->name('wallet.offline-recharges.index');
        Route::post('wallet/offline-recharges', [OfflineRechargeController::class, 'store'])
            ->middleware('throttle:5,1')
            ->name('wallet.offline-recharges.store');

        Route::get('user-status', [UserStatusController::class, 'show']);
        Route::patch('user-status', [UserStatusController::class, 'update']);

        Route::prefix('discovery')->name('discovery.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('/', [DiscoveryController::class, 'feed'])->name('feed');
            Route::get('trending-live', [DiscoveryController::class, 'trendingLive'])->name('trending-live');
            Route::get('trending-voice', [DiscoveryController::class, 'trendingVoice'])->name('trending-voice');
            Route::get('popular-hosts', [DiscoveryController::class, 'popularHosts'])->name('popular-hosts');
            Route::get('nearby', [DiscoveryController::class, 'nearby'])->name('nearby');
            Route::get('new-hosts', [DiscoveryController::class, 'newHosts'])->name('new-hosts');
            Route::get('recommended', [DiscoveryController::class, 'recommended'])->name('recommended');
            Route::get('categories', [DiscoveryController::class, 'categories'])->name('categories');
            Route::get('search', [DiscoveryController::class, 'search'])->name('search');
            Route::get('online', [DiscoveryController::class, 'online'])->name('online');
        });

        Route::prefix('geo')->name('geo.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('/', [GeoDiscoveryController::class, 'feed'])->name('feed');
            Route::get('location', [GeoDiscoveryController::class, 'showLocation'])->name('location.show');
            Route::put('location', [GeoDiscoveryController::class, 'updateLocation'])->name('location.update');
            Route::put('privacy', [GeoDiscoveryController::class, 'updatePrivacy'])->name('privacy');
            Route::get('nearby/users', [GeoDiscoveryController::class, 'nearbyUsers'])->name('nearby.users');
            Route::get('nearby/live', [GeoDiscoveryController::class, 'nearbyLive'])->name('nearby.live');
            Route::get('countries/{country}/live', [GeoDiscoveryController::class, 'countryLive'])
                ->name('countries.live');
            Route::get('countries/{country}/voice', [GeoDiscoveryController::class, 'countryVoice'])
                ->name('countries.voice');
            Route::get('countries/{country}/rankings', [GeoDiscoveryController::class, 'countryRankings'])
                ->name('countries.rankings');
        });

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
            Route::get('{room}/shopping/pins', [LiveShoppingPinController::class, 'index'])
                ->name('shopping.pins.index');
            Route::post('{room}/shopping/pins', [LiveShoppingPinController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('shopping.pins.store');
            Route::patch('{room}/shopping/pins/{pin}', [LiveShoppingPinController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('shopping.pins.update');
            Route::delete('{room}/shopping/pins/{pin}', [LiveShoppingPinController::class, 'destroy'])
                ->middleware('throttle:30,1')
                ->name('shopping.pins.destroy');
            Route::post('{room}/pk/request', [PkBattleController::class, 'request'])
                ->middleware('throttle:10,1')
                ->name('pk.request');
            Route::post('{room}/pk/accept', [PkBattleController::class, 'accept'])
                ->middleware('throttle:10,1')
                ->name('pk.accept');
            Route::post('{room}/multi-pk/request', [MultiPkBattleController::class, 'create'])
                ->middleware('throttle:10,1')
                ->name('multi-pk.request');
            Route::post('{room}/multi-pk/{battle}/accept', [MultiPkBattleController::class, 'accept'])
                ->middleware('throttle:10,1')
                ->name('multi-pk.accept');
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

        Route::prefix('multi-pk')->name('multi-pk.')->middleware('throttle:60,1')->group(function (): void {
            Route::post('{battle}/start', [MultiPkBattleController::class, 'start'])
                ->middleware('throttle:10,1')
                ->name('start');
            Route::post('{battle}/end', [MultiPkBattleController::class, 'end'])
                ->middleware('throttle:10,1')
                ->name('end');
            Route::get('{battle}', [MultiPkBattleController::class, 'show'])->name('show');
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
            Route::post('{room}/gifts/send', [GiftController::class, 'sendToVoiceRoom'])
                ->middleware('throttle:20,1')
                ->name('gifts.send');
            Route::post('{room}/pk/request', [VoicePkBattleController::class, 'request'])
                ->middleware('throttle:10,1')
                ->name('pk.request');
            Route::post('{room}/pk/accept', [VoicePkBattleController::class, 'accept'])
                ->middleware('throttle:10,1')
                ->name('pk.accept');
            Route::post('{room}/end', [VoiceRoomController::class, 'end'])->middleware('throttle:10,1')->name('end');
        });

        Route::prefix('voice-pk')->name('voice-pk.')->middleware('throttle:60,1')->group(function (): void {
            Route::post('{battle}/start', [VoicePkBattleController::class, 'start'])
                ->middleware('throttle:10,1')
                ->name('start');
            Route::post('{battle}/end', [VoicePkBattleController::class, 'end'])
                ->middleware('throttle:10,1')
                ->name('end');
            Route::get('{battle}', [VoicePkBattleController::class, 'show'])->name('show');
        });

        Route::prefix('rankings')->name('rankings.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('hosts', [RankingController::class, 'hosts'])->name('hosts');
            Route::get('gifters', [RankingController::class, 'gifters'])->name('gifters');
            Route::get('pk', [RankingController::class, 'pk'])->name('pk');
            Route::get('voice', [RankingController::class, 'voice'])->name('voice');
            Route::get('levels', [RankingController::class, 'levels'])->name('levels');
        });

        Route::prefix('levels')->name('levels.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('me', [LevelController::class, 'me'])->name('me');
            Route::get('history', [LevelController::class, 'history'])->name('history');
            Route::get('daily-tasks', [LevelController::class, 'dailyTasks'])->name('daily-tasks');
            Route::post('daily-tasks/claim', [LevelController::class, 'claimDailyTask'])
                ->middleware('throttle:20,1')
                ->name('daily-tasks.claim');
            Route::get('badges', [LevelController::class, 'badges'])->name('badges');
            Route::get('curve', [LevelController::class, 'curve'])->name('curve');
        });

        Route::prefix('challenges')->name('challenges.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('/', [ChallengeController::class, 'index'])->name('index');
            Route::get('daily', [ChallengeController::class, 'daily'])->name('daily');
            Route::get('weekly', [ChallengeController::class, 'weekly'])->name('weekly');
            Route::get('history', [ChallengeController::class, 'history'])->name('history');
            Route::post('claim', [ChallengeController::class, 'claim'])
                ->middleware('throttle:20,1')
                ->name('claim');
        });

        Route::prefix('marketplace')->name('marketplace.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('items', [MarketplaceController::class, 'items'])->name('items');
            Route::get('items/{item}/stickers', [MarketplaceController::class, 'stickers'])->name('items.stickers');
            Route::get('inventory', [MarketplaceController::class, 'inventory'])->name('inventory');
            Route::get('purchases', [MarketplaceController::class, 'purchases'])->name('purchases');
            Route::get('equipped', [MarketplaceController::class, 'equipped'])->name('equipped');
            Route::post('purchase', [MarketplaceController::class, 'purchase'])
                ->middleware('throttle:20,1')
                ->name('purchase');
            Route::post('equip', [MarketplaceController::class, 'equip'])
                ->middleware('throttle:30,1')
                ->name('equip');
            Route::post('unequip', [MarketplaceController::class, 'unequip'])
                ->middleware('throttle:30,1')
                ->name('unequip');
        });

        Route::prefix('games')->name('games.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('/', [GameController::class, 'index'])->name('index');
            Route::get('history', [GameController::class, 'history'])->name('history');
            Route::get('leaderboard', [GameController::class, 'leaderboard'])->name('leaderboard');
            Route::get('stats', [GameController::class, 'stats'])->name('stats');
            Route::post('analytics', [GameController::class, 'analytics'])->name('analytics');
            Route::get('quiz/question', [GameController::class, 'quizQuestion'])->name('quiz.question');
            Route::get('lottery/current', [GameController::class, 'lotteryCurrent'])->name('lottery.current');

            Route::prefix('lucky-wheel')->name('lucky-wheel.')->group(function (): void {
                Route::get('config', [LuckyWheelController::class, 'config'])->name('config');
                Route::post('spins', [LuckyWheelController::class, 'spin'])
                    ->middleware('throttle:30,1')
                    ->name('spins');
                Route::get('history', [LuckyWheelController::class, 'history'])->name('history');
                Route::get('leaderboard', [LuckyWheelController::class, 'leaderboard'])->name('leaderboard');
            });

            Route::prefix('slot-machine')->name('slot-machine.')->group(function (): void {
                Route::get('config', [SlotMachineController::class, 'config'])->name('config');
                Route::post('play', [SlotMachineController::class, 'play'])
                    ->middleware('throttle:30,1')
                    ->name('play');
                Route::get('history', [SlotMachineController::class, 'history'])->name('history');
                Route::get('leaderboard', [SlotMachineController::class, 'leaderboard'])->name('leaderboard');
                Route::get('statistics', [SlotMachineController::class, 'statistics'])->name('statistics');
                Route::get('jackpot', [SlotMachineController::class, 'jackpot'])->name('jackpot');
            });

            Route::prefix('lucky-number')->name('lucky-number.')->group(function (): void {
                Route::get('config', [LuckyNumberController::class, 'config'])->name('config');
                Route::post('play', [LuckyNumberController::class, 'play'])
                    ->middleware('throttle:30,1')
                    ->name('play');
                Route::post('quick-pick', [LuckyNumberController::class, 'quickPick'])
                    ->middleware('throttle:30,1')
                    ->name('quick-pick');
                Route::get('history', [LuckyNumberController::class, 'history'])->name('history');
                Route::get('leaderboard', [LuckyNumberController::class, 'leaderboard'])->name('leaderboard');
                Route::get('statistics', [LuckyNumberController::class, 'statistics'])->name('statistics');
                Route::get('jackpot', [LuckyNumberController::class, 'jackpot'])->name('jackpot');
                Route::get('draws', [LuckyNumberController::class, 'draws'])->name('draws');
            });

            Route::prefix('lucky-dice')->name('lucky-dice.')->group(function (): void {
                Route::get('config', [LuckyDiceController::class, 'config'])->name('config');
                Route::post('play', [LuckyDiceController::class, 'play'])
                    ->middleware('throttle:30,1')
                    ->name('play');
                Route::get('history', [LuckyDiceController::class, 'history'])->name('history');
                Route::get('leaderboard', [LuckyDiceController::class, 'leaderboard'])->name('leaderboard');
            });

            Route::prefix('red-envelope')->name('red-envelope.')->group(function (): void {
                Route::get('config', [RedEnvelopeController::class, 'config'])->name('config');
                Route::post('create', [RedEnvelopeController::class, 'create'])
                    ->middleware('throttle:20,1')
                    ->name('create');
                Route::post('claim', [RedEnvelopeController::class, 'claim'])
                    ->middleware('throttle:60,1')
                    ->name('claim');
                Route::get('history', [RedEnvelopeController::class, 'history'])->name('history');
                Route::get('leaderboard', [RedEnvelopeController::class, 'leaderboard'])->name('leaderboard');
            });

            Route::prefix('treasure-box')->name('treasure-box.')->group(function (): void {
                Route::get('config', [TreasureBoxController::class, 'config'])->name('config');
                Route::get('history', [TreasureBoxController::class, 'history'])->name('history');
                Route::get('leaderboard', [TreasureBoxController::class, 'leaderboard'])->name('leaderboard');
                Route::get('statistics', [TreasureBoxController::class, 'statistics'])->name('statistics');
                Route::post('create', [TreasureBoxController::class, 'create'])
                    ->middleware('throttle:20,1')
                    ->name('create');
                Route::post('join', [TreasureBoxController::class, 'join'])
                    ->middleware('throttle:60,1')
                    ->name('join');
            });

            Route::prefix('quiz')->name('quiz.')->group(function (): void {
                Route::get('config', [QuizController::class, 'config'])->name('config');
                Route::get('categories', [QuizController::class, 'categories'])->name('categories');
                Route::get('questions', [QuizController::class, 'questions'])->name('questions');
                Route::get('history', [QuizController::class, 'history'])->name('history');
                Route::get('leaderboard', [QuizController::class, 'leaderboard'])->name('leaderboard');
                Route::get('statistics', [QuizController::class, 'statistics'])->name('statistics');
                Route::get('tournaments', [QuizController::class, 'tournaments'])->name('tournaments');
                Route::post('create', [QuizController::class, 'create'])
                    ->middleware('throttle:20,1')
                    ->name('create');
                Route::post('join', [QuizController::class, 'join'])
                    ->middleware('throttle:60,1')
                    ->name('join');
                Route::post('start', [QuizController::class, 'start'])
                    ->middleware('throttle:30,1')
                    ->name('start');
                Route::post('answer', [QuizController::class, 'answer'])
                    ->middleware('throttle:120,1')
                    ->name('answer');
            });

            Route::prefix('rps')->name('rps.')->group(function (): void {
                Route::get('config', [RockPaperScissorsController::class, 'config'])->name('config');
                Route::get('history', [RockPaperScissorsController::class, 'history'])->name('history');
                Route::get('leaderboard', [RockPaperScissorsController::class, 'leaderboard'])->name('leaderboard');
                Route::get('statistics', [RockPaperScissorsController::class, 'statistics'])->name('statistics');
                Route::get('tournaments', [RockPaperScissorsController::class, 'tournaments'])->name('tournaments');
                Route::get('matches/{match}', [RockPaperScissorsController::class, 'show'])->name('matches.show');
                Route::post('create', [RockPaperScissorsController::class, 'create'])
                    ->middleware('throttle:20,1')
                    ->name('create');
                Route::post('accept', [RockPaperScissorsController::class, 'accept'])
                    ->middleware('throttle:60,1')
                    ->name('accept');
                Route::post('move', [RockPaperScissorsController::class, 'move'])
                    ->middleware('throttle:120,1')
                    ->name('move');
                Route::post('rematch', [RockPaperScissorsController::class, 'rematch'])
                    ->middleware('throttle:20,1')
                    ->name('rematch');
                Route::post('join-tournament', [RockPaperScissorsController::class, 'joinTournament'])
                    ->middleware('throttle:30,1')
                    ->name('join-tournament');
            });

            Route::prefix('ludo')->name('ludo.')->group(function (): void {
                Route::get('config', [LudoController::class, 'config'])->name('config');
                Route::get('history', [LudoController::class, 'history'])->name('history');
                Route::get('leaderboard', [LudoController::class, 'leaderboard'])->name('leaderboard');
                Route::get('statistics', [LudoController::class, 'statistics'])->name('statistics');
                Route::get('replays', [LudoController::class, 'replays'])->name('replays');
                Route::get('tournaments', [LudoController::class, 'tournaments'])->name('tournaments');
                Route::get('matches/{match}', [LudoController::class, 'show'])->name('matches.show');
                Route::post('create', [LudoController::class, 'create'])->middleware('throttle:20,1')->name('create');
                Route::post('join', [LudoController::class, 'join'])->middleware('throttle:60,1')->name('join');
                Route::post('ready', [LudoController::class, 'ready'])->middleware('throttle:60,1')->name('ready');
                Route::post('roll', [LudoController::class, 'roll'])->middleware('throttle:120,1')->name('roll');
                Route::post('move', [LudoController::class, 'move'])->middleware('throttle:180,1')->name('move');
                Route::post('reconnect', [LudoController::class, 'reconnect'])->middleware('throttle:60,1')->name('reconnect');
                Route::post('leave', [LudoController::class, 'leave'])->middleware('throttle:30,1')->name('leave');
                Route::post('join-tournament', [LudoController::class, 'joinTournament'])->middleware('throttle:30,1')->name('join-tournament');
            });

            Route::post('play', [GameController::class, 'play'])
                ->middleware('throttle:30,1')
                ->name('play');
            Route::post('matches', [GameController::class, 'createMatch'])
                ->middleware('throttle:20,1')
                ->name('matches.store');
            Route::get('matches/{match}', [GameController::class, 'showMatch'])->name('matches.show');
            Route::post('matches/{match}/join', [GameController::class, 'joinMatch'])
                ->middleware('throttle:20,1')
                ->name('matches.join');
            Route::post('matches/{match}/action', [GameController::class, 'matchAction'])
                ->middleware('throttle:60,1')
                ->name('matches.action');
            Route::get('{slug}', [GameController::class, 'show'])->name('show');
        });

        Route::prefix('live-shopping')->name('live-shopping.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('products', [StoreProductController::class, 'index'])->name('products.index');
            Route::post('products', [StoreProductController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('products.store');
            Route::patch('products/{product}', [StoreProductController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('products.update');
            Route::delete('products/{product}', [StoreProductController::class, 'destroy'])
                ->middleware('throttle:30,1')
                ->name('products.destroy');
            Route::get('sellers/{user}/products', [StoreProductController::class, 'offlineStore'])
                ->name('sellers.products');

            Route::get('cart', [CartController::class, 'show'])->name('cart.show');
            Route::post('cart/items', [CartController::class, 'addItem'])
                ->middleware('throttle:40,1')
                ->name('cart.items.store');
            Route::patch('cart/items/{item}', [CartController::class, 'updateItem'])
                ->middleware('throttle:40,1')
                ->name('cart.items.update');
            Route::delete('cart/items/{item}', [CartController::class, 'removeItem'])
                ->middleware('throttle:40,1')
                ->name('cart.items.destroy');
            Route::delete('cart', [CartController::class, 'clear'])
                ->middleware('throttle:20,1')
                ->name('cart.clear');

            Route::post('checkout', [OrderController::class, 'checkout'])
                ->middleware('throttle:15,1')
                ->name('checkout');
            Route::get('orders', [OrderController::class, 'index'])->name('orders.index');
            Route::get('seller/orders', [OrderController::class, 'sellerIndex'])->name('seller.orders');
            Route::get('seller/analytics/overview', [MerchantAnalyticsController::class, 'overview'])
                ->name('seller.analytics.overview');
            Route::get('seller/analytics/products', [MerchantAnalyticsController::class, 'products'])
                ->name('seller.analytics.products');
            Route::get('seller/analytics/timeseries', [MerchantAnalyticsController::class, 'timeseries'])
                ->name('seller.analytics.timeseries');

            Route::get('coupons', [StoreCouponController::class, 'index'])->name('coupons.index');
            Route::post('coupons', [StoreCouponController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('coupons.store');
            Route::delete('coupons/{coupon}', [StoreCouponController::class, 'destroy'])
                ->middleware('throttle:20,1')
                ->name('coupons.destroy');
            Route::post('coupons/validate', [StoreCouponController::class, 'validateCode'])
                ->middleware('throttle:30,1')
                ->name('coupons.validate');

            Route::get('orders/{order}', [OrderController::class, 'show'])->name('orders.show');
            Route::post('orders/{order}/pay', [OrderController::class, 'pay'])
                ->middleware('throttle:15,1')
                ->name('orders.pay');
            Route::patch('orders/{order}/shipping', [OrderController::class, 'updateShipping'])
                ->middleware('throttle:30,1')
                ->name('orders.shipping');
        });

        // Monetization: real-money gateways (physical goods / merchant orders),
        // invoices, and buyer refund requests.
        Route::prefix('payments')->name('payments.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('gateways', [PaymentController::class, 'gateways'])->name('gateways');
            Route::get('/', [PaymentController::class, 'index'])->name('index');
            Route::post('intents', [PaymentController::class, 'createIntent'])
                ->middleware('throttle:20,1')
                ->name('intents.store');
            Route::get('{transaction}', [PaymentController::class, 'show'])->name('show');
            Route::post('{transaction}/refund', [PaymentController::class, 'refund'])
                ->middleware('throttle:10,1')
                ->name('refund');
        });

        Route::prefix('invoices')->name('invoices.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('/', [InvoiceController::class, 'index'])->name('index');
            Route::get('{invoice}', [InvoiceController::class, 'show'])->name('show');
        });

        // Growth: referrals, invite rewards, promo codes, push/email campaigns.
        Route::prefix('growth')->name('growth.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('referrals/me', [ReferralController::class, 'me'])->name('referrals.me');
            Route::post('referrals/attach', [ReferralController::class, 'attach'])
                ->middleware('throttle:10,1')
                ->name('referrals.attach');

            Route::get('invites', [InviteController::class, 'index'])->name('invites.index');
            Route::post('invites', [InviteController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('invites.store');
            Route::post('invites/{token}/accept', [InviteController::class, 'accept'])
                ->middleware('throttle:10,1')
                ->name('invites.accept');

            Route::post('promo-codes', [PromoCodeController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('promo-codes.store');
            Route::get('promo-codes/validate', [PromoCodeController::class, 'validateCode'])
                ->name('promo-codes.validate');
            Route::post('promo-codes/redeem', [PromoCodeController::class, 'redeem'])
                ->middleware('throttle:15,1')
                ->name('promo-codes.redeem');
            Route::get('promo-codes/redemptions', [PromoCodeController::class, 'redemptions'])
                ->name('promo-codes.redemptions');

            Route::get('campaigns', [CampaignController::class, 'index'])->name('campaigns.index');
            Route::post('campaigns', [CampaignController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('campaigns.store');
            Route::get('campaigns/{campaign}', [CampaignController::class, 'show'])->name('campaigns.show');
            Route::post('campaigns/{campaign}/dispatch', [CampaignController::class, 'dispatchNow'])
                ->middleware('throttle:10,1')
                ->name('campaigns.dispatch');
            Route::post('campaigns/{campaign}/cancel', [CampaignController::class, 'cancel'])
                ->middleware('throttle:10,1')
                ->name('campaigns.cancel');
        });

        // Creator tools: scheduler, clips/highlights, replays, RTMP/OBS, analytics.
        Route::prefix('creator')->name('creator.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('schedules', [StreamScheduleController::class, 'index'])->name('schedules.index');
            Route::post('schedules', [StreamScheduleController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('schedules.store');
            Route::post('schedules/{schedule}/cancel', [StreamScheduleController::class, 'cancel'])
                ->middleware('throttle:20,1')
                ->name('schedules.cancel');

            Route::get('clips', [StreamClipController::class, 'index'])->name('clips.index');
            Route::get('rooms/{room}/clips', [StreamClipController::class, 'roomIndex'])->name('rooms.clips');
            Route::post('rooms/{room}/clips', [StreamClipController::class, 'store'])
                ->middleware('throttle:30,1')
                ->name('rooms.clips.store');

            Route::get('replays', [StreamReplayController::class, 'index'])->name('replays.index');
            Route::get('replays/{replay}', [StreamReplayController::class, 'show'])->name('replays.show');

            Route::get('rtmp', [RtmpIngestController::class, 'show'])->name('rtmp.show');
            Route::post('rtmp', [RtmpIngestController::class, 'issue'])
                ->middleware('throttle:10,1')
                ->name('rtmp.issue');
            Route::post('rtmp/{credential}/revoke', [RtmpIngestController::class, 'revoke'])
                ->middleware('throttle:10,1')
                ->name('rtmp.revoke');

            Route::get('analytics/summary', [StreamAnalyticsController::class, 'summary'])->name('analytics.summary');
            Route::get('analytics', [StreamAnalyticsController::class, 'index'])->name('analytics.index');
            Route::get('analytics/rooms/{room}', [StreamAnalyticsController::class, 'show'])->name('analytics.room');
        });

        // Social engagement: mentorship, achievements, daily check-in.
        Route::prefix('social')->name('social.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('mentorships', [MentorshipController::class, 'index'])->name('mentorships.index');
            Route::get('mentorships/requests', [MentorshipController::class, 'incoming'])->name('mentorships.requests');
            Route::post('mentorships/requests', [MentorshipController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('mentorships.requests.store');
            Route::post('mentorships/requests/{mentorshipRequest}/accept', [MentorshipController::class, 'accept'])
                ->middleware('throttle:20,1')
                ->name('mentorships.requests.accept');
            Route::post('mentorships/requests/{mentorshipRequest}/reject', [MentorshipController::class, 'reject'])
                ->middleware('throttle:20,1')
                ->name('mentorships.requests.reject');
            Route::post('mentorships/{mentorship}/end', [MentorshipController::class, 'end'])
                ->middleware('throttle:20,1')
                ->name('mentorships.end');
            Route::post('mentorships/{mentorship}/sessions', [MentorshipController::class, 'logSession'])
                ->middleware('throttle:30,1')
                ->name('mentorships.sessions');

            Route::get('achievements', [AchievementController::class, 'index'])->name('achievements.index');
            Route::post('achievements/{achievement}/claim', [AchievementController::class, 'claim'])
                ->middleware('throttle:30,1')
                ->name('achievements.claim');

            Route::get('check-in', [CheckInController::class, 'status'])->name('check-in.status');
            Route::post('check-in', [CheckInController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('check-in.store');
        });

        // AI stubs: moderation, translation, recommendations, highlight suggestions.
        Route::prefix('ai')->name('ai.')->middleware('throttle:60,1')->group(function (): void {
            Route::post('moderate/text', [AiController::class, 'moderateText'])
                ->middleware('throttle:60,1')
                ->name('moderate.text');
            Route::post('moderate/voice', [AiController::class, 'moderateVoice'])
                ->middleware('throttle:60,1')
                ->name('moderate.voice');
            Route::post('translate', [AiController::class, 'translate'])
                ->middleware('throttle:60,1')
                ->name('translate');
            Route::post('recommend', [AiController::class, 'recommend'])
                ->middleware('throttle:30,1')
                ->name('recommend');
            Route::post('highlights/suggest', [AiController::class, 'suggestHighlights'])
                ->middleware('throttle:20,1')
                ->name('highlights.suggest');
            Route::get('jobs/{job}', [AiController::class, 'showJob'])->name('jobs.show');
        });

        // Global: geo compliance, timezone preferences/schedules, CDN config.
        Route::prefix('global')->name('global.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('compliance/evaluate', [GlobalController::class, 'evaluateCompliance'])
                ->name('compliance.evaluate');

            Route::get('timezones', [GlobalController::class, 'timezones'])->name('timezones');
            Route::get('timezone', [GlobalController::class, 'getTimezone'])->name('timezone.show');
            Route::put('timezone', [GlobalController::class, 'putTimezone'])->name('timezone.update');
            Route::post('timezone/convert', [GlobalController::class, 'convertTimezone'])
                ->middleware('throttle:30,1')
                ->name('timezone.convert');
            Route::get('timezone/schedules', [GlobalController::class, 'listSchedules'])
                ->name('timezone.schedules.index');
            Route::post('timezone/schedules', [GlobalController::class, 'createSchedule'])
                ->middleware('throttle:20,1')
                ->name('timezone.schedules.store');

            Route::get('cdn/config', [GlobalController::class, 'cdnConfig'])->name('cdn.config');
            Route::post('cdn/resolve', [GlobalController::class, 'cdnResolve'])
                ->middleware('throttle:60,1')
                ->name('cdn.resolve');
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

        Route::prefix('svip')->name('svip.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('levels', [SvipController::class, 'levels'])->name('levels');
            Route::get('me', [SvipController::class, 'me'])->name('me');
            Route::post('purchase', [SvipController::class, 'purchase'])
                ->middleware('throttle:10,1')
                ->name('purchase');
            Route::post('upgrade', [SvipController::class, 'upgrade'])
                ->middleware('throttle:10,1')
                ->name('upgrade');
        });

        Route::prefix('ads')->name('ads.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('eligibility', [AdController::class, 'eligibility'])->name('eligibility');
            Route::get('mine', [AdController::class, 'mine'])->name('mine');
            Route::get('placements/{placement}', [AdController::class, 'serve'])->name('serve');
            Route::post('/', [AdController::class, 'store'])
                ->middleware('throttle:20,1')
                ->name('store');
            Route::get('{ad}', [AdController::class, 'show'])->name('show');
            Route::patch('{ad}', [AdController::class, 'update'])
                ->middleware('throttle:30,1')
                ->name('update');
            Route::post('{ad}/pause', [AdController::class, 'pause'])
                ->middleware('throttle:30,1')
                ->name('pause');
            Route::post('{ad}/resume', [AdController::class, 'resume'])
                ->middleware('throttle:30,1')
                ->name('resume');
            Route::delete('{ad}', [AdController::class, 'destroy'])
                ->middleware('throttle:20,1')
                ->name('destroy');
            Route::post('{ad}/events', [AdController::class, 'track'])
                ->middleware('throttle:120,1')
                ->name('events');
            Route::get('{ad}/analytics', [AdController::class, 'analytics'])->name('analytics');
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

        Route::prefix('coin-sellers')->name('coin-sellers.')->middleware('throttle:60,1')->group(function (): void {
            Route::post('apply', [CoinSellerController::class, 'apply'])
                ->middleware('throttle:5,1')
                ->name('apply');
            Route::get('me', [CoinSellerController::class, 'me'])->name('me');
            Route::get('dashboard', [CoinSellerController::class, 'dashboard'])->name('dashboard');
            Route::get('sales', [CoinSellerController::class, 'sales'])->name('sales');
            Route::get('codes', [CoinSellerController::class, 'codes'])->name('codes');
            Route::get('inventory', [CoinSellerController::class, 'inventory'])->name('inventory');
            Route::get('commissions', [CoinSellerController::class, 'commissions'])->name('commissions');
            Route::post('sell', [CoinSellerController::class, 'sell'])
                ->middleware('throttle:20,1')
                ->name('sell');
            Route::post('codes', [CoinSellerController::class, 'createCode'])
                ->middleware('throttle:20,1')
                ->name('codes.create');
            Route::post('codes/{coinRechargeCode}/revoke', [CoinSellerController::class, 'revokeCode'])
                ->middleware('throttle:20,1')
                ->name('codes.revoke');
            Route::post('redeem', [CoinSellerController::class, 'redeem'])
                ->middleware('throttle:10,1')
                ->name('redeem');
        });

        Route::prefix('kyc')->name('kyc.')->middleware('throttle:60,1')->group(function (): void {
            Route::get('me', [KycController::class, 'me'])->name('me');
            Route::post('submit', [KycController::class, 'submit'])
                ->middleware('throttle:5,1')
                ->name('submit');
        });

        Route::prefix('families')->name('families.')->middleware('throttle:60,1')->group(function (): void {
            Route::post('create', [FamilyController::class, 'store'])
                ->middleware('throttle:10,1')
                ->name('create');
            Route::get('me', [FamilyController::class, 'me'])->name('me');
            Route::get('rankings', [FamilyController::class, 'rankings'])->name('rankings');
            Route::post('join', [FamilyController::class, 'join'])
                ->middleware('throttle:10,1')
                ->name('join');
            Route::get('{family}', [FamilyController::class, 'show'])->name('show');
            Route::post('{family}/invites', [FamilyController::class, 'invite'])
                ->middleware('throttle:20,1')
                ->name('invites');
            Route::post('{family}/approve', [FamilyController::class, 'approve'])
                ->middleware('throttle:20,1')
                ->name('approve');
            Route::post('{family}/reject', [FamilyController::class, 'reject'])
                ->middleware('throttle:20,1')
                ->name('reject');
            Route::patch('{family}/roles', [FamilyController::class, 'updateRole'])
                ->middleware('throttle:20,1')
                ->name('roles');
            Route::delete('{family}/members/{user}', [FamilyController::class, 'removeMember'])
                ->middleware('throttle:20,1')
                ->name('members.remove');
            Route::get('{family}/members', [FamilyController::class, 'members'])->name('members');
            Route::get('{family}/messages', [FamilyController::class, 'messages'])->name('messages');
            Route::post('{family}/messages', [FamilyController::class, 'sendMessage'])
                ->middleware('throttle:60,1')
                ->name('messages.send');
            Route::get('{family}/gifts', [FamilyController::class, 'gifts'])->name('gifts');
            Route::post('{family}/gifts', [FamilyController::class, 'sendGift'])
                ->middleware('throttle:30,1')
                ->name('gifts.send');
            Route::get('{family}/wallet', [FamilyController::class, 'wallet'])->name('wallet');
            Route::post('{family}/wallet/contribute', [FamilyController::class, 'contribute'])
                ->middleware('throttle:20,1')
                ->name('wallet.contribute');
            Route::get('{family}/missions', [FamilyController::class, 'missions'])->name('missions');
            Route::post('{family}/missions/{mission}/claim', [FamilyController::class, 'claimMission'])
                ->middleware('throttle:20,1')
                ->name('missions.claim');
            Route::get('{family}/badge', [FamilyController::class, 'badge'])->name('badge');
            Route::get('{family}/level', [FamilyController::class, 'level'])->name('level');
        });

        Route::prefix('couples')->name('couples.')->middleware('throttle:60,1')->group(function (): void {
            Route::post('propose', [CoupleController::class, 'propose'])
                ->middleware('throttle:10,1')
                ->name('propose');
            Route::get('me', [CoupleController::class, 'me'])->name('me');
            Route::get('proposals', [CoupleController::class, 'proposals'])->name('proposals');
            Route::get('rankings', [CoupleController::class, 'rankings'])->name('rankings');
            Route::post('proposals/{proposal}/accept', [CoupleController::class, 'accept'])
                ->middleware('throttle:20,1')
                ->name('proposals.accept');
            Route::post('proposals/{proposal}/reject', [CoupleController::class, 'reject'])
                ->middleware('throttle:20,1')
                ->name('proposals.reject');
            Route::delete('proposals/{proposal}', [CoupleController::class, 'cancel'])
                ->middleware('throttle:20,1')
                ->name('proposals.cancel');
            Route::get('{couple}', [CoupleController::class, 'show'])->name('show');
            Route::patch('{couple}', [CoupleController::class, 'update'])
                ->middleware('throttle:20,1')
                ->name('update');
            Route::delete('{couple}', [CoupleController::class, 'end'])
                ->middleware('throttle:10,1')
                ->name('end');
            Route::get('{couple}/gifts', [CoupleController::class, 'gifts'])->name('gifts');
            Route::post('{couple}/gifts', [CoupleController::class, 'sendGift'])
                ->middleware('throttle:30,1')
                ->name('gifts.send');
            Route::get('{couple}/tasks', [CoupleController::class, 'tasks'])->name('tasks');
            Route::post('{couple}/tasks/{task}/claim', [CoupleController::class, 'claimTask'])
                ->middleware('throttle:20,1')
                ->name('tasks.claim');
            Route::post('{couple}/check-in', [CoupleController::class, 'checkIn'])
                ->middleware('throttle:10,1')
                ->name('checkin');
            Route::get('{couple}/badge', [CoupleController::class, 'badge'])->name('badge');
            Route::get('{couple}/level', [CoupleController::class, 'level'])->name('level');
            Route::get('{couple}/anniversary', [CoupleController::class, 'anniversary'])->name('anniversary');
            Route::post('{couple}/anniversary/claim', [CoupleController::class, 'claimAnniversary'])
                ->middleware('throttle:10,1')
                ->name('anniversary.claim');
        });
    });
});
