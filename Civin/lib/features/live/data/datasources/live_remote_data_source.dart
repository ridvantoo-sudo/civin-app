import 'package:civin/core/network/dio_client.dart';
import 'package:civin/features/live/data/models/live_room_model.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<LiveRemoteDataSource> liveRemoteDataSourceProvider =
    Provider<LiveRemoteDataSource>(
      (Ref ref) => DioLiveRemoteDataSource(ref.watch(dioClientProvider)),
    );

abstract interface class LiveRemoteDataSource {
  Future<List<LiveRoom>> getLiveRooms();
  Future<List<LiveCategory>> getCategories();
  Future<LiveRoom> createLiveRoom({
    required String title,
    required int categoryId,
    String? description,
  });
  Future<LiveConnection> startStream(String roomId);
  Future<LiveConnection> joinRoom(String roomId);
  Future<void> leaveRoom(String roomId);
  Future<void> endStream(String roomId);
}

final class DioLiveRemoteDataSource implements LiveRemoteDataSource {
  const DioLiveRemoteDataSource(this._client);

  static const String _path = '/api/v1/live';
  final DioClient _client;

  @override
  Future<List<LiveRoom>> getLiveRooms() async {
    final Response<dynamic> response = await _client.get<dynamic>(_path);
    final Object? data = _body(response)['data'];
    if (data is! List<dynamic>) {
      throw const FormatException('Invalid live rooms response.');
    }
    return data
        .map((dynamic item) {
          if (item is! Map<String, dynamic>) {
            throw const FormatException('Invalid live room item.');
          }
          return LiveRoomModel.fromJson(item);
        })
        .toList(growable: false);
  }

  @override
  Future<List<LiveCategory>> getCategories() async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '$_path/categories',
    );
    final Object? data = _body(response)['data'];
    if (data is! List<dynamic>) {
      throw const FormatException('Invalid live categories response.');
    }
    return data
        .map((dynamic item) {
          if (item is! Map<String, dynamic>) {
            throw const FormatException('Invalid live category item.');
          }
          return LiveRoomModel.categoryFromJson(item);
        })
        .toList(growable: false);
  }

  @override
  Future<LiveRoom> createLiveRoom({
    required String title,
    required int categoryId,
    String? description,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '$_path/create',
      data: <String, dynamic>{
        'title': title,
        'category_id': categoryId,
        if (description != null && description.isNotEmpty)
          'description': description,
      },
    );
    return LiveRoomModel.fromJson(_data(response));
  }

  @override
  Future<LiveConnection> startStream(String roomId) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '$_path/$roomId/start',
    );
    return LiveRoomModel.connectionFromJson(_data(response));
  }

  @override
  Future<LiveConnection> joinRoom(String roomId) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '$_path/$roomId/join',
    );
    return LiveRoomModel.connectionFromJson(_data(response));
  }

  @override
  Future<void> leaveRoom(String roomId) async {
    await _client.post<void>('$_path/$roomId/leave');
  }

  @override
  Future<void> endStream(String roomId) async {
    await _client.post<void>('$_path/$roomId/end');
  }

  Map<String, dynamic> _data(Response<dynamic> response) {
    final Object? data = _body(response)['data'];
    if (data is! Map<String, dynamic>) {
      throw const FormatException('Invalid live room response.');
    }
    return data;
  }

  Map<String, dynamic> _body(Response<dynamic> response) {
    final Object? body = response.data;
    if (body is! Map<String, dynamic>) {
      throw const FormatException('Invalid API response.');
    }
    return body;
  }
}
