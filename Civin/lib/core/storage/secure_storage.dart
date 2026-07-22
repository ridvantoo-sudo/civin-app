import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

final Provider<SecureStorage> secureStorageProvider = Provider<SecureStorage>(
  (Ref ref) => const SecureStorage(),
);

final class SecureStorage {
  const SecureStorage({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  Future<String?> read(String key) => _storage.read(key: key);

  Future<void> write(String key, String value) =>
      _storage.write(key: key, value: value);

  Future<void> delete(String key) => _storage.delete(key: key);

  Future<void> clear() => _storage.deleteAll();

  Future<bool> contains(String key) => _storage.containsKey(key: key);
}
