import 'dart:math';

import 'package:civin/core/constants/storage_keys.dart';
import 'package:civin/core/storage/secure_storage.dart';
import 'package:civin/features/authentication/domain/entities/token.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<TokenService> tokenServiceProvider = Provider<TokenService>(
  (Ref ref) => TokenService(ref.watch(secureStorageProvider)),
);

final class TokenService {
  const TokenService(this._storage);

  static const String _tokenKey = 'auth.id_token';
  static const String _refreshTokenKey = 'auth.refresh_token';
  static const String _issuedAtKey = 'auth.token_issued_at';
  static const String _expiresAtKey = 'auth.token_expires_at';
  static const String _biometricKey = 'auth.biometric_enabled';
  static const String _deviceIdKey = 'auth.device_id';
  static final RegExp _uuidPattern = RegExp(
    r'^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$',
  );

  final SecureStorage _storage;

  Future<void> save(Token token, {String? refreshToken}) async {
    await Future.wait<void>(<Future<void>>[
      _storage.write(_tokenKey, token.value),
      _storage.write(_issuedAtKey, token.issuedAt.toIso8601String()),
      _storage.write(_expiresAtKey, token.expiresAt.toIso8601String()),
      if (refreshToken != null) _storage.write(_refreshTokenKey, refreshToken),
    ]);
    if (await isBiometricEnabled()) {
      await _storage.delete(StorageKeys.authAccessToken);
    } else {
      await _storage.write(StorageKeys.authAccessToken, token.value);
    }
  }

  Future<Token?> read() async {
    final List<String?> values = await Future.wait<String?>(<Future<String?>>[
      _storage.read(_tokenKey),
      _storage.read(_issuedAtKey),
      _storage.read(_expiresAtKey),
    ]);
    final DateTime? issuedAt = DateTime.tryParse(values[1] ?? '');
    final DateTime? expiresAt = DateTime.tryParse(values[2] ?? '');
    if (values[0] == null || issuedAt == null || expiresAt == null) return null;
    return Token(value: values[0]!, issuedAt: issuedAt, expiresAt: expiresAt);
  }

  Future<String?> readRefreshToken() => _storage.read(_refreshTokenKey);

  Future<void> clearSession() async {
    await Future.wait<void>(<Future<void>>[
      _storage.delete(_tokenKey),
      _storage.delete(_refreshTokenKey),
      _storage.delete(StorageKeys.authAccessToken),
      _storage.delete(_issuedAtKey),
      _storage.delete(_expiresAtKey),
    ]);
  }

  Future<bool> isBiometricEnabled() async =>
      await _storage.read(_biometricKey) == 'true';

  Future<void> setBiometricEnabled(bool enabled) async {
    await _storage.write(_biometricKey, enabled.toString());
    if (enabled) {
      await _storage.delete(StorageKeys.authAccessToken);
      return;
    }
    final String? token = await _storage.read(_tokenKey);
    if (token != null) {
      await _storage.write(StorageKeys.authAccessToken, token);
    }
  }

  Future<String> getOrCreateDeviceId() async {
    final String? existing = await _storage.read(_deviceIdKey);
    if (existing != null && _uuidPattern.hasMatch(existing)) return existing;
    final String id = _uuidV4();
    await _storage.write(_deviceIdKey, id);
    return id;
  }

  static String _uuidV4() {
    final Random random = Random.secure();
    final List<int> bytes = List<int>.generate(16, (_) => random.nextInt(256));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    String hex(int value) => value.toRadixString(16).padLeft(2, '0');
    final String all = bytes.map(hex).join();
    return '${all.substring(0, 8)}-${all.substring(8, 12)}-'
        '${all.substring(12, 16)}-${all.substring(16, 20)}-${all.substring(20)}';
  }
}
