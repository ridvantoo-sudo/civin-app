import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/games/domain/entities/civin_game.dart';
import 'package:civin/features/games/domain/repositories/game_repository.dart';

final class ListGames {
  const ListGames(this._repository);

  final GameRepository _repository;

  Future<RepositoryResult<List<CivinGame>>> call() => _repository.listGames();
}

final class GetGame {
  const GetGame(this._repository);

  final GameRepository _repository;

  Future<RepositoryResult<CivinGame>> call(String slug) =>
      _repository.getGame(slug);
}

final class PlayCivinGame {
  const PlayCivinGame(this._repository);

  final GameRepository _repository;

  Future<RepositoryResult<GameRoundResult>> call(PlayGameCommand command) =>
      _repository.play(command);
}

final class GetGameHistory {
  const GetGameHistory(this._repository);

  final GameRepository _repository;

  Future<RepositoryResult<List<GameRoundResult>>> call({
    String? gameSlug,
    int perPage = 20,
  }) => _repository.history(gameSlug: gameSlug, perPage: perPage);
}

final class GetGameLeaderboard {
  const GetGameLeaderboard(this._repository);

  final GameRepository _repository;

  Future<RepositoryResult<List<GameLeaderEntry>>> call({
    String? gameSlug,
    String period = 'daily',
  }) => _repository.leaderboard(gameSlug: gameSlug, period: period);
}

final class GetQuizQuestion {
  const GetQuizQuestion(this._repository);

  final GameRepository _repository;

  Future<RepositoryResult<QuizPrompt>> call() => _repository.quizQuestion();
}

final class GetCurrentLottery {
  const GetCurrentLottery(this._repository);

  final GameRepository _repository;

  Future<RepositoryResult<LotteryRoundInfo>> call() =>
      _repository.currentLottery();
}

final class CreateCivinMatch {
  const CreateCivinMatch(this._repository);

  final GameRepository _repository;

  Future<RepositoryResult<CivinMatch>> call({
    required String gameSlug,
    required int stakeCoins,
    int maxPlayers = 2,
    Map<String, Object?>? metadata,
  }) => _repository.createMatch(
    gameSlug: gameSlug,
    stakeCoins: stakeCoins,
    maxPlayers: maxPlayers,
    metadata: metadata,
  );
}

final class JoinCivinMatch {
  const JoinCivinMatch(this._repository);

  final GameRepository _repository;

  Future<RepositoryResult<CivinMatch>> call(String matchId) =>
      _repository.joinMatch(matchId);
}

final class ActOnCivinMatch {
  const ActOnCivinMatch(this._repository);

  final GameRepository _repository;

  Future<RepositoryResult<CivinMatch>> call(
    String matchId, {
    required String action,
    Map<String, Object?> payload = const <String, Object?>{},
  }) => _repository.matchAction(matchId, action: action, payload: payload);
}
