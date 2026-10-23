import 'package:civin/core/network/dio_client.dart';
import 'package:civin/features/pk/data/models/pk_model.dart';
import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<PkRemoteDataSource> pkRemoteDataSourceProvider =
    Provider<PkRemoteDataSource>(
      (Ref ref) => DioPkRemoteDataSource(ref.watch(dioClientProvider)),
    );

abstract interface class PkRemoteDataSource {
  Future<PkBattle> requestPk(
    String roomId, {
    required String opponentRoomId,
    int? durationSeconds,
  });

  Future<PkBattle> acceptPk(String roomId);

  Future<PkBattle> startPk(String battleId);

  Future<PkBattle> endPk(String battleId);

  Future<PkBattle> getPk(String battleId);
}

final class DioPkRemoteDataSource implements PkRemoteDataSource {
  const DioPkRemoteDataSource(this._client);

  final DioClient _client;

  @override
  Future<PkBattle> requestPk(
    String roomId, {
    required String opponentRoomId,
    int? durationSeconds,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/live/$roomId/pk/request',
      data: <String, dynamic>{
        'opponent_room_id': opponentRoomId,
        'duration_seconds': ?durationSeconds,
      },
    );
    return PkModel.battleFromJson(_data(response));
  }

  @override
  Future<PkBattle> acceptPk(String roomId) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/live/$roomId/pk/accept',
    );
    return PkModel.battleFromJson(_data(response));
  }

  @override
  Future<PkBattle> startPk(String battleId) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/pk/$battleId/start',
    );
    return PkModel.battleFromJson(_data(response));
  }

  @override
  Future<PkBattle> endPk(String battleId) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/pk/$battleId/end',
    );
    return PkModel.battleFromJson(_data(response));
  }

  @override
  Future<PkBattle> getPk(String battleId) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/pk/$battleId',
    );
    return PkModel.battleFromJson(_data(response));
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
