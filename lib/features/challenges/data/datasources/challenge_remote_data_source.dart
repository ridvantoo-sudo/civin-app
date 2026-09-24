import 'package:civin/core/network/dio_client.dart';
import 'package:civin/features/challenges/data/models/challenge_model.dart';
import 'package:civin/features/challenges/domain/entities/challenge.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<ChallengeRemoteDataSource> challengeRemoteDataSourceProvider =
    Provider<ChallengeRemoteDataSource>(
      (Ref ref) => DioChallengeRemoteDataSource(ref.watch(dioClientProvider)),
    );

abstract interface class ChallengeRemoteDataSource {
  Future<List<CivinChallenge>> list({String? period});

  Future<ChallengeClaim> claim(String challengeId);
}

final class DioChallengeRemoteDataSource implements ChallengeRemoteDataSource {
  const DioChallengeRemoteDataSource(this._client);

  final DioClient _client;

  @override
  Future<List<CivinChallenge>> list({String? period}) async {
    final String path = switch (period) {
      'daily' => '/api/v1/challenges/daily',
      'weekly' => '/api/v1/challenges/weekly',
      _ => '/api/v1/challenges',
    };
    final Response<dynamic> response = await _client.get<dynamic>(path);
    return ChallengeModel.listFromJson(_body(response)['data']);
  }

  @override
  Future<ChallengeClaim> claim(String challengeId) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/challenges/claim',
      data: <String, dynamic>{'challenge_id': challengeId},
    );
    return ChallengeModel.claimFromJson(_data(response));
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
