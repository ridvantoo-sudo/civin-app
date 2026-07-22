import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/rankings/domain/entities/ranking.dart';
import 'package:civin/features/rankings/domain/repositories/ranking_repository.dart';

final class GetRankings {
  const GetRankings(this._repository);

  final RankingRepository _repository;

  Future<RepositoryResult<List<RankingEntry>>> call(RankingQuery query) =>
      _repository.getRankings(query);
}
