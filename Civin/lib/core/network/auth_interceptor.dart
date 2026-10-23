import 'package:civin/core/constants/storage_keys.dart';
import 'package:civin/core/storage/secure_storage.dart';
import 'package:civin/features/authentication/services/api_session_service.dart';
import 'package:dio/dio.dart';

final class AuthInterceptor extends Interceptor {
  AuthInterceptor(this._dio, this._storage, this._sessions);

  static const String _retryKey = 'auth_token_retry';
  static const String _skipAuthKey = 'skip_auth';

  final Dio _dio;
  final SecureStorage _storage;
  final ApiSessionService _sessions;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    if (options.extra[_skipAuthKey] == true) {
      handler.next(options);
      return;
    }
    final String? token = await _storage.read(StorageKeys.authAccessToken);
    if (token != null && token.isNotEmpty) {
      options.headers['Authorization'] = 'Bearer $token';
    }
    handler.next(options);
  }

  @override
  Future<void> onError(
    DioException err,
    ErrorInterceptorHandler handler,
  ) async {
    final RequestOptions request = err.requestOptions;
    final bool canRefresh =
        err.response?.statusCode == 401 &&
        request.extra[_retryKey] != true &&
        request.extra[_skipAuthKey] != true;
    if (!canRefresh) {
      handler.next(err);
      return;
    }

    try {
      final token = await _sessions.refreshAccessToken();
      if (token == null) {
        handler.next(err);
        return;
      }
      request
        ..extra[_retryKey] = true
        ..headers['Authorization'] = 'Bearer ${token.value}';
      handler.resolve(await _dio.fetch<dynamic>(request));
    } on Object {
      handler.next(err);
    }
  }
}
