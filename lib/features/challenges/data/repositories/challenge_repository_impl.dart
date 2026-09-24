import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/challenges/data/datasources/challenge_remote_data_source.dart';
import 'package:civin/features/challenges/domain/entities/challenge.dart';
import 'package:civin/features/challenges/domain/repositories/challenge_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<ChallengeRepository> challengeRepositoryProvider =
    Provider<ChallengeRepository>(
      (Ref ref) =>
          ChallengeRepositoryImpl(ref.watch(challengeRemoteDataSourceProvider)),
    );

final class ChallengeRepositoryImpl extends BaseRepository
    implements ChallengeRepository {
  ChallengeRepositoryImpl(this._remote);

  final ChallengeRemoteDataSource _remote;

  @override
  Future<RepositoryResult<List<CivinChallenge>>> list({String? period}) =>
      execute(() => _remote.list(period: period));

  @override
  Future<RepositoryResult<ChallengeClaim>> claim(String challengeId) =>
      execute(() => _remote.claim(challengeId));
}
