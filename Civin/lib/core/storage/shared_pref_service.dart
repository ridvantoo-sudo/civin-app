import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:shared_preferences/shared_preferences.dart';

final Provider<SharedPrefService> sharedPrefServiceProvider =
    Provider<SharedPrefService>((Ref ref) => SharedPrefService());

final class SharedPrefService {
  SharedPrefService({SharedPreferencesAsync? preferences})
    : _preferences = preferences ?? SharedPreferencesAsync();

  final SharedPreferencesAsync _preferences;

  Future<String?> getString(String key) => _preferences.getString(key);
  Future<bool?> getBool(String key) => _preferences.getBool(key);
  Future<int?> getInt(String key) => _preferences.getInt(key);
  Future<double?> getDouble(String key) => _preferences.getDouble(key);

  Future<void> setString(String key, String value) =>
      _preferences.setString(key, value);

  Future<void> setBool(String key, bool value) =>
      _preferences.setBool(key, value);

  Future<void> setInt(String key, int value) => _preferences.setInt(key, value);

  Future<void> setDouble(String key, double value) =>
      _preferences.setDouble(key, value);

  Future<void> remove(String key) => _preferences.remove(key);
  Future<void> clear() => _preferences.clear();
}
