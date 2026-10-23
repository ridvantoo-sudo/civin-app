import 'package:civin/core/constants/strings.dart';
import 'package:civin/core/errors/api_error_response.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:dio/dio.dart';

sealed class RepositoryResult<T> {
  const RepositoryResult();

  R fold<R>({
    required R Function(T data) onSuccess,
    required R Function(AppFailure failure) onFailure,
  }) => switch (this) {
    RepositorySuccess<T>(:final data) => onSuccess(data),
    RepositoryFailure<T>(:final failure) => onFailure(failure),
  };
}

final class RepositorySuccess<T> extends RepositoryResult<T> {
  const RepositorySuccess(this.data);

  final T data;
}

final class RepositoryFailure<T> extends RepositoryResult<T> {
  const RepositoryFailure(this.failure);

  final AppFailure failure;
}

abstract base class BaseRepository {
  const BaseRepository();

  Future<RepositoryResult<T>> execute<T>(Future<T> Function() request) async {
    try {
      return RepositorySuccess<T>(await request());
    } on DioException catch (error) {
      return RepositoryFailure<T>(_mapDioException(error));
    } on Exception catch (error) {
      return RepositoryFailure<T>(
        AppFailure.unexpected(
          message: AppStrings.unexpectedError,
          cause: error,
        ),
      );
    }
  }

  AppFailure _mapDioException(DioException error) {
    final Object? data = error.response?.data;
    String message = error.message ?? AppStrings.unexpectedError;
    if (data is Map<String, dynamic>) {
      try {
        message = ApiErrorResponse.fromJson(data).message;
      } on Object {
        message = error.message ?? AppStrings.unexpectedError;
      }
    }
    return AppFailure.network(
      message: message,
      statusCode: error.response?.statusCode,
    );
  }
}
