import 'package:civin/features/onboarding/domain/repositories/onboarding_repository.dart';

final class IsOnboardingCompleted {
  const IsOnboardingCompleted(this._repository);

  final OnboardingRepository _repository;

  Future<bool> call() => _repository.isCompleted();
}
