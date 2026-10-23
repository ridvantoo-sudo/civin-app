# Civin technical and architectural report

Audit of the Civin live-streaming product: a Flutter client and a Laravel 12 JSON API. The report covers architecture, the product surface, the client/server contract, the coin economy, identity, realtime, operations, tests, and verified risks.

## 1. Source of truth

| Item | Value |
| --- | --- |
| Repository | `ridvantoo-sudo/civin-app` |
| Audited tree | commit `c1708822ae263183c9d31bdb779b4fc2f70552d6` |
| That commit | `Add files via upload`, 23 Jul 2026, 17:37 Asia/Baghdad |
| Current `main` | commit `91c9ba7`. The `Civin` directory was removed in `bd4cb28` (`Delete Civin directory`, 25 Jul 2026, 21:06 Asia/Baghdad). `main` has no application source. |
| Scale in the audited tree | 273 Dart files, 46 screens/pages, 519 PHP files, 37 Dart test files, 15 PHPUnit feature files |

Every finding below is from that snapshot. Later commits on `main` only delete files; they do not change the design.

## 2. Executive summary

Civin is a host-centric live product: video rooms, voice rooms, live chat, gifts, PK battles, rankings, VIP, agencies, and a coin/diamond wallet. The Flutter app is a feature-sliced Riverpod client. The API is a Laravel 12 vertical-slice backend under `Civin/backend/app/Features`, with Sanctum sessions, a Filament admin panel, Agora for media, and Pusher-protocol broadcasts for room and wallet events.

The two sides are further along than the root README claims. Social, live, gift, PK, voice, ranking, VIP, agency, and wallet screens call `/api/v1`. Three Flutter feature folders are still empty: direct chat, notifications, and settings.

The architecture is coherent, and the money paths that move coins between users (gifts, VIP, agency commission, PK rewards) use database transactions and row locks. The gaps that matter in production are operational and contractual:

1. Wallet recharge credits whatever coin amount the client sends. There is no store-receipt check.
2. Realtime defaults to the `log` broadcaster, and the Flutter socket reports “connected” when broadcast settings are missing.
3. Identity is split. The session user id is a Firebase UID. Social and wallet records use the Laravel UUID. Logout clears local tokens and does not call `POST /api/v1/auth/logout`.
4. `main` no longer contains the application, so this snapshot is not what a fresh clone builds.

## 3. System context

```text
Flutter app
  Firebase Auth (email, Google, Apple, phone, guest)
  Dio  ->  Laravel /api/v1   (Sanctum bearer)
  Agora RTC  ->  live video and voice audio
  Pusher-protocol WebSocket  ->  private room and wallet channels

Laravel 12
  MySQL (users, social graph, rooms, ledger)
  Redis (cache, queues, scheduler locks)
  Queue worker (gift side effects, broadcasts)
  Scheduler (daily/weekly rankings, VIP expiry)
  Filament /admin
  External Pusher-compatible broadcaster (not bundled)
```

There is no separate web product. `routes/web.php` serves the Laravel welcome view. Operators use Filament at `/admin`.

## 4. Repository layout

```text
Civin/
  lib/                  Flutter application
  test/                 Flutter unit and widget tests
  android/ ios/ web/    host projects
  assets/               fonts, icons, images, splash Lottie
  backend/              Laravel 12 API and Filament admin
    app/Features/       one directory per business capability
    app/Filament/       admin panel
    routes/api.php      /api/v1
    database/migrations
    tests/Feature
    deploy/             production and Back4app env templates, deploy.sh
    Dockerfile          Fly.io-style Apache image
  Dockerfile.back4app   Apache + queue + scheduler via supervisord
```

The root README still says the Flutter client does not expose Laravel social features, and it still contains the default “new Flutter project” getting-started block. Both statements are stale. `lib/core/router/router.dart` registers the social, live, and economy screens.

## 5. Flutter architecture

### 5.1 Stack

`pubspec.yaml` pins Dart SDK `^3.12.2` and app version `1.0.0+1`. Runtime libraries that define the architecture:

| Package | Role |
| --- | --- |
| `flutter_riverpod` ^3.3.2 | Application state |
| `go_router` ^17.3.0 | Routes and auth redirects |
| `dio` ^5.10.0 | Laravel HTTP |
| `firebase_core` / `firebase_auth` | Identity |
| `google_sign_in`, `sign_in_with_apple` | Federated sign-in |
| `flutter_secure_storage` | Tokens and device id |
| `agora_rtc_engine` ^6.5.4 | Live and voice media |
| `camera`, `wakelock_plus`, `permission_handler` | Host capture |
| `web_socket_channel` | Hand-rolled Pusher client |
| `freezed` / `json_serializable` | Failures and JSON models |
| `lottie`, `cached_network_image`, `emoji_picker_flutter` | UI |

