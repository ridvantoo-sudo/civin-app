import 'package:freezed_annotation/freezed_annotation.dart';

part 'app_failure.freezed.dart';

@freezed
sealed class AppFailure with _$AppFailure {
  const factory AppFailure.network({required String message, int? statusCode}) =
      NetworkFailure;

  const factory AppFailure.storage({required String message}) = StorageFailure;

  const factory AppFailure.validation({required String message}) =
      ValidationFailure;

  const factory AppFailure.unexpected({
    required String message,
    Object? cause,
  }) = UnexpectedFailure;
}
