import 'package:civin/core/services/app_logger.dart';
import 'package:civin/features/splash/domain/entities/splash_destination.dart';
import 'package:civin/features/splash/domain/repositories/splash_repository.dart';
import 'package:civin/features/splash/domain/usecases/resolve_splash_destination.dart';

final class SplashBootstrapService {
  const SplashBootstrapService({
    required this.repository,
    required this.resolveDestination,
    required this.logger,
    this.minimumDisplayDuration = const Duration(milliseconds: 1600),
  });

  final SplashRepository repository;
  final ResolveSplashDestination resolveDestination;
  final AppLogger logger;
  final Duration minimumDisplayDuration;

  Future<SplashDestination> bootstrap() async {
    final Stopwatch stopwatch = Stopwatch()..start();

    await Future.wait<void>(<Future<void>>[
      _initializeSecureStorage(),
      _initializeSharedPreferences(),
      _checkConnectivity(),
      _checkFirebase(),
    ]);

    final SplashDestination destination = await resolveDestination();
    final Duration elapsed = stopwatch.elapsed;
    if (elapsed < minimumDisplayDuration) {
      await Future<void>.delayed(minimumDisplayDuration - elapsed);
    }
    logger.info('Splash resolved destination: $destination');
    return destination;
  }

  Future<void> _initializeSecureStorage() async {
    try {
      await repository.initializeSecureStorage();
    } on Object catch (error, stackTrace) {
      logger.error(
        'Secure storage initialization failed',
        error: error,
        stackTrace: stackTrace,
      );
    }
  }

  Future<void> _initializeSharedPreferences() async {
    try {
      await repository.initializeSharedPreferences();
    } on Object catch (error, stackTrace) {
      logger.error(
        'Shared preferences initialization failed',
        error: error,
        stackTrace: stackTrace,
      );
    }
  }

  Future<void> _checkConnectivity() async {
    try {
      final bool connected = await repository.checkConnectivity();
      if (!connected) {
        logger.warning('Device is offline during splash bootstrap');
      }
    } on Object catch (error, stackTrace) {
      logger.error(
        'Connectivity check failed',
        error: error,
        stackTrace: stackTrace,
      );
    }
  }

  Future<void> _checkFirebase() async {
    try {
      final bool ready = await repository.isFirebaseReady();
      logger.info('Firebase ready: $ready');
    } on Object catch (error, stackTrace) {
      logger.error(
        'Firebase readiness check failed',
        error: error,
        stackTrace: stackTrace,
      );
    }
  }
}