`lib/main.dart` installs `runZonedGuarded`, calls `AppBootstrap.initialize()`, and mounts `ProviderScope` → `CivinApp`. `CivinApp` is a `MaterialApp.router` with Material 3 light/dark themes, the Riverpod router, and a global loading overlay.

### 5.2 Layers

`lib/core` is shared infrastructure: environment, theme, Dio, secure storage, router, and reusable widgets. `lib/features/<name>` is the business cut. Mature features use `data` / `domain` / `presentation` (remote data source, repository, use cases, Riverpod notifiers, screens). `lib/shared/shared.dart` only re-exports core widgets.

`BaseController` in `lib/core/base/base_controller.dart` is never extended. Feature notifiers are written by hand. `permission_service.dart` is also unreferenced. Several features still contain `.gitkeep` files next to real code, left over from the original empty-layer scaffold.

### 5.3 Configuration

`lib/core/config/environment.dart` reads compile-time defines:

| Define | Default | Effect |
| --- | --- | --- |
| `ENVIRONMENT` | `development` | Logging and environment label |
| `API_BASE_URL` | `http://127.0.0.1:8000` | Dio base URL |
| `ENABLE_FIREBASE` | false | Firebase init is skipped |
| `AGORA_APP_ID` | empty | RTC disabled until set |
| `BROADCAST_HOST`, `BROADCAST_PORT` (443), `BROADCAST_SCHEME` (https), `BROADCAST_APP_KEY` | empty | WebSocket target |

The socket URL is `{ws|wss}://{host}:{port}/app/{key}`.

### 5.4 Networking

`dio_client.dart` sets connect 20s, send 20s, receive 30s. Interceptors, in order:

1. Connectivity gate. Offline requests fail before the network.
2. `AuthInterceptor` attaches `Authorization: Bearer` from `StorageKeys.authAccessToken` (the Laravel Sanctum token, not the Firebase ID token).
3. On 401, one refresh via `POST /api/v1/auth/refresh`, then a single retry. Requests marked `skip_auth` (the Firebase exchange and the refresh call) do not send the bearer.
4. Outside production, a Dio log interceptor writes to `AppLogger`.

### 5.5 State

Providers are the composition root. Repositories and the WebSocket service are plain `Provider`s. Screen state uses `Notifier` and `AsyncNotifier`, often `.family` keyed by room id (`giftProvider`, `pkProvider`, `ChatController`, `voiceRoomProvider`). `authStateProvider` is a `StreamProvider` over Firebase auth. Splash destination is a `FutureProvider`.

### 5.6 Feature maturity

| Feature | Dart | Status |
| --- | --- | --- |
| splash, onboarding | real | Local flag in shared preferences, then route |
| authentication | real | Firebase identity, then Sanctum exchange |
| home | shell | Five tabs; Feed has no content |
| profile | real | Laravel social API |
| live, live_chat, gifts, pk | real | One routed room composes all four |
| voice_rooms | real | Create and join by id; no room directory |
| rankings, vip, agency, wallet | real | REST plus wallet socket |
| chat, notifications, settings | `.gitkeep` only | No screens, no data sources |

## 6. Navigation and product surface

Bottom navigation in `lib/features/home/presentation/home_page.dart`:

| Tab | Widget | What the user gets |
| --- | --- | --- |
| Feed | `_FeedTab` | Title text only, plus four app-bar shortcuts |
| Live | `LiveHomeScreen` | Room grid from the API, and Go live |
| Voice | `VoiceRoomHome` | Create, or join by typing a room id |
| Discover | `SearchUsersPage` | Debounced user search |
| Profile | `ProfilePage` | Laravel profile, follow counts, VIP badge |

Feed shortcuts push `/agency`, `/vip`, `/rankings`, and `/wallet`.

Auth redirect in `router.dart` runs only when `Firebase.apps` is not empty. If Firebase was not initialized, every route is open. When it is on:

- Protected routes (home, profile, live, voice, wallet, rankings, VIP, agency, `/users/…`) send an anonymous visitor to `/login`.
- An authenticated user is pushed off login, register, phone, and OTP.
- A non-anonymous user with an unverified email is held on `/verify-email`.
- A verified user with an empty Firebase `displayName` is held on `/complete-profile`.

`/`, `/onboarding`, `/forgot-password`, and `/reset-password` are not protected.

### 6.1 Route table

