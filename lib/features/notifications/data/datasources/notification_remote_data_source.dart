import 'package:civin/core/network/dio_client.dart';
import 'package:civin/core/utils/json_values.dart';
import 'package:civin/features/notifications/domain/entities/app_notification.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<NotificationRemoteDataSource>
notificationRemoteDataSourceProvider = Provider<NotificationRemoteDataSource>(
  (Ref ref) => DioNotificationRemoteDataSource(ref.watch(dioClientProvider)),
);

abstract interface class NotificationRemoteDataSource {
  Future<List<AppNotification>> list();

  Future<void> markRead(String id);

  Future<void> markAllRead();
}

final class DioNotificationRemoteDataSource
    implements NotificationRemoteDataSource {
  const DioNotificationRemoteDataSource(this._client);

  final DioClient _client;

  @override
  Future<List<AppNotification>> list() async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/notifications',
    );
    return JsonValues.asMapList(
      _body(response)['data'],
    ).map(_fromJson).toList(growable: false);
  }

  @override
  Future<void> markRead(String id) async {
    await _client.patch<dynamic>('/api/v1/notifications/$id/read');
  }

  @override
  Future<void> markAllRead() async {
    await _client.patch<dynamic>('/api/v1/notifications/read-all');
  }

  AppNotification _fromJson(Map<String, dynamic> json) {
    final Map<String, Object?> data = JsonValues.asMap(json['data']);
    final String? title = JsonValues.asNullableString(
      data['title'] ?? data['message'] ?? data['body'],
    );
    final String type = JsonValues.asString(json['type'], fallback: 'update');
    return AppNotification(
      id: JsonValues.asString(json['id']),
      type: type,
      title: title ?? _label(type),
      body: JsonValues.asNullableString(data['body'] ?? data['message']),
      readAt: JsonValues.asDate(json['read_at']),
      createdAt: JsonValues.asDate(json['created_at']),
    );
  }

  String _label(String type) {
    final String short = type.split('\\').last;
    return short.replaceAllMapped(
      RegExp('([a-z])([A-Z])'),
      (Match match) => '${match.group(1)} ${match.group(2)}',
    );
  }

  Map<String, dynamic> _body(Response<dynamic> response) {
    final Object? data = response.data;
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid API envelope.');
  }
}
