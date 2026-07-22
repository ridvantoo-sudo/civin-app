import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/rankings/domain/entities/ranking.dart';

abstract interface class RankingRepository {
  Future<RepositoryResult<List<RankingEntry>>> getRankings(RankingQuery query);
}