| Path | Screen |
| --- | --- |
| `/` | Splash |
| `/onboarding` | Onboarding |
| `/login`, `/register`, `/forgot-password`, `/reset-password` | Firebase email auth |
| `/phone-login`, `/otp-verification` | Firebase phone |
| `/verify-email`, `/complete-profile`, `/account-security` | Post-auth gates and security |
| `/home` | Tab shell |
| `/profile`, `/profile/edit` | Own profile |
| `/users/search`, `/users/:userId`, `…/followers`, `…/following`, `…/report` | Social |
| `/blocked-users` | Block list |
| `/live`, `/live/create`, `/live/:roomId` | Live home, create, room |
| `/wallet`, `/wallet/transactions`, `/wallet/recharge`, `/wallet/withdraw` | Wallet |
| `/voice`, `/voice/create`, `/voice/:roomId` | Voice |
| `/rankings`, `/rankings/hosts`, `/gifters`, `/pk`, `/voice` | Rankings |
| `/vip`, `/vip/levels`, `/vip/purchase`, `/vip/badge` | VIP |
| `/agency`, `/agency/create`, `/agency/profile`, `/agency/hosts`, `/agency/earnings` | Agency |

`/live/:roomId` builds `LiveRoomWithPkScreen`. That screen wraps `LiveRoomWithChatAndGiftsScreen`, which wraps `LiveRoomScreen`. Chat, gifts, and PK are overlays on one Agora view. `live_room_with_chat_screen.dart` is an earlier composition and is not imported by the router.

If a live deep link arrives without a `LiveRoom` in `extra`, the router fabricates a placeholder room. The client never calls `GET /api/v1/live/{room}`.

### 6.2 What each area actually does

**Splash and onboarding.** Splash watches `splashBootstrapProvider` and `go`s to onboarding, login, or home. “Logged in” means a Sanctum access token is present in secure storage, not that Firebase has a current user. Onboarding is a local page view; finishing it stores a flag and opens login.

**Authentication screens** talk to Firebase: email/password, Google, Apple, phone OTP, anonymous guest, password reset, email verification polling, and biometric unlock on restore. `CompleteProfilePage` writes the Firebase `displayName` and an optional photo URL. It does not `PATCH /api/v1/profile`.

**Profile** loads `GET /api/v1/profile`. Edit profile patches nickname, bio, avatar, cover, privacy, gender, birthday, and country. User details can follow, unfollow, block, and report. Followers, following, and blocked users are paginated. The profile “settings” icon opens `AccountSecurityPage` (biometrics, session refresh, logout, delete), not the empty settings feature and not `GET/PUT /user-settings`.

**Live.** The home grid lists rooms. Create captures a camera preview, title, and category, then starts a host session. The room screen joins Agora, shows viewer counts, and gives the host mute, camera, and leave. Chat history and send/delete go through REST; new messages arrive on the room socket when broadcast is configured.

**Gifts.** Catalog and send are REST. `gift.sent` updates the overlay. Animation is a placeholder, not a full SVGA player.

**PK.** Request, accept, start, and end are REST. The HUD listens for `pk.started`, `pk.score.updated`, and `pk.finished`.

**Voice.** There is no list endpoint and no discovery grid. The host creates a room (title, description, seat count). Everyone else pastes a room id. The room shows seats, audience count, chat, and Agora audio. Seat updates come from `private-voice.room.{id}`.

**Rankings.** Four leaderboards (hosts, gifters, PK, voice) read `/api/v1/rankings/…`. The numbers are produced by scheduled jobs, not live queries on every open.

**VIP.** Levels, current subscription, purchase, upgrade, and a badge preview. Purchase debits coins on the server.

**Agency.** Lookup by id, create, profile, apply, host list, and commission earnings. There is no directory of agencies.

**Wallet.** Balance, recent ledger, recharge grid, and withdraw form. The recharge packages are the constant `kDefaultRechargePackages` in `wallet_providers.dart`. The client generates `transactionId` and posts coins, price, and provider. `wallet.updated` refreshes the balance card when the socket is real.

## 7. Laravel architecture

### 7.1 Stack

`composer.lock` resolves Laravel `v12.64.0` on PHP `^8.2` (platform pin 8.3). Packages that shape the system:

| Package | Role |
| --- | --- |
| `laravel/sanctum` ^4 | API tokens |
| `kreait/firebase-php` ^8.3 | Verify Firebase ID tokens |
| `filament/filament` ^3.3 | Admin UI at `/admin` |
| `spatie/laravel-permission` ^8.3 | Admin roles |
| `spatie/laravel-activitylog` ^4.12 | Admin audit log |
| `cyberdeep/laravel-agora-token-generator` | RTC tokens |
| `predis/predis` | Redis client |

`laravel/reverb` and any payment SDK (Stripe, Apple, Google Play) are absent. Broadcasting uses Laravel’s driver env vars and expects an external Pusher-compatible server.

