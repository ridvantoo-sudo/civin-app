import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/challenges/domain/entities/challenge.dart';
import 'package:civin/features/challenges/domain/repositories/challenge_repository.dart';

final class ListChallenges {
  const ListChallenges(this._repository);

  final ChallengeRepository _repository;

  Future<RepositoryResult<List<CivinChallenge>>> call({String? period}) =>
      _repository.list(period: period);
}

final class ClaimChallenge {
  const ClaimChallenge(this._repository);

  final ChallengeRepository _repository;

  Future<RepositoryResult<ChallengeClaim>> call(String challengeId) =>
      _repository.claim(challengeId);
}
