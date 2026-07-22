import 'package:civin/core/network/dio_client.dart';
import 'package:civin/features/live_chat/data/models/live_message_model.dart';
import 'package:civin/features/live_chat/domain/entities/live_message.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<LiveChatRemoteDataSource> liveChatRemoteDataSourceProvider =
    Provider<LiveChatRemoteDataSource>(
      (Ref ref) => DioLiveChatRemoteDataSource(ref.watch(dioClientProvider)),
    );

abstract interface class LiveChatRemoteDataSource {
  Future<List<LiveMessage>> getMessages(String roomId, {int perPage = 50});

  Future<LiveMessage> sendMessage(
    String roomId, {
    required String message,
    Map<String, dynamic>? metadata,
  });

  Future<void> deleteMessage(String roomId, String messageId);
}

final class DioLiveChatRemoteDataSource implements LiveChatRemoteDataSource {
  const DioLiveChatRemoteDataSource(this._client);

  final DioClient _client;

  @override
  Future<List<LiveMessage>> getMessages(
    String roomId, {
    int perPage = 50,
  }) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/live/$roomId/messages',
      queryParameters: <String, dynamic>{'per_page': perPage},
    );
    final Object? data = _body(response)['data'];
    if (data is! List<dynamic>) {
      throw const FormatException('Invalid live chat messages response.');
    }
    final List<LiveMessage> messages = data
        .map((dynamic item) {
          if (item is! Map<String, dynamic>) {
            throw const FormatException('Invalid live chat message item.');
          }
          return LiveMessageModel.fromJson(item);
        })
        .toList(growable: false);
    return messages.reversed.toList(growable: false);
  }

  @override
  Future<LiveMessage> sendMessage(
    String roomId, {
    required String message,
    Map<String, dynamic>? metadata,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/live/$roomId/messages',
      data: <String, dynamic>{
        'message': message,
        'metadata': ?metadata,
      },
    );
    return LiveMessageModel.fromJson(_data(response));
  }

  @override
  Future<void> deleteMessage(String roomId, String messageId) async {
    await _client.delete<dynamic>('/api/v1/live/$roomId/messages/$messageId');
  }

  Map<String, dynamic> _body(Response<dynamic> response) {
    final Object? data = response.data;
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid API envelope.');
  }

  Map<String, dynamic> _data(Response<dynamic> response) {
    final Object? data = _body(response)['data'];
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid API data payload.');
  }
}