`bootstrap/app.php` is the Laravel 11+ style: `routes/api.php`, `web.php`, `channels.php`, `console.php`, and health `GET /up`. The only middleware aliases are Spatie’s `role`, `permission`, and `role_or_permission`. Proxies are trusted at `*`, which matches a platform load balancer and is unsafe if the process is reachable directly.

All JSON routes live in a `v1` group, so the public prefix is `/api/v1`.

### 7.2 Feature slices

Each capability under `app/Features` follows the same shape: models, DTOs, actions, HTTP controllers/requests/resources, repository contract plus Eloquent implementation, services, and optional policies, events, and listeners.

| Feature | Persistence | Money or state in a transaction |
| --- | --- | --- |
| Authentication | `users`, `refresh_tokens`, Sanctum tokens | Yes: register, guest, Firebase login, refresh, link, delete |
| Profiles, Users, UserSearch, Countries | profiles, users, countries | Profile writes are single-model |
| Followers, Blocking, Reports | followers, blocks, reports | Follow and block yes; report review is a plain update |
| Devices, Settings, UserStatus, Notifications | devices, settings, user_settings, user_status, notifications | Device upsert and user-settings upsert yes |
| LiveStreaming, LiveChat | rooms, viewers, sessions, messages, moderators | Yes |
| Gifts | catalog, transactions | Yes, including the wallet transfer |
| Wallet | wallets, ledger, recharge orders, withdraw requests | Yes |
| PkBattle | battles, scores, rewards | Yes; score updates are a queued listener |
| VoiceRoom | rooms, seats, participants, sessions | Yes |
| Ranking | rankings, snapshots | Yes, in the scheduled calculator |
| Vip | levels, subscriptions, vip transactions | Yes, with a wallet debit |
| Agency | agencies, members, commissions | Yes; commission is a queued listener on `GiftSent` |
| Admin | uses the models above | Ban runs in a transaction |

Filament resources live in `app/Filament`, not inside Features. `AdminPanelProvider` mounts them at `/admin`.

### 7.3 Domain events that leave the request

`GiftSent` is broadcast and also consumed by two queued, after-commit listeners:

- `UpdatePkScoreFromGift` adds the coin value to the active battle score.
- `CreateAgencyCommissionFromGift` moves a percentage of the receiver’s new diamonds to the agency owner.

Both listeners are idempotent on the gift transaction id. They do nothing useful unless a queue worker is running. Rankings and VIP expiry are scheduler jobs (`routes/console.php`): daily rankings at 00:05, weekly on Monday at 00:15, VIP expiry hourly, each with `onOneServer` and `withoutOverlapping`.

## 8. Data model and economy

Money is integer coins and diamonds (`unsignedBigInteger`). The only decimal rates are `agencies.commission_rate` and `agency_commissions.commission_rate` (`decimal(5,2)`).

### 8.1 Identity and social tables

`users` has a UUID primary key, email, username, `firebase_uid`, `is_guest`, `status`, `is_admin`, `banned_at`, `ban_reason`, password, soft deletes, and two-factor columns (`two_factor_secret`, recovery codes, `confirmed_at`). `profiles` hold the public card: nickname, bio, images, privacy, gender, birthday, country, follower counters, `is_vip`. Social edges are `followers` (`pending` or `accepted`), `blocks`, `reports`, and `user_status`.

### 8.2 Rooms

`live_rooms.status` is `created`, `live`, or `ended`, plus Agora channel fields. Viewers and sessions are separate tables. Chat is `live_messages` plus per-room settings and a moderator list. Voice mirrors this with `voice_rooms`, `voice_seats`, `voice_participants`, and `voice_sessions`. PK is `pk_battles`, `pk_scores`, and `pk_rewards`.

### 8.3 Ledger

`wallets` has `coins_balance` and `diamonds_balance`. `wallet_transactions` is the audit trail. `recharge_orders` is unique on `(payment_provider, transaction_id)`. `withdraw_requests` is an approval queue. Gifts have a catalog (`gift_categories`, `gifts.coin_price`) and `gift_transactions`. VIP is `vip_levels`, `user_vips`, `vip_transactions`. Agencies add `agency_members` and `agency_commissions`.

An earlier migration put `coin_balance` on `users`. `2026_07_22_000008_create_wallet_economy_tables` moves those balances onto `wallets`.

### 8.4 How value moves

**Gift.** Inside one transaction the repository locks the room, the two users, and both wallets. A repeated `client_request_id` returns the existing transaction. Sender coins decrease by the gift price. Receiver diamonds increase by the same integer. Two ledger rows are written. After commit, `GiftSent` is broadcast on the live room channel and the queued listeners run.

**Agency commission.** If the receiver is an agency host, the listener locks wallets again and transfers `commission_rate` percent of those diamonds from the host to the owner. A unique key on the source gift transaction stops double application.

