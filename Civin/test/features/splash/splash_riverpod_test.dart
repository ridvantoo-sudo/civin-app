import 'package:civin/core/services/app_logger.dart';
import 'package:civin/features/onboarding/domain/repositories/onboarding_repository.dart';
import 'package:civin/features/onboarding/domain/usecases/complete_onboarding.dart';
import 'package:civin/features/onboarding/domain/usecases/is_onboarding_completed.dart';
import 'package:civin/features/onboarding/presentation/onboarding_controller.dart';
import 'package:civin/features/onboarding/services/onboarding_service.dart';
import 'package:civin/features/splash/domain/entities/splash_destination.dart';
import 'package:civin/features/splash/domain/repositories/splash_repository.dart';
import 'package:civin/features/splash/domain/usecases/resolve_splash_destination.dart';
import 'package:civin/features/splash/services/splash_bootstrap_service.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('ResolveSplashDestination', () {
    test('routes first launch to onboarding', () async {
      final ResolveSplashDestination useCase = ResolveSplashDestination(
        _FakeSplashRepository(onboardingCompleted: false, loggedIn: false),
      );
      expect(await useCase(), SplashDestination.onboarding);
    });

    test('routes returning logged-out users to login', () async {
      final ResolveSplashDestination useCase = ResolveSplashDestination(
        _FakeSplashRepository(onboardingCompleted: true, loggedIn: false),
      );
      expect(await useCase(), SplashDestination.login);
    });

    test('routes logged-in users to home', () async {
      final ResolveSplashDestination useCase = ResolveSplashDestination(
        _FakeSplashRepository(onboardingCompleted: true, loggedIn: true),
      );
      expect(await useCase(), SplashDestination.home);
    });
  });

  group('SplashBootstrapService', () {
    test('initializes dependencies and returns destination', () async {
      final _FakeSplashRepository repository = _FakeSplashRepository(
        onboardingCompleted: false,
        loggedIn: false,
        connected: false,
        firebaseReady: true,
      );
      final SplashBootstrapService service = SplashBootstrapService(
        repository: repository,
        resolveDestination: ResolveSplashDestination(repository),
        logger: AppLogger(),
        minimumDisplayDuration: Duration.zero,
      );

      final SplashDestination destination = await service.bootstrap();

      expect(destination, SplashDestination.onboarding);
      expect(repository.secureStorageInitialized, isTrue);
      expect(repository.sharedPreferencesInitialized, isTrue);
      expect(repository.connectivityChecked, isTrue);
      expect(repository.firebaseChecked, isTrue);
    });
  });

  group('Onboarding Riverpod controller', () {
    test('completes onboarding through notifier state', () async {
      final _FakeOnboardingRepository repository = _FakeOnboardingRepository();
      final ProviderContainer container = ProviderContainer(
        overrides: [
          onboardingRepositoryProvider.overrideWith((Ref ref) => repository),
          onboardingServiceProvider.overrideWith(
            (Ref ref) => OnboardingService(
              completeOnboarding: CompleteOnboarding(repository),
              logger: AppLogger(),
            ),
          ),
        ],
      );
      addTearDown(container.dispose);

      expect(await IsOnboardingCompleted(repository)(), isFalse);
      container.read(onboardingControllerProvider.notifier).next(4);
      expect(container.read(onboardingControllerProvider).currentIndex, 1);

      final bool completed = await container
          .read(onboardingControllerProvider.notifier)
          .complete();
      expect(completed, isTrue);
      expect(repository.completed, isTrue);
      expect(container.read(onboardingControllerProvider).isCompleting, isTrue);
    });
  });
}

final class _FakeSplashRepository implements SplashRepository {
  _FakeSplashRepository({
    required this.onboardingCompleted,
    required this.loggedIn,
    this.connected = true,
    this.firebaseReady = false,
  });

  final bool onboardingCompleted;
  final bool loggedIn;
  final bool connected;
  final bool firebaseReady;

  bool secureStorageInitialized = false;
  bool sharedPreferencesInitialized = false;
  bool connectivityChecked = false;
  bool firebaseChecked = false;

  @override
  Future<void> initializeSecureStorage() async {
    secureStorageInitialized = true;
  }

  @override
  Future<void> initializeSharedPreferences() async {
    sharedPreferencesInitialized = true;
  }

  @override
  Future<bool> checkConnectivity() async {
    connectivityChecked = true;
    return connected;
  }

  @override
  Future<bool> isFirebaseReady() async {
    firebaseChecked = true;
    return firebaseReady;
  }

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
