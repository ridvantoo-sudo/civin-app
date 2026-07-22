# Civin

Civin contains a Flutter live-streaming application and a Laravel 12 JSON API
under `backend/`. The backend includes authentication, profiles, followers,
private follow requests, blocking, reports, user search, online/live status,
devices, settings, and notifications. Backend setup and architecture are
documented in [`backend/README.md`](backend/README.md); the social API contract
is in [`backend/docs/social-api.md`](backend/docs/social-api.md).

The Flutter client currently uses Firebase identity and does not yet expose the
Laravel social features in its UI. Laravel social endpoints require the
Sanctum access token and backend user UUID returned by the Laravel
authentication API.

## Run

```sh
flutter pub get
dart run build_runner build --delete-conflicting-outputs

# Start the Laravel API (default http://127.0.0.1:8000)
cd backend && php artisan serve

# In another terminal — API_BASE_URL defaults to http://127.0.0.1:8000
flutter run --dart-define=ENVIRONMENT=development \
  --dart-define=ENABLE_FIREBASE=true

# Or override explicitly:
# flutter run --dart-define=API_BASE_URL=https://your-api.example.com
```

Firebase is opt-in until real Firebase project files are supplied. Add the
platform configuration generated for your Firebase project, then run with
`--dart-define=ENABLE_FIREBASE=true`. This prevents an unconfigured local build
from failing while retaining the real Firebase initialization path.

Android release signing reads `android/key.properties` when present. The file
must define `storeFile`, `storePassword`, `keyAlias`, and `keyPassword`; signing
files and properties are excluded by `.gitignore`. Without those credentials,
release artifacts remain unsigned rather than using insecure debug credentials.

## Architecture

`lib/core` contains application-wide infrastructure. `lib/features` is divided
by business capability, and every feature reserves `data`, `domain`,
`presentation`, `repository`, `services`, and `widgets` layers. Empty layers
contain `.gitkeep` markers so the architecture survives source control without
introducing fake implementations. `lib/shared` exports reusable UI primitives.
Static resources live at the Flutter-standard root-level `assets` directory.

## Foundation file guide

### Entry and configuration

- `lib/main.dart` installs guarded error handling, bootstraps services, and
  starts the Riverpod scope.
- `lib/app.dart` owns Material 3, light/dark themes, routing, global loading,
  and the global scaffold messenger.
- `lib/core/config/environment.dart` reads compile-time environment, API, and
  Firebase settings.
- `lib/core/constants/assets.dart` provides type-safe asset path builders.
- `lib/core/constants/strings.dart` centralizes shared application copy.
- `lib/core/constants/app_sizes.dart` defines spacing, radius, sizing, and
  page-padding tokens.
- `pubspec.yaml` declares runtime/code-generation packages and asset folders.
- `analysis_options.yaml` applies strict Flutter and Dart analyzer rules.
- `.gitignore` excludes build products and Android signing secrets.

### Theme and utilities

- `colors.dart` defines the semantic Civin palette.
- `text_styles.dart` defines the Material typography scale.
- `theme.dart` builds Material 3 light/dark themes and exposes theme-mode state.
- `responsive_helper.dart` classifies mobile, tablet, and desktop layouts.
- `extensions.dart` adds focused context and nullable-string conveniences.
- `app_utils.dart` provides keyboard focus handling and a disposable debouncer.

### Infrastructure

- `app_failure.dart` is the immutable Freezed failure model.
- `api_error_response.dart` parses structured API errors with Json Serializable.
- `dio_client.dart` configures Dio timeouts, connectivity checks, logging, and
  typed HTTP methods.
- `network_checker.dart` exposes current and streaming connectivity state.
- `firebase_service.dart` owns idempotent Firebase initialization.
- `secure_storage.dart` wraps encrypted platform storage.
- `shared_pref_service.dart` wraps the modern asynchronous preferences API.
- `app_logger.dart` centralizes structured application logging.
- `app_bootstrap.dart` installs framework/platform error capture and initializes
  enabled startup services.
- `permission_service.dart` centralizes permission status and requests.
- `package_info_service.dart` exposes installed application metadata.
- `base_repository.dart` maps data operations into typed success/failure values.
- `base_controller.dart` standardizes Riverpod asynchronous controller state.
- Generated `*.freezed.dart` and `*.g.dart` files implement immutable unions and
  JSON decoding; they are produced from the annotated source files.

### Navigation and reusable UI

- `router.dart` owns the root navigator, route constants, error route, and the
  neutral foundation shell required before feature screens exist.
- `loading_widget.dart`, `error_widget.dart`, and `empty_widget.dart` provide
  accessible async-state views.
- `primary_button.dart`, `text_field.dart`, and `custom_app_bar.dart` provide
  themed application controls.
- `global_loading.dart` provides Riverpod-controlled full-app loading state.
- `app_snackbar.dart`, `app_dialog.dart`, and `app_bottom_sheet.dart` standardize
  transient surfaces.
- `app_network_image.dart` wraps cached network images with loading/error states.
- `lib/shared/shared.dart` is the public export surface for shared widgets.
- `test/widget_test.dart` verifies that the foundation app renders successfully.

Flutter-generated platform files under `android`, `ios`, `web`, `macos`,
`windows`, and `linux` provide the standard stable-SDK host applications.
The Android Gradle file adds secure optional release signing, while the web
entry file carries Civin metadata.
Asset and feature-layer `.gitkeep` files only preserve intentionally empty
directories until real resources and business implementations are added.
# civin

A new Flutter project.

## Getting Started

This project is a starting point for a Flutter application.

A few resources to get you started if this is your first Flutter project:

- [Learn Flutter](https://docs.flutter.dev/get-started/learn-flutter)
- [Write your first Flutter app](https://docs.flutter.dev/get-started/codelab)
- [Flutter learning resources](https://docs.flutter.dev/reference/learning-resources)

For help getting started with Flutter development, view the
[online documentation](https://docs.flutter.dev/), which offers tutorials,
samples, guidance on mobile development, and a full API reference.