**VIP.** Purchase or upgrade locks the user, the subscription, and the wallet, debits `coin_price` (or the upgrade delta), writes a VIP transaction and a ledger row, and sets `profiles.is_vip`.

**PK reward.** Ending a battle writes `pk_rewards` and credits diamonds through the wallet repository.

**Recharge.** The handler locks the user and the provider transaction id, then increments `coins_balance` by the `coins` field from the request. `RechargeWalletRequest` allows `coins` from 1 to 100000000 and `price` from 0. Nothing checks a store receipt, a server-side price list, or that `price` matches `coins`. The Flutter grid is cosmetic: a modified client can name any package and any coin amount. Idempotency only prevents the same `transaction_id` from crediting twice.

**Withdraw.** The request checks that diamonds are sufficient and inserts a `pending` row. It does not reserve or debit. Approval locks the request and debits. Two pending requests can each pass the balance check; the second approval fails only if the balance is already gone. Rejection changes status and does not touch the wallet.

```text
recharge (client-asserted) -> coins
coins --gift--> receiver diamonds
receiver diamonds --agency %--> owner diamonds
coins --VIP--> subscription
diamonds --PK reward--> diamonds
diamonds --withdraw request--> pending
pending --admin approve--> diamonds decrease
```

## 9. Identity and session

Two accounts exist for one person, and the app does not treat them as one id.

1. Firebase is the only interactive login. Email, Google, Apple, phone, and anonymous guest all return a Firebase user. `AuthRepositoryImpl._toUser` sets `User.id` to the Firebase UID.
2. After each successful sign-in, `_createSession` posts the Firebase ID token to `POST /api/v1/auth/firebase/login` with `device_id`, `device_name`, and `platform`.
3. The API verifies the token with `KreaitFirebaseTokenVerifier` (service account from `FIREBASE_CREDENTIALS`), finds or creates a `users` row by `firebase_uid`, and issues a Sanctum access token plus a refresh token. The Laravel user UUID is in the token response and is not stored as the app’s user id.
4. Dio sends only the Sanctum token. Profile and wallet calls therefore run as the Laravel user, while navigation and auth redirects still look at the Firebase user.

Splash routing keys off the stored Sanctum token. The router, when Firebase is enabled, keys off `authRepository.currentUser`. A token without a Firebase user, or a Firebase user without a token, sends the user to different places.

### 9.1 Tokens

Sanctum access tokens expire after `SANCTUM_EXPIRATION` minutes (default 60) and carry the ability `device:{device_id}`. Refresh tokens are SHA-256 hashes, bound to a device and a `family_id`, and last `REFRESH_TOKEN_EXPIRATION_DAYS` (default 30). Refresh locks the row, revokes the presented token, and issues a new pair in the same family. Presenting an already-revoked token revokes the whole family and returns “Refresh token replay detected”.

Firebase login on an existing user deletes that user’s Sanctum tokens and revokes refresh tokens before issuing the new pair. Logout in `AuthenticationService` deletes the current access token and the device’s refresh tokens. The Flutter `signOut` and `deleteAccount` methods never call those endpoints. They sign out of Firebase and delete the local secure-storage keys. The server session stays valid until it expires.

`deleteAccount` on the server exists (`DELETE /api/v1/auth/account`) and is covered by PHPUnit. The client deletes the Firebase account only.

### 9.2 Other auth endpoints the app does not call

The API also implements email/password register and login, guest login, forgot/reset password, signed email verification, `auth/me`, `auth/firebase/link`, and resend verification. The Flutter UI uses Firebase for those jobs. Guest on the server creates `is_guest` users with wallets; the app’s guest button is Firebase anonymous auth followed by the same Firebase exchange.

Email verification is enforced in the router from Firebase’s `emailVerified` flag. The Laravel `MustVerifyEmail` signed URL is a parallel mechanism the client does not use.

### 9.3 Ban gap

`AdminModerationService::banUser` stamps `banned_at` and deletes tokens. Login and refresh reject `status != active`. Ordinary `auth:sanctum` routes do not check `status`. A token that was not deleted, or a race that issues a token as the ban commits, keeps working until expiry.

## 10. Realtime and media

### 10.1 Agora

The API mints RTC tokens with the Agora certificate (`AGORA_APP_ID`, `AGORA_APP_CERTIFICATE`, `AGORA_TOKEN_TTL`). The app passes `AGORA_APP_ID` at compile time and uses `agora_rtc_engine` for the live video view and the voice room. Camera preview on the create-live screen uses the `camera` plugin. Neither side implements its own media server.

### 10.2 Broadcast

Private channels in `routes/channels.php`:

| Channel | Who may subscribe |
| --- | --- |
| `users.{userId}` | That user |
| `user.wallet.{userId}` | That user |
| `live.room.{roomId}` | Host, active viewer (`left_at` null), or live-chat moderator |
| `voice.room.{roomId}` | Host or active participant |

