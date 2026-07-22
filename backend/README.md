# Civin API

Laravel 12 JSON API for Civin. Every application endpoint is under `/api/v1`.

## Setup

```bash
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate:fresh --seed
php artisan serve
```

Local tests are self-contained: PHPUnit uses in-memory SQLite, the sync queue, array cache/session/mail, and the null broadcaster. Run `php artisan test`.

Production defaults in `.env.example` target MySQL, Predis-backed Redis queues/cache, and configurable broadcasting. Set `FIREBASE_CREDENTIALS` to an absolute path to a service-account JSON file outside the repository. Run a queue worker for queued verification mail and future asynchronous listeners.

For Back4app Containers, use the repository-root `Dockerfile.back4app`, the
dashboard variable template in `deploy/.env.back4app.example`, and the complete
instructions in [`docs/back4app-deployment.md`](docs/back4app-deployment.md).

## Architecture and security

Code is feature-first under `app/Features`. Controllers only coordinate validated requests, services own workflows, actions/events describe domain transitions, repositories isolate persistence boundaries, and resources control API output. Shared framework integration is limited to `app/Support`.

All domain and Sanctum identifiers are UUIDs. Passwords use Laravel's configured hash, refresh tokens are 96-character opaque values stored only as SHA-256 hashes, and each successful rotation revokes its predecessor. Reuse of a revoked refresh token revokes its token family. Access and refresh tokens are tied to an upserted device; removing a device revokes all of its credentials. Firebase linking accepts an ID token only, verifies it through Kreait behind `FirebaseTokenVerifier`, and enforces a unique verified UID. Account deletion requires a password for non-guests, revokes credentials, removes identity fields, then soft-deletes the user.

Sanctum access lifetime is controlled by `SANCTUM_EXPIRATION`; refresh lifetime uses `REFRESH_TOKEN_EXPIRATION_DAYS`. Password-reset responses do not disclose whether an email exists. Email verification links are signed. Global settings expose only records marked public.

## API

Public authentication: `POST /auth/register`, `/auth/login`, `/auth/guest`, `/auth/refresh`, `/auth/forgot-password`, `/auth/reset-password`; signed `GET /auth/verify-email/{user}/{hash}`.

Authenticated authentication: `POST /auth/logout`, `/auth/firebase/link`, `/auth/email/verification-notification`; `DELETE /auth/account`.

Modules: `GET|PATCH /user`, `GET|PATCH /profile`, public user profiles, follow/request/list operations, blocking, user reports and admin review, user search, live/online status, live streaming rooms, live chat, devices, user settings, notifications, public active countries, and public settings. Use `Authorization: Bearer <access_token>`.

The complete social endpoint contract, payloads, privacy rules, report categories, status values, pagination, and error responses are documented in [`docs/social-api.md`](docs/social-api.md).

The private broadcast channel is `private-users.{userId}`. Reading one notification emits `notification.read`; channel authorization requires the authenticated user's UUID. Live rooms use `private-live.room.{roomId}` for lifecycle, viewer, and chat events (`message.sent`, `message.deleted`, `viewer.joined`, `viewer.left`).

## Application file catalog

Authentication:

