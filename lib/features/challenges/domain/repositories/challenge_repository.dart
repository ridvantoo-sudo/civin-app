import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/challenges/domain/entities/challenge.dart';

abstract interface class ChallengeRepository {
  Future<RepositoryResult<List<CivinChallenge>>> list({String? period});

  Future<RepositoryResult<ChallengeClaim>> claim(String challengeId);
}
