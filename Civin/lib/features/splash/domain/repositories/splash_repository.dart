abstract interface class SplashRepository {
  Future<void> initializeSecureStorage();

  Future<void> initializeSharedPreferences();

  Future<bool> checkConnectivity();

  Future<bool> isFirebaseReady();

  Future<bool> isOnboardingCompleted();

  Future<bool> isLoggedIn();
}
