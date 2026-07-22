import 'package:civin/core/constants/storage_keys.dart';
import 'package:civin/core/storage/shared_pref_service.dart';

final class OnboardingLocalDataSource {
  const OnboardingLocalDataSource(this._sharedPreferences);

  final SharedPrefService _sharedPreferences;

  Future<bool> isCompleted() async =>
      await _sharedPreferences.getBool(StorageKeys.onboardingCompleted) ?? false;

  Future<void> complete() =>
      _sharedPreferences.setBool(StorageKeys.onboardingCompleted, true);
}