Events that implement `ShouldBroadcast` (most also `ShouldDispatchAfterCommit`): live started/ended, chat message sent/deleted, viewer joined/left, `gift.sent`, `wallet.updated`, `pk.started` / `pk.score.updated` / `pk.finished`, and voice room, seat, and speaker events. `NotificationRead` is `ShouldBroadcastNow` on the user channel. The Flutter client subscribes to the live, voice, and wallet channels and handles those event names. It never subscribes to `users.{userId}`, so notification broadcasts have no listener.

`PusherWebSocketService` speaks a minimal Pusher protocol: connect to `/app/{key}`, then `POST /broadcasting/auth` with the Sanctum bearer, then `pusher:subscribe`. There is no Reverb or Pusher SDK on either side. `.env.example`, the Back4app template, the production template, and `fly.toml` set `BROADCAST_CONNECTION=log`. With that driver, events are written to the log and never reach a socket. Even if the driver is changed, `ShouldBroadcast` jobs need a queue worker.

If `BROADCAST_HOST` or `BROADCAST_APP_KEY` is empty, `connect()` emits `connected` and `subscribe()` records the channel name and returns. Live chat, gifts, PK scores, voice seats, and wallet refreshes then look healthy and stay silent.

### 10.3 Channel name alignment

Backend docs mention `private-users.{userId}` and `private-live.room.{roomId}`. The Flutter service prefixes `private-` itself, which matches Laravel’s private-channel wire name. The contract is aligned when a real broadcaster and the dart-defines are both set. The failure mode is configuration, not a mismatched event name.

## 11. Client/server contract

| Area | Server | Flutter | Gap |
| --- | --- | --- | --- |
| Firebase exchange and refresh | `auth/firebase/login`, `auth/refresh` | Called | Server logout, delete, and `auth/me` are not called |
| Email/password and guest API | Full | Not called | Firebase is the only UI |
| Profile | `GET/PATCH /profile`, public profile | Called | Complete-profile writes Firebase only |
| Follow graph | follow, unfollow, lists | Called | `follower-requests` accept/reject have no UI, so private accounts cannot be approved in the app |
| Block | list, create, delete | Called | |
| Report | categories, create, history, admin review | categories and create | No history screen; admin is Filament/API only |
| User search | `GET /users/search` | Called with `query` and optional `is_online` | Country filter from the social API doc is unused |
| Devices | list, delete | None | |
| Settings | public settings, user settings | None | |
| Notifications | list, read, delete | None | |
| User status | get, patch | None | Presence is not pushed by the app |
| Countries | public list | None | Edit profile has a country id and does not load the catalog |
| Current user | `GET/PATCH /user` | None | Profile endpoints are used instead |
| Live | create, list, categories, start, end, join, leave, show | All except show | Deep link without `extra` uses a stub room |
| Live chat | list, send, delete | Called | |
| Gifts | catalog, send, history | Called | |
| PK | request, accept, start, end, show | Called | |
| Voice | create, join, leave, seats, end | Called | No list endpoint exists, so the home screen cannot browse rooms |
| Rankings | four boards | Called | |
| VIP | levels, me, purchase, upgrade | Called | |
| Agency | create, show, apply, review, members, hosts, earnings | Called | No agency index |
| Wallet | show, transactions, recharge, withdraw | Called | Packages and payment proof are client-side |
| Admin reports and withdrawals | policy-gated JSON | Not called | Filament is the operator UI |

## 12. Admin

Roles from `AdminRole`: Super Admin, Moderator, Finance Admin, Support Admin. Permissions are mapped in `AdminRole::permissionMap()`. `Gate::before` lets Super Admin pass every ability. `users.is_admin` still satisfies `canAccessPanel` and the report and withdraw policies, in parallel with Spatie roles. Filament exposes that flag as “Legacy admin flag”.

`User::canAccessPanel` requires a non-guest, non-banned user who is either `is_admin` or holds an admin role. Panel resources use `AuthorizesAdminAccess`. JSON admin routes authorize through `ReportPolicy` and `WithdrawRequestPolicy`. Feature tests assert that a normal Sanctum user receives 403.

Two-factor columns and `HasTwoFactorAuthentication` exist. The trait’s readiness method does not block Filament login. 2FA is schema only.

`AdminAuditLogger` writes Spatie `activity('admin')` for ban, unban, terminate room, and delete message.

`AdminUserSeeder` creates `admin@civin.app` / username `civin_admin` with password `password`, `is_admin`, and the Super Admin role, via `firstOrCreate`. `DatabaseSeeder` calls it. The Back4app entrypoint does not: when `BACK4APP_RUN_SEEDERS=true` it seeds countries, settings, and roles only. A developer who runs `php artisan db:seed` on a shared database gets the known password. The production doc warns about this; the seeder still contains the password.

