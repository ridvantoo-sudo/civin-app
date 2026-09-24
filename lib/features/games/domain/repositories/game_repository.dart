import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/games/domain/entities/civin_game.dart';

final class PlayGameCommand {
  const PlayGameCommand({
    required this.gameSlug,
    required this.betCoins,
    this.input = const <String, Object?>{},
    this.metadata,
  });

  final String gameSlug;
  final int betCoins;
  final Map<String, Object?> input;
  final Map<String, Object?>? metadata;
}

abstract interface class GameRepository {
  Future<RepositoryResult<List<CivinGame>>> listGames();

  Future<RepositoryResult<CivinGame>> getGame(String slug);

  Future<RepositoryResult<GameRoundResult>> play(PlayGameCommand command);

  Future<RepositoryResult<List<GameRoundResult>>> history({
    String? gameSlug,
    int perPage = 20,
  });

  Future<RepositoryResult<List<GameLeaderEntry>>> leaderboard({
    String? gameSlug,
    String period = 'daily',
  });

  Future<RepositoryResult<QuizPrompt>> quizQuestion();

  Future<RepositoryResult<LotteryRoundInfo>> currentLottery();

  Future<RepositoryResult<CivinMatch>> createMatch({
    required String gameSlug,
    required int stakeCoins,
    int maxPlayers = 2,
    Map<String, Object?>? metadata,
  });

  Future<RepositoryResult<CivinMatch>> joinMatch(String matchId);

  Future<RepositoryResult<CivinMatch>> matchAction(
    String matchId, {
    required String action,
    Map<String, Object?> payload = const <String, Object?>{},
  });
}
