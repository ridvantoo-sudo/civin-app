abstract interface class OnboardingRepository {
  Future<bool> isCompleted();

  Future<void> complete();
}
