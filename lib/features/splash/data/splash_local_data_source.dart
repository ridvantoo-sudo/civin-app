import 'package:civin/core/constants/storage_keys.dart';
import 'package:civin/core/network/network_checker.dart';
import 'package:civin/core/services/firebase_service.dart';
import 'package:civin/core/storage/secure_storage.dart';
import 'package:civin/core/storage/shared_pref_service.dart';

final class SplashLocalDataSource {
  const SplashLocalDataSource({
    required this.sharedPreferences,
    required this.secureStorage,
    required this.networkChecker,
    required this.firebaseService,
  });

  final SharedPrefService sharedPreferences;
  final SecureStorage secureStorage;
  final NetworkChecker networkChecker;
  final FirebaseService firebaseService;

  Future<void> initializeSecureStorage() async {
    await secureStorage.contains(StorageKeys.authAccessToken);
  }

  Future<void> initializeSharedPreferences() async {
    await sharedPreferences.getBool(StorageKeys.onboardingCompleted);
  }

  Future<bool> checkConnectivity() => networkChecker.isConnected;

  Future<bool> isFirebaseReady() async => firebaseService.isInitialized;

  Future<bool> isOnboardingCompleted() async =>
      await sharedPreferences.getBool(StorageKeys.onboardingCompleted) ?? false;

  Future<bool> isLoggedIn() async {
    final String? token = await secureStorage.read(StorageKeys.authAccessToken);
    return token != null && token.trim().isNotEmpty;
  }
}
