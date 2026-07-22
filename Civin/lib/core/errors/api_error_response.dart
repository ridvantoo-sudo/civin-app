import 'package:json_annotation/json_annotation.dart';

part 'api_error_response.g.dart';

@JsonSerializable(createToJson: false)
final class ApiErrorResponse {
  const ApiErrorResponse({required this.message, this.code, this.details});

  factory ApiErrorResponse.fromJson(Map<String, dynamic> json) =>
      _$ApiErrorResponseFromJson(json);

  final String message;
  final String? code;
  final Map<String, dynamic>? details;
}
