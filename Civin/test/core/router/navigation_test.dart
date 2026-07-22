import 'package:civin/app.dart';
import 'package:civin/core/constants/strings.dart';
import 'package:civin/core/services/app_logger.dart';
import 'package:civin/features/onboarding/domain/repositories/onboarding_repository.dart';
import 'package:civin/features/onboarding/domain/usecases/complete_onboarding.dart';
import 'package:civin/features/onboarding/presentation/onboarding_controller.dart';
import 'package:civin/features/onboarding/services/onboarding_service.dart';
import 'package:civin/features/splash/domain/entities/splash_destination.dart';
import 'package:civin/features/splash/domain/repositories/splash_repository.dart';
import 'package:civin/features/splash/domain/usecases/resolve_splash_destination.dart';
import 'package:civin/features/splash/presentation/splash_providers.dart';
import 'package:civin/features/splash/services/splash_bootstrap_service.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:package_info_plus/package_info_plus.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    PackageInfo.setMockInitialValues(
      appName: AppStrings.appName,
      packageName: 'com.civin.app',
      version: '1.0.0',
      buildNumber: '1',
      buildSignature: '',
    );
  });

  testWidgets('splash navigates to onboarding on first launch', (
    WidgetTester tester,
  ) async {
    final _FakeSplashRepository repository = _FakeSplashRepository(
      onboardingCompleted: false,
      loggedIn: false,
    );
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          splashBootstrapServiceProvider.overrideWith(
            (Ref ref) => SplashBootstrapService(
              repository: repository,
              resolveDestination: ResolveSplashDestination(repository),
              logger: AppLogger(),
              minimumDisplayDuration: Duration.zero,
            ),
          ),
        ],
        child: const CivinApp(),
      ),
    );

    await tester.pump();
    await tester.pumpAndSettle();

    expect(find.text(AppStrings.onboardingTitleDiscover), findsOneWidget);
  });

  testWidgets('splash navigates to login for returning users', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          splashBootstrapProvider.overrideWith(
            (Ref ref) async => SplashDestination.login,
          ),
        ],
        child: const CivinApp(),
      ),
    );

    await tester.pump();
    await tester.pumpAndSettle();

    expect(find.text(AppStrings.login), findsOneWidget);
  });

  testWidgets('splash navigates to home when logged in', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          splashBootstrapProvider.overrideWith(
            (Ref ref) async => SplashDestination.home,
          ),
        ],
        child: const CivinApp(),
      ),
    );

    await tester.pump();
    await tester.pumpAndSettle();

    expect(find.text(AppStrings.home), findsOneWidget);
  });

  testWidgets('finishing onboarding navigates to login', (
    WidgetTester tester,
  ) async {
    final _FakeOnboardingRepository repository = _FakeOnboardingRepository();

    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          splashBootstrapProvider.overrideWith(
            (Ref ref) async => SplashDestination.onboarding,
          ),
          onboardingRepositoryProvider.overrideWith((Ref ref) => repository),
          onboardingServiceProvider.overrideWith(
            (Ref ref) => OnboardingService(
              completeOnboarding: CompleteOnboarding(repository),
              logger: AppLogger(),
            ),
          ),
        ],
        child: const CivinApp(),
      ),
    );

    await tester.pump();
    await tester.pumpAndSettle();

    expect(find.text(AppStrings.skip), findsOneWidget);
    await tester.tap(find.text(AppStrings.skip));
    await tester.pumpAndSettle();

    expect(find.text(AppStrings.login), findsOneWidget);
    expect(repository.completed, isTrue);
  });
}

final class _FakeSplashRepository implements SplashRepository {
  _FakeSplashRepository({
    required this.onboardingCompleted,
    required this.loggedIn,
  });

  final bool onboardingCompleted;
  final bool loggedIn;

  @override
  Future<void> initializeSecureStorage() async {}

  @override
  Future<void> initializeSharedPreferences() async {}

  @override
  Future<bool> checkConnectivity() async => true;

  @override
  Future<bool> isFirebaseReady() async => false;

  @override
  Future<bool> isOnboardingCompleted() async => onboardingCompleted;

  @override
  Future<bool> isLoggedIn() async => loggedIn;
}

final class _FakeOnboardingRepository implements OnboardingRepository {
  bool completed = false;

  @override
  Future<bool> isCompleted() async => completed;

  @override
  Future<void> complete() async {
    completed = true;
  }
}
