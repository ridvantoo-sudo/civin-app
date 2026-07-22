import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/rankings/data/datasources/ranking_remote_data_source.dart';
import 'package:civin/features/rankings/domain/entities/ranking.dart';
import 'package:civin/features/rankings/domain/repositories/ranking_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<RankingRepository> rankingRepositoryProvider =
    Provider<RankingRepository>(
      (Ref ref) => RankingRepositoryImpl(
        ref.watch(rankingRemoteDataSourceProvider),
      ),
    );

final class RankingRepositoryImpl extends BaseRepository
    implements RankingRepository {
  RankingRepositoryImpl(this._remote);

  final RankingRemoteDataSource _remote;

  @override
  Future<RepositoryResult<List<RankingEntry>>> getRankings(
    RankingQuery query,
  ) => execute(() => _remote.getRankings(query));
}
