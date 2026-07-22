import 'package:civin/features/splash/domain/entities/splash_destination.dart';
import 'package:civin/features/splash/domain/repositories/splash_repository.dart';

final class ResolveSplashDestination {
  const ResolveSplashDestination(this._repository);

  final SplashRepository _repository;

  Future<SplashDestination> call() async {
    final bool onboardingCompleted = await _repository.isOnboardingCompleted();
    if (!onboardingCompleted) {
      return SplashDestination.onboarding;
    }
    final bool loggedIn = await _repository.isLoggedIn();
    if (loggedIn) {
      return SplashDestination.home;
    }
    return SplashDestination.login;
  }
}
