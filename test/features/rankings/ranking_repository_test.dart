import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/rankings/data/datasources/ranking_remote_data_source.dart';
import 'package:civin/features/rankings/data/models/ranking_model.dart';
import 'package:civin/features/rankings/data/repositories/ranking_repository_impl.dart';
import 'package:civin/features/rankings/domain/entities/ranking.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late _FakeRemote remote;
  late RankingRepositoryImpl repository;

  setUp(() {
    remote = _FakeRemote();
    repository = RankingRepositoryImpl(remote);
  });

  test('loads host rankings through remote datasource', () async {
    final RepositoryResult<List<RankingEntry>> result = await repository
        .getRankings(const RankingQuery(type: RankingType.host));

    expect(result, isA<RepositorySuccess<List<RankingEntry>>>());
    final List<RankingEntry> entries =
        (result as RepositorySuccess<List<RankingEntry>>).data;
    expect(entries, hasLength(3));
    expect(entries.first.rank, 1);
    expect(entries.first.user.displayName, 'Top Host');
    expect(entries.first.user.isVip, isTrue);
    expect(remote.lastQuery?.type, RankingType.host);
    expect(remote.lastQuery?.period, RankingPeriod.daily);
  });

  test('forwards period and country filters', () async {
    await repository.getRankings(
      const RankingQuery(
        type: RankingType.gifter,
        period: RankingPeriod.weekly,
        scope: RankingScope.country,
        country: 'TR',
        limit: 10,
      ),
    );

    expect(remote.lastQuery?.type, RankingType.gifter);
    expect(remote.lastQuery?.period, RankingPeriod.weekly);
    expect(remote.lastQuery?.effectiveCountry, 'TR');
    expect(remote.lastQuery?.limit, 10);
  });

  test('maps remote failures to repository failure', () async {
    remote.error = DioException(
      requestOptions: RequestOptions(path: '/api/v1/rankings/hosts'),
      response: Response<dynamic>(
        requestOptions: RequestOptions(path: '/api/v1/rankings/hosts'),
        statusCode: 422,
        data: <String, dynamic>{'message': 'Unsupported ranking period.'},
      ),
    );

    final RepositoryResult<List<RankingEntry>> failed = await repository
        .getRankings(
          const RankingQuery(
            type: RankingType.host,
            period: RankingPeriod.monthly,
          ),
        );

    expect(failed, isA<RepositoryFailure<List<RankingEntry>>>());
    expect(
      (failed as RepositoryFailure<List<RankingEntry>>).failure,
      isA<NetworkFailure>(),
    );
    expect(failed.failure.message, 'Unsupported ranking period.');
  });

  test('parses ranking entry json with nested country', () {
    final RankingEntry entry = RankingModel.entryFromJson(
      <String, dynamic>{
        'rank': 2,
        'score': 420,
        'user': <String, dynamic>{
          'id': 'u-2',
          'username': 'second',
          'nickname': 'Second Host',
          'avatar_url': null,
          'is_vip': false,
          'country': <String, dynamic>{
            'alpha2': 'US',
            'name': 'United States',
          },
        },
      },
    );

    expect(entry.rank, 2);
    expect(entry.score, 420);
    expect(entry.user.countryCode, 'US');
    expect(entry.user.countryName, 'United States');
  });
}

final class _FakeRemote implements RankingRemoteDataSource {
  Object? error;
  RankingQuery? lastQuery;

  @override
  Future<List<RankingEntry>> getRankings(RankingQuery query) async {
    lastQuery = query;
    if (error != null) throw error!;
    return const <RankingEntry>[
      RankingEntry(
        rank: 1,
        score: 900,
        user: RankingUser(
          id: 'u-1',
          nickname: 'Top Host',
          isVip: true,
          countryCode: 'TR',
        ),
      ),
      RankingEntry(
        rank: 2,
        score: 500,
        user: RankingUser(id: 'u-2', nickname: 'Second Host'),
      ),
      RankingEntry(
        rank: 3,
        score: 120,
        user: RankingUser(id: 'u-3', nickname: 'Third Host'),
      ),
    ];
  }
}
