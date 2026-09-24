import 'package:civin/core/network/dio_client.dart';
import 'package:civin/core/utils/json_values.dart';
import 'package:civin/features/games/data/models/game_model.dart';
import 'package:civin/features/games/domain/entities/civin_game.dart';
import 'package:civin/features/games/domain/repositories/game_repository.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<GameRemoteDataSource> gameRemoteDataSourceProvider =
    Provider<GameRemoteDataSource>(
      (Ref ref) => DioGameRemoteDataSource(ref.watch(dioClientProvider)),
    );

abstract interface class GameRemoteDataSource {
  Future<List<CivinGame>> listGames();

  Future<CivinGame> getGame(String slug);

  Future<GameRoundResult> play(PlayGameCommand command);

  Future<List<GameRoundResult>> history({String? gameSlug, int perPage = 20});

  Future<List<GameLeaderEntry>> leaderboard({
    String? gameSlug,
    String period = 'daily',
  });

  Future<QuizPrompt> quizQuestion();

  Future<LotteryRoundInfo> currentLottery();

  Future<CivinMatch> createMatch({
    required String gameSlug,
    required int stakeCoins,
    int maxPlayers = 2,
    Map<String, Object?>? metadata,
  });

  Future<CivinMatch> joinMatch(String matchId);

  Future<CivinMatch> matchAction(
    String matchId, {
    required String action,
    Map<String, Object?> payload = const <String, Object?>{},
  });
}

final class DioGameRemoteDataSource implements GameRemoteDataSource {
  const DioGameRemoteDataSource(this._client);

  final DioClient _client;

  @override
  Future<List<CivinGame>> listGames() async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/games',
    );
    return GameModel.gamesFromJson(_body(response)['data']);
  }

  @override
  Future<CivinGame> getGame(String slug) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/games/$slug',
    );
    return GameModel.gameFromJson(_data(response));
  }

  @override
  Future<GameRoundResult> play(PlayGameCommand command) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/games/play',
      data: <String, dynamic>{
        'game_slug': command.gameSlug,
        'bet_coins': command.betCoins,
        'input': command.input,
        'metadata': ?command.metadata,
      },
    );
    return GameModel.roundFromPlayEnvelope(_data(response));
  }

  @override
  Future<List<GameRoundResult>> history({
    String? gameSlug,
    int perPage = 20,
  }) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/games/history',
      queryParameters: <String, dynamic>{
        'per_page': perPage,
        'game_slug': ?gameSlug,
      },
    );
    return JsonValues.asMapList(
      _body(response)['data'],
    ).map(GameModel.historyFromJson).toList(growable: false);
  }

  @override
  Future<List<GameLeaderEntry>> leaderboard({
    String? gameSlug,
    String period = 'daily',
  }) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/games/leaderboard',
      queryParameters: <String, dynamic>{
        'period': period,
        'game_slug': ?gameSlug,
      },
    );
    return GameModel.leadersFromJson(_body(response)['data']);
  }

  @override
  Future<QuizPrompt> quizQuestion() async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/games/quiz/question',
    );
    return GameModel.quizFromJson(_data(response));
  }

  @override
  Future<LotteryRoundInfo> currentLottery() async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/games/lottery/current',
    );
    return GameModel.lotteryFromJson(_data(response));
  }

  @override
  Future<CivinMatch> createMatch({
    required String gameSlug,
    required int stakeCoins,
    int maxPlayers = 2,
    Map<String, Object?>? metadata,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/games/matches',
      data: <String, dynamic>{
        'game_slug': gameSlug,
        'stake_coins': stakeCoins,
        'max_players': maxPlayers,
        'metadata': ?metadata,
      },
    );
    return GameModel.matchFromJson(_data(response));
  }

  @override
  Future<CivinMatch> joinMatch(String matchId) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/games/matches/$matchId/join',
    );
    return GameModel.matchFromJson(_data(response));
  }

  @override
  Future<CivinMatch> matchAction(
    String matchId, {
    required String action,
    Map<String, Object?> payload = const <String, Object?>{},
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/games/matches/$matchId/action',
      data: <String, dynamic>{'action': action, 'payload': payload},
    );
    return GameModel.matchFromJson(_data(response));
  }

  Map<String, dynamic> _body(Response<dynamic> response) {
    final Object? data = response.data;
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid API envelope.');
  }

  Map<String, dynamic> _data(Response<dynamic> response) {
    final Object? data = _body(response)['data'];
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid API data payload.');
  }
}