`users.is_admin` is fillable, and `EloquentUserRepository::update` uses `forceFill`. The current profile request does not pass that field. Any future caller that forwards the request body into that repository would be able to set it.

`EloquentReportRepository::pending()` returns reports without a `status = pending` filter, so the “pending” admin list is every report.

## 13. Deployment and operations

Three documented ways to run the API:

**Back4app Containers** (`Dockerfile.back4app`, `backend/docs/back4app-deployment.md`). Multi-stage Composer `--no-dev` and Vite build, PHP 8.3 Apache on port 8080, supervisord for Apache plus optional queue and scheduler (`BACK4APP_RUN_QUEUE_WORKER`, `BACK4APP_RUN_SCHEDULER`). The entrypoint materializes Firebase credentials from `FIREBASE_CREDENTIALS_BASE64` or `FIREBASE_CREDENTIALS_JSON`, optionally migrates, optionally seeds, then `optimize`. Health check is `GET /up`. Disk is ephemeral; uploads need S3. The doc recommends at least 1 GB because Apache, a worker, and the scheduler share the container. Managed MySQL and Redis are required. This is not a Parse app.

**Fly.io** (`backend/Dockerfile`, `backend/fly.toml`). The image runs Apache only. `fly.toml` defines separate web, `queue:work`, and `schedule:work` processes, and a release command of `migrate --force`. Default broadcast connection in that file is `log`. The app name `civin-backend` has to be unique before deploy.

**Ubuntu** (`backend/docs/production-deployment.md`, `backend/deploy/scripts/deploy.sh`). Nginx in front of `backend/public`, Supervisor for the worker, cron for the scheduler. The doc’s CI example is a manual SSH deploy. This snapshot has no `.github/workflows`.

Workers and the scheduler are not optional for the product. Without them, gift commissions, PK scores, broadcasts, rankings, and VIP expiry do not run. All env templates ship `APP_DEBUG`-style local defaults in `.env.example` (`APP_DEBUG=true`, `SESSION_ENCRYPT=false`, `BROADCAST_CONNECTION=log`, `QUEUE_CONNECTION=redis`). Production templates exist under `backend/deploy/` and must actually be used.

Flutter release signing reads `android/key.properties` when present (`storeFile`, `storePassword`, `keyAlias`, `keyPassword`). Those files are gitignored. Without them, release builds stay unsigned. `android/app/google-services.json` and `ios/Runner/GoogleService-Info.plist` are committed, including Firebase client API keys. Those keys are meant to be restricted by package name and SHA-1 in the Firebase console; they are still client identifiers in the repo.

There is no in-repo CI, no PHPStan, and no `pint.json`. Laravel Pint is a dev dependency only. `backend/composer.json` exposes `composer test`. The root README does not mention `flutter test`.

## 14. Tests

PHPUnit feature tests cover register/login/refresh replay, Firebase login and token revocation, the social graph, live lifecycle and Agora tokens, chat moderation, gifts and idempotency, PK scoring and rewards, voice seats, wallet recharge idempotency and withdraw review, VIP purchase and the expiry job, ranking calculation, agency commissions, module smoke tests for countries/settings/devices/notifications, and Filament access by role. `tests/Feature/ExampleTest.php` and `tests/Unit/ExampleTest.php` are still the framework placeholders.

Not covered, and user-visible:

- A client choosing its own recharge coin amount.
- Two pending withdrawals that together exceed the diamond balance.
- A banned user calling a normal route with a still-valid token.
- Broadcast channel authorization (ended room, moderator who is not in the room).
- Guest users creating live or voice rooms.
- The Flutter app against a real API. Dart tests override repositories and data sources.

Flutter tests (37 files) assert validators, auth widgets, splash routing, and each mature feature’s provider, repository JSON mapping, and a representative widget. They lock the wiring. They do not prove Firebase, Sanctum, Agora, or the socket.

`analysis_options.yaml` enables `flutter_lints` plus strict rules (`always_use_package_imports`, `avoid_dynamic_calls`, and others).

## 15. Security findings

Verified by reading the cited files. Severity is about what a deployed build allows, not about whether a test exists.

### Critical

**Recharge trusts the client.** `RechargeWalletRequest` accepts `coins` and `price` from the body. `EloquentWalletRepository::recharge` credits `$data->coins` after an idempotency lock on the provider transaction id. No Apple, Google, or Stripe verification exists in `composer.json` or the wallet feature. Any authenticated caller can increase `coins_balance`.

### High

**Withdrawals do not reserve diamonds.** `requestWithdraw` checks the balance and inserts `pending`. Multiple requests can over-commit. Approval is first-come at review time.

