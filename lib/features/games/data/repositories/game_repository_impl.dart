import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/games/data/datasources/game_remote_data_source.dart';
import 'package:civin/features/games/domain/entities/civin_game.dart';
import 'package:civin/features/games/domain/repositories/game_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<GameRepository> gameRepositoryProvider =
    Provider<GameRepository>(
      (Ref ref) => GameRepositoryImpl(ref.watch(gameRemoteDataSourceProvider)),
    );

final class GameRepositoryImpl extends BaseRepository
    implements GameRepository {
  GameRepositoryImpl(this._remote);

  final GameRemoteDataSource _remote;

  @override
  Future<RepositoryResult<List<CivinGame>>> listGames() =>
      execute(_remote.listGames);

  @override
  Future<RepositoryResult<CivinGame>> getGame(String slug) =>
      execute(() => _remote.getGame(slug));

  @override
  Future<RepositoryResult<GameRoundResult>> play(PlayGameCommand command) =>
      execute(() => _remote.play(command));

  @override
  Future<RepositoryResult<List<GameRoundResult>>> history({
    String? gameSlug,
    int perPage = 20,
  }) => execute(() => _remote.history(gameSlug: gameSlug, perPage: perPage));

  @override
  Future<RepositoryResult<List<GameLeaderEntry>>> leaderboard({
    String? gameSlug,
    String period = 'daily',
  }) => execute(() => _remote.leaderboard(gameSlug: gameSlug, period: period));

  @override
  Future<RepositoryResult<QuizPrompt>> quizQuestion() =>
      execute(_remote.quizQuestion);

  @override
  Future<RepositoryResult<LotteryRoundInfo>> currentLottery() =>
      execute(_remote.currentLottery);

  @override
  Future<RepositoryResult<CivinMatch>> createMatch({
    required String gameSlug,
    required int stakeCoins,
    int maxPlayers = 2,
    Map<String, Object?>? metadata,
  }) => execute(
    () => _remote.createMatch(
      gameSlug: gameSlug,
      stakeCoins: stakeCoins,
      maxPlayers: maxPlayers,
      metadata: metadata,
    ),
  );

  @override
  Future<RepositoryResult<CivinMatch>> joinMatch(String matchId) =>
      execute(() => _remote.joinMatch(matchId));

  @override
  Future<RepositoryResult<CivinMatch>> matchAction(
    String matchId, {
    required String action,
    Map<String, Object?> payload = const <String, Object?>{},
  }) => execute(
    () => _remote.matchAction(matchId, action: action, payload: payload),
  );
}
