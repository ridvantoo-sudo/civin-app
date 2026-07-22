import 'dart:async';

import 'package:civin/core/config/environment.dart';
import 'package:civin/core/constants/strings.dart';
import 'package:civin/core/network/auth_interceptor.dart';
import 'package:civin/core/network/network_checker.dart';
import 'package:civin/core/services/app_logger.dart';
import 'package:civin/core/storage/secure_storage.dart';
import 'package:civin/features/authentication/services/api_session_service.dart';
import 'package:civin/features/authentication/services/token_service.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<Dio> dioProvider = Provider<Dio>((Ref ref) {
  final String baseUrl = Environment.apiBaseUrl.trim();
  final Uri? parsed = Uri.tryParse(baseUrl);
  if (baseUrl.isEmpty || parsed == null || !parsed.hasScheme || parsed.host.isEmpty) {
    throw StateError(
      'API_BASE_URL is missing or invalid ("$baseUrl"). '
      'Pass a full URL, e.g. --dart-define=API_BASE_URL=http://127.0.0.1:8000',
    );
  }
  final Dio dio = Dio(
    BaseOptions(
      baseUrl: baseUrl,
      connectTimeout: const Duration(seconds: 20),
      receiveTimeout: const Duration(seconds: 30),
      sendTimeout: const Duration(seconds: 20),
      contentType: Headers.jsonContentType,
      responseType: ResponseType.json,
      headers: const <String, Object>{'Accept': Headers.jsonContentType},
    ),
  );
  dio.interceptors.add(
    _ConnectivityInterceptor(ref.watch(networkCheckerProvider)),
  );
  final ApiSessionService sessions = ApiSessionService(
    dio,
    ref.watch(tokenServiceProvider),
  );
  dio.interceptors.add(
    AuthInterceptor(dio, ref.watch(secureStorageProvider), sessions),
  );
  if (!Environment.isProduction) {
    dio.interceptors.add(
      LogInterceptor(
        requestBody: true,
        responseBody: true,
        logPrint: ref.watch(appLoggerProvider).debug,
      ),
    );
  }
  ref.onDispose(dio.close);
  return dio;
});

final Provider<DioClient> dioClientProvider = Provider<DioClient>(
  (Ref ref) => DioClient(ref.watch(dioProvider)),
);

final class DioClient {
  const DioClient(this._dio);

  final Dio _dio;

  Future<Response<T>> get<T>(
    String path, {
    Map<String, dynamic>? queryParameters,
    Options? options,
    CancelToken? cancelToken,
  }) => _dio.get<T>(
    path,
    queryParameters: queryParameters,
    options: options,
    cancelToken: cancelToken,
  );

  Future<Response<T>> post<T>(
    String path, {
    Object? data,
    Map<String, dynamic>? queryParameters,
    Options? options,
    CancelToken? cancelToken,
  }) => _dio.post<T>(
    path,
    data: data,
    queryParameters: queryParameters,
    options: options,
    cancelToken: cancelToken,
  );

  Future<Response<T>> put<T>(
    String path, {
    Object? data,
    Options? options,
    CancelToken? cancelToken,
  }) =>
      _dio.put<T>(path, data: data, options: options, cancelToken: cancelToken);

  Future<Response<T>> patch<T>(
    String path, {
    Object? data,
    Options? options,
    CancelToken? cancelToken,
  }) => _dio.patch<T>(
    path,
    data: data,
    options: options,
    cancelToken: cancelToken,
  );

  Future<Response<T>> delete<T>(
    String path, {
    Object? data,
    Options? options,
    CancelToken? cancelToken,
  }) => _dio.delete<T>(
    path,
    data: data,
    options: options,
    cancelToken: cancelToken,
  );
}

final class _ConnectivityInterceptor extends Interceptor {
  _ConnectivityInterceptor(this._networkChecker);

  final NetworkChecker _networkChecker;

  @override
  Future<void> onRequest(
    RequestOptions options,
    RequestInterceptorHandler handler,
  ) async {
    if (!await _networkChecker.isConnected) {
      handler.reject(
        DioException(
          requestOptions: options,
          type: DioExceptionType.connectionError,
          message: AppStrings.noInternet,
        ),
      );
      return;
    }
    handler.next(options);
  }
}