**Logout and account deletion do not revoke the API session.** `auth_repository_impl.dart` `signOut` and `deleteAccount` clear Firebase and local tokens only. Stolen Sanctum tokens keep working until expiry (default 60 minutes) and refresh tokens until the family expires (default 30 days), because the client also never rotates them away on logout.

**Realtime lies when unconfigured.** `web_socket_service.dart` marks the socket connected and subscriptions successful when `BROADCAST_*` is empty, and every deploy template logs broadcasts instead of sending them. Moderators and hosts will not see chat, gifts, or seat changes.

**Known admin password in the default seeder.** `AdminUserSeeder` uses `password` for `admin@civin.app` and assigns Super Admin. `DatabaseSeeder` runs it. Back4app’ entrypoint avoids this class; `db:seed` does not.

### Medium

**Two user ids.** Firebase UID in the session versus Laravel UUID on every API resource. A screen that puts `User.id` into `/users/{user}` will 404 or hit the wrong row.

**Profile data is written twice.** Display name and photo on the Firebase user are independent of `PATCH /profile`.

**Private follow requests have no client.** Pending follows cannot be accepted or rejected in the app, so private accounts do not work end to end.

**Ban is not checked per request.** Token deletion at ban time is the only gate on existing sessions.

**`is_admin` is fillable** and the user repository `forceFill`s updates. Current HTTP requests do not pass it through.

**`APP_DEBUG=true` and `SESSION_ENCRYPT=false`** are the example defaults. `trustProxies(at: '*')` is unconditional.

**Firebase client config is committed** in the Android and iOS host projects.

### Low

**Admin “pending” reports are unfiltered** (`EloquentReportRepository::pending`).

**Many authenticated routes have no route throttle.** Auth, wallet, live, and report-create are throttled. Profile reads and social lists are not, beyond the search route’s 60/minute.

**2FA columns are unused** at login.

**Dead code:** `LiveRoomWithChatScreen`, `BaseController`, `PermissionService`.

**Feed is an empty tab**, and voice has no discovery. Those are product gaps, not security bugs.

## 16. Architectural assessment

What is in good shape:

- Feature folders on both sides match the product language (live, gifts, PK, voice, wallet, VIP, agency, rankings).
- The economy ledger is integer, transactional, and idempotent for gifts, VIP, PK rewards, and agency splits.
- Refresh tokens detect replay and revoke the family.
- Admin JSON routes and Filament resources are authorized, and tests lock the 403 path.
- The routed live room is one stack (media, chat, gifts, PK) rather than four competing screens. The unused chat-only screen is the leftover.
- Widget and feature tests exist for every Flutter module that has code, and PHPUnit covers each backend slice.

What will not scale as-is:

- The client pretends the socket is up. Failures need a visible disconnected state, and production needs a real broadcaster plus a worker.
- Payments cannot stay a trusted `coins` integer. A server price list and a verified receipt have to be the only way coins increase.
- One user id has to own routing, profile, and wallet. The Laravel UUID is already on the token response; the Firebase UID should stay an identity claim, not a path parameter.
- Logout has to be a server call. Account deletion has to be the Laravel endpoint, with Firebase deletion after the API succeeds.
- `main` cannot stay empty. The product source needs to live on the default branch, or every new clone is a blank repository with this snapshot only in history.

## 17. Recommended order of work

1. Put commit `c170882` (or a cleaned copy of it) back on the branch people clone. Keep secrets and the admin seeder password out of that restoration.
2. Stop crediting recharge from the request body. Define packages on the server. Accept a provider and a receipt, verify them, then credit the package’s coin amount. Use the existing `(payment_provider, transaction_id)` unique key.
3. Reserve diamonds when a withdrawal is requested, or reject new requests while one is pending and the sum would exceed the balance.
4. Call `POST /api/v1/auth/logout` and `DELETE /api/v1/auth/account` from the client. Reject `status != active` and `banned_at` in Sanctum middleware.
5. Point `BROADCAST_CONNECTION` at a Pusher-compatible server, set the Flutter `BROADCAST_*` defines to that host, and run a queue worker. Make `PusherWebSocketService.connect` emit `disconnected` when config is missing.
6. Persist the Laravel user UUID from the firebase-login response and use it for `/users/{id}` and wallet channel names. Keep using Firebase only to obtain the ID token.
7. Add the follower-request screen, or stop creating `pending` follows until it exists.
8. Replace the Feed placeholder and add a voice-room list endpoint plus a grid, if those tabs are part of the product.
9. Add a CI workflow that runs `php artisan test` and `flutter test`. Remove or gate `AdminUserSeeder` so `db:seed` cannot create `password`.

This audit did not run the test suites or boot the app. Findings are from the source at `c170882`.