- `app/Features/Authentication/Actions/Register.php`: maps validated registration data and runs registration; `Actions/Login.php`: maps credentials/device data and runs login; `Actions/GuestLogin.php`: creates a device-bound guest session.
- `app/Features/Authentication/Actions/RefreshToken.php`: rotates an opaque refresh token; `Actions/Logout.php`: revokes the current device session; `Actions/DeleteAccount.php`: coordinates password-confirmed deletion.
- `app/Features/Authentication/Actions/ForgotPassword.php`: sends enumeration-safe reset mail; `Actions/ResetPassword.php`: resets the password and revokes every access/refresh credential; `Actions/VerifyEmail.php`: sends notices and verifies signed email links.
- `app/Features/Authentication/Actions/LinkFirebase.php`: links only an identity returned by the verifier.
- `app/Features/Authentication/DTOs/DeviceData.php`: immutable normalized device metadata; `DTOs/RegisterData.php`: immutable registration input; `DTOs/LoginData.php`: immutable login input; `DTOs/TokenPair.php`: immutable issued-token result.
- `app/Features/Authentication/Events/UserRegistered.php`: committed registration event; `Events/AccountDeleted.php`: deletion event; `Events/FirebaseLinked.php`: identity-link event.
- `app/Features/Authentication/Listeners/SendEmailVerification.php`: queued-after-commit verification notification.
- `app/Features/Authentication/Http/Controllers/AuthenticationController.php`: action-only auth HTTP adapter; `Http/Controllers/EmailVerificationController.php`: verification action adapter.
- `app/Features/Authentication/Http/Requests/RegisterRequest.php`: registration rules; `Http/Requests/LoginRequest.php`: login/device rules; `Http/Requests/GuestRequest.php`: guest device rules.
- `app/Features/Authentication/Http/Requests/RefreshTokenRequest.php`: refresh credential rules; `Http/Requests/ForgotPasswordRequest.php`: forgot-password rules; `Http/Requests/ResetPasswordRequest.php`: reset token/password rules.
- `app/Features/Authentication/Http/Requests/LinkFirebaseRequest.php`: Firebase ID-token rules; `Http/Requests/DeleteAccountRequest.php`: account confirmation rules.
- `app/Features/Authentication/Http/Resources/TokenPairResource.php`: stable explicit Bearer/expiry envelope using nested user/device resources.
- `app/Features/Authentication/Models/RefreshToken.php`: UUID hashed refresh-token record.
- `app/Features/Authentication/Repositories/Contracts/RefreshTokenRepository.php`: refresh persistence/revocation contract; `Repositories/Eloquent/EloquentRefreshTokenRepository.php`: lock-aware Eloquent implementation.
- `app/Features/Authentication/Services/AuthenticationService.php`: transactional authentication lifecycle and secure token issuance; `Services/FirebaseTokenVerifier.php`: verifier contract; `Services/KreaitFirebaseTokenVerifier.php`: production ID-token verification; `Services/InvalidFirebaseToken.php`: safe invalid-token signal.

Users and profiles:

- `app/Features/Users/Models/User.php`: UUID authenticatable, verifiable, notifiable user; `app/Models/User.php`: framework compatibility alias.
- `app/Features/Users/Repositories/Contracts/UserRepository.php`: user lookup/update/locking contract; `Repositories/Eloquent/EloquentUserRepository.php`: Eloquent implementation.
- `app/Features/Users/Services/UserService.php`: current-user update and verification invalidation; `Http/Controllers/CurrentUserController.php`: user service adapter; `Http/Requests/UpdateUserRequest.php`: current-user rules; `Http/Resources/UserResource.php`: safe user representation.
- `app/Features/Profiles/Models/Profile.php`: UUID one-to-one profile model.
- `app/Features/Profiles/Repositories/Contracts/ProfileRepository.php`: profile creation/read/update contract; `Repositories/Eloquent/EloquentProfileRepository.php`: eager-loading Eloquent implementation.
- `app/Features/Profiles/Services/ProfileService.php`: current profile operations; `Http/Controllers/ProfileController.php`: profile service adapter; `Http/Requests/UpdateProfileRequest.php`: profile validation; `Http/Resources/ProfileResource.php`: profile representation.
- `app/Features/Followers`: transactional public follows and private requests, idempotent persistence, follower/following lists, actions, DTOs, policies, events, and queued database notifications.
- `app/Features/Blocking`: idempotent blocks, blocked-user lists, mutual interaction prevention, and transactional relationship cleanup.
- `app/Features/Reports`: categorized user reports, reporter history, admin policy/review workflow, events, and review notifications.
- `app/Features/UserSearch`: indexed username/nickname/UUID/country/online search with blocked-user exclusion.
- `app/Features/UserStatus`: online, last-seen, and live-session state with status-change events.
- `app/Features/LiveStreaming`: Agora-backed live rooms, categories, host start/end, viewer join/leave, session peak tracking, and private room broadcasts.
- `app/Features/LiveChat`: room-scoped `live_messages` (TEXT/JOIN/LEAVE/SYSTEM/ADMIN), chat settings, moderators, spam protection, host/moderator deletes, and `message.sent` / `message.deleted` / `viewer.joined` / `viewer.left` on `private-live.room.{roomId}`.

Devices and countries:

