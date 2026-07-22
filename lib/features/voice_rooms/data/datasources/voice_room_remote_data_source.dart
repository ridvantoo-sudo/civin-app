import 'package:civin/core/network/dio_client.dart';
import 'package:civin/features/voice_rooms/data/models/voice_room_model.dart';
import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<VoiceRoomRemoteDataSource> voiceRoomRemoteDataSourceProvider =
    Provider<VoiceRoomRemoteDataSource>(
      (Ref ref) => DioVoiceRoomRemoteDataSource(ref.watch(dioClientProvider)),
    );

abstract interface class VoiceRoomRemoteDataSource {
  Future<VoiceRoomConnection> createRoom({
    required String title,
    String? description,
    String? thumbnail,
    int? seatCount,
  });

  Future<VoiceRoomConnection> joinRoom(String roomId);

  Future<VoiceRoom> leaveRoom(String roomId);

  Future<VoiceRoom> requestSeat(String roomId, {required int seatIndex});

  Future<VoiceRoom> approveSeat(String roomId, {required int seatIndex});

  Future<VoiceRoom> rejectSeat(String roomId, {required int seatIndex});

  Future<VoiceRoom> removeSpeaker(String roomId, {required int seatIndex});

  Future<VoiceRoom> muteSpeaker(
    String roomId, {
    required int seatIndex,
    bool muted = true,
  });

  Future<VoiceRoom> endRoom(String roomId);
}

final class DioVoiceRoomRemoteDataSource implements VoiceRoomRemoteDataSource {
  const DioVoiceRoomRemoteDataSource(this._client);

  final DioClient _client;

  @override
  Future<VoiceRoomConnection> createRoom({
    required String title,
    String? description,
    String? thumbnail,
    int? seatCount,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/voice/create',
      data: <String, dynamic>{
        'title': title,
        'description': ?description,
        'thumbnail': ?thumbnail,
        'seat_count': ?seatCount,
      },
    );
    return VoiceRoomModel.connectionFromJson(_data(response));
  }

  @override
  Future<VoiceRoomConnection> joinRoom(String roomId) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/voice/$roomId/join',
    );
    return VoiceRoomModel.connectionFromJson(_data(response));
  }

  @override
  Future<VoiceRoom> leaveRoom(String roomId) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/voice/$roomId/leave',
    );
    return VoiceRoomModel.roomFromJson(_data(response));
  }

  @override
  Future<VoiceRoom> requestSeat(
    String roomId, {
    required int seatIndex,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/voice/$roomId/seat/request',
      data: <String, dynamic>{'seat_index': seatIndex},
    );
    return VoiceRoomModel.roomFromJson(_data(response));
  }

  @override
  Future<VoiceRoom> approveSeat(
    String roomId, {
    required int seatIndex,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/voice/$roomId/seat/approve',
      data: <String, dynamic>{'seat_index': seatIndex},
    );
    return VoiceRoomModel.roomFromJson(_data(response));
  }

  @override
  Future<VoiceRoom> rejectSeat(
    String roomId, {
    required int seatIndex,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/voice/$roomId/seat/reject',
      data: <String, dynamic>{'seat_index': seatIndex},
    );
    return VoiceRoomModel.roomFromJson(_data(response));
  }

  @override
  Future<VoiceRoom> removeSpeaker(
    String roomId, {
    required int seatIndex,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/voice/$roomId/seat/remove',
      data: <String, dynamic>{'seat_index': seatIndex},
    );
    return VoiceRoomModel.roomFromJson(_data(response));
  }

  @override
  Future<VoiceRoom> muteSpeaker(
    String roomId, {
    required int seatIndex,
    bool muted = true,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/voice/$roomId/seat/mute',
      data: <String, dynamic>{'seat_index': seatIndex, 'muted': muted},
    );
    return VoiceRoomModel.roomFromJson(_data(response));
  }

  @override
  Future<VoiceRoom> endRoom(String roomId) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/voice/$roomId/end',
    );
    return VoiceRoomModel.roomFromJson(_data(response));
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
