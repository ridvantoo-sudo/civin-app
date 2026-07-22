import 'package:civin/core/network/dio_client.dart';
import 'package:civin/features/rankings/data/models/ranking_model.dart';
import 'package:civin/features/rankings/domain/entities/ranking.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<RankingRemoteDataSource> rankingRemoteDataSourceProvider =
    Provider<RankingRemoteDataSource>(
      (Ref ref) => DioRankingRemoteDataSource(ref.watch(dioClientProvider)),
    );

abstract interface class RankingRemoteDataSource {
  Future<List<RankingEntry>> getRankings(RankingQuery query);
}

final class DioRankingRemoteDataSource implements RankingRemoteDataSource {
  const DioRankingRemoteDataSource(this._client);

  final DioClient _client;

  @override
  Future<List<RankingEntry>> getRankings(RankingQuery query) async {
    final Map<String, dynamic> params = <String, dynamic>{
      'period': query.period.apiValue,
      'limit': query.limit,
    };
    final String? country = query.effectiveCountry;
    if (country != null && country.isNotEmpty) {
      params['country'] = country;
    }

    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/rankings/${query.type.apiPath}',
      queryParameters: params,
    );
    final Object? data = _body(response)['data'];
    if (data is! List<dynamic>) {
      throw const FormatException('Invalid rankings response.');
    }
    return RankingModel.entriesFromJson(data);
  }

  Map<String, dynamic> _body(Response<dynamic> response) {
    final Object? data = response.data;
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid API envelope.');
  }
}