- `app/Features/Devices/Models/Device.php`: UUID soft-deletable device model; `Actions/UpsertDevice.php`: normalized upsert and registration event; `Events/DeviceRegistered.php`: device lifecycle event.
- `app/Features/Devices/Repositories/Contracts/DeviceRepository.php`: device persistence contract; `Repositories/Eloquent/EloquentDeviceRepository.php`: soft-delete-aware implementation.
- `app/Features/Devices/Services/DeviceService.php`: listing and atomic credential/device removal; `Policies/DevicePolicy.php`: ownership policy; `Http/Controllers/DeviceController.php`: service/policy adapter; `Http/Resources/DeviceResource.php`: non-sensitive device representation.
- `app/Features/Countries/Models/Country.php`: UUID country model; `Repositories/Contracts/CountryRepository.php`: active-country contract; `Repositories/Eloquent/EloquentCountryRepository.php`: active-only queries.
- `app/Features/Countries/Services/CountryService.php`: public country use cases; `Http/Controllers/CountryController.php`: country service adapter; `Http/Resources/CountryResource.php`: public country representation.

Settings and notifications:

- `app/Features/Settings/Models/Setting.php`: typed global setting model; `Models/UserSetting.php`: keyed JSON user setting model.
- `app/Features/Settings/Repositories/Contracts/SettingRepository.php`: public/user setting contract; `Repositories/Eloquent/EloquentSettingRepository.php`: transactional Eloquent implementation.
- `app/Features/Settings/Services/SettingService.php`: public and user setting use cases; `Http/Controllers/SettingController.php`: settings service adapter; `Http/Requests/UpdateUserSettingsRequest.php`: allowlisted typed setting rules.
- `app/Features/Notifications/Repositories/Contracts/NotificationRepository.php`: owned notification contract; `Repositories/Eloquent/EloquentNotificationRepository.php`: scoped database-notification implementation.
- `app/Features/Notifications/Services/NotificationService.php`: pagination, ownership-safe mutation, and event dispatch; `Events/NotificationRead.php`: private-channel broadcast event; `Http/Controllers/NotificationController.php`: notification service adapter; `Http/Resources/NotificationResource.php`: notification representation.

Shared integration, data, routes, and tests:

- `app/Support/Models/PersonalAccessToken.php`: UUID Sanctum token model; `app/Http/Controllers/Controller.php`: shared authorization-capable base controller; `app/Providers/AppServiceProvider.php`: all repository/verifier bindings, policy/event setup, factory discovery, and mail URLs.
- `database/migrations/0001_01_01_000000_create_users_table.php`: coherent UUID schema for users, password resets, sessions, countries, profiles, devices, settings, Sanctum tokens, refresh tokens, and notifications.
- `database/migrations/2026_07_22_000003_create_user_social_system_tables.php`: additive UUID social schema, profile counters/privacy/media fields, admin report authorization flag, foreign keys, indexes, and soft deletes.
- `database/factories/UserFactory.php`: user data; `ProfileFactory.php`: profile data; `DeviceFactory.php`: device data; `CountryFactory.php`: country data; `SettingFactory.php`: global setting data; `UserSettingFactory.php`: user setting data; `RefreshTokenFactory.php`: hashed refresh-token data.
- `database/seeders/CountrySeeder.php`: idempotent useful countries; `SettingSeeder.php`: public/private examples; `DatabaseSeeder.php`: invokes both seeders.
- `routes/api.php`: throttled versioned APIs including `/live` streaming and `/live/{room}/messages` chat; `routes/channels.php`: private user and live-room channel authorization; `bootstrap/app.php`: API, web, console, health, and broadcasting route registration.
- `config/auth.php`: feature user provider and refresh lifetime; `config/sanctum.php`: access-token lifetime and Sanctum middleware; `config/app.php`: frontend URL; `config/services.php`: Firebase credential path; `.env.example`: MySQL/Redis/broadcast/Firebase production template.
- `tests/Feature/AuthenticationTest.php`: auth happy paths, multi-device behavior, rotation/replay, enumeration, reset revocation, Firebase, throttling, guard, guest, deletion, and verification; `tests/Feature/ModuleEndpointsTest.php`: user/profile/device/country/settings/notification behavior, validation, and ownership; `tests/Feature/UserSocialSystemTest.php`: profiles, follows, private requests, counters, notifications, blocking, reports, search, status, and authorization.
