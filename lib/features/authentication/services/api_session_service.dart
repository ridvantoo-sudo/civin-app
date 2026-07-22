import 'package:civin/core/network/dio_client.dart';
import 'package:civin/features/authentication/domain/entities/token.dart';
import 'package:civin/features/authentication/services/token_service.dart';
import 'package:dio/dio.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<ApiSessionService> apiSessionServiceProvider =
    Provider<ApiSessionService>(
      (Ref ref) => ApiSessionService(
        ref.watch(dioProvider),
        ref.watch(tokenServiceProvider),
      ),
    );

final class ApiSessionService {
  const ApiSessionService(this._dio, this._tokens);

  final Dio _dio;
  final TokenService _tokens;

  Future<Token> exchangeFirebaseIdToken(String idToken) async {
    final String deviceId = await _tokens.getOrCreateDeviceId();
    final Response<dynamic> response = await _dio.post<dynamic>(
      '/api/v1/auth/firebase/login',
      data: <String, dynamic>{
        'id_token': idToken,
        'device_id': deviceId,
        'device_name': _deviceName,
        'platform': _platform,
      },
      options: Options(
        extra: const <String, Object?>{'skip_auth': true},
      ),
    );
    return _persistTokenPair(response.data);
  }

  Future<Token?> refreshAccessToken() async {
    final String? refreshToken = await _tokens.readRefreshToken();
    if (refreshToken == null || refreshToken.isEmpty) return null;
    try {
      final Response<dynamic> response = await _dio.post<dynamic>(
        '/api/v1/auth/refresh',
        data: <String, dynamic>{'refresh_token': refreshToken},
        options: Options(
          extra: const <String, Object?>{'skip_auth': true},
        ),
      );
      return _persistTokenPair(response.data);
    } on DioException {
      return null;
    }
  }

  Future<Token> _persistTokenPair(Object? raw) async {
    if (raw is! Map) {
      throw StateError('Authentication API returned an invalid response.');
    }
    final Map<String, dynamic> data = Map<String, dynamic>.from(raw);
    final String? accessToken = data['access_token'] as String?;
    final String? refreshToken = data['refresh_token'] as String?;
    if (accessToken == null || accessToken.isEmpty) {
      throw StateError('Authentication API did not return an access token.');
    }
    final DateTime now = DateTime.now().toUtc();
    final DateTime expiresAt =
        DateTime.tryParse('${data['access_token_expires_at'] ?? ''}')?.toUtc() ??
        DateTime.tryParse('${data['expires_at'] ?? ''}')?.toUtc() ??
        now.add(const Duration(minutes: 60));
    final Token token = Token(
      value: accessToken,
      issuedAt: now,
      expiresAt: expiresAt,
    );
    await _tokens.save(token, refreshToken: refreshToken);
    return token;
  }

  static String get _platform {
    if (kIsWeb) return 'web';
    return switch (defaultTargetPlatform) {
      TargetPlatform.iOS => 'ios',
      TargetPlatform.android => 'android',
      _ => 'web',
    };
  }

  static String get _deviceName {
    if (kIsWeb) return 'Civin Web';
    return switch (defaultTargetPlatform) {
      TargetPlatform.iOS => 'Civin iOS',
      TargetPlatform.android => 'Civin Android',
      TargetPlatform.macOS => 'Civin macOS',
      _ => 'Civin Device',
    };
  }
}
