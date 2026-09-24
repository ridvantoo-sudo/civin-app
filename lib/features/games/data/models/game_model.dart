import 'package:civin/core/utils/json_values.dart';
import 'package:civin/features/games/domain/entities/civin_game.dart';

abstract final class GameModel {
  static List<CivinGame> gamesFromJson(Object? data) =>
      JsonValues.asMapList(data).map(gameFromJson).toList(growable: false);

  static CivinGame gameFromJson(Map<String, dynamic> json) => CivinGame(
    id: JsonValues.asString(json['id']),
    slug: JsonValues.asString(json['slug']),
    name: JsonValues.asString(json['name'], fallback: 'Game'),
    description: JsonValues.asNullableString(json['description']),
    icon: JsonValues.asNullableString(json['icon']),
    category: JsonValues.asNullableString(json['category']),
    minBetCoins: JsonValues.asInt(json['min_bet_coins']),
    maxBetCoins: JsonValues.asInt(json['max_bet_coins']),
    supportsMultiplayer: JsonValues.asBool(json['supports_multiplayer']),
    status: JsonValues.asString(json['status'], fallback: 'active'),
    config: JsonValues.asMap(json['config']),
  );

  static GameRoundResult roundFromPlayEnvelope(Map<String, dynamic> json) {
    final Map<String, dynamic> play = json['play'] is Map
        ? JsonValues.asStringDynamicMap(json['play'])
        : json;
    final Map<String, dynamic> wallet = json['wallet'] is Map
        ? JsonValues.asStringDynamicMap(json['wallet'])
        : const <String, dynamic>{};
    final Object? topOutcome = json['outcome'];
    return GameRoundResult(
      id: JsonValues.asString(play['id']),
      gameSlug: JsonValues.asNullableString(play['game_slug']),
      betCoins: JsonValues.asInt(play['bet_coins']),
      rewardCoins: JsonValues.asInt(play['reward_coins']),
      rewardDiamonds: JsonValues.asInt(play['reward_diamonds']),
      result: JsonValues.asString(play['result'], fallback: 'lost'),
      coinsBalance: JsonValues.asInt(wallet['coins_balance']),
      diamondsBalance: JsonValues.asInt(wallet['diamonds_balance']),
      outcome: topOutcome is Map
          ? JsonValues.asMap(topOutcome)
          : JsonValues.asMap(play['outcome']),
      createdAt: JsonValues.asDate(play['created_at']),
    );
  }

  static GameRoundResult historyFromJson(Map<String, dynamic> json) =>
      GameRoundResult(
        id: JsonValues.asString(json['id']),
        gameSlug: JsonValues.asNullableString(json['game_slug']),
        betCoins: JsonValues.asInt(json['bet_coins']),
        rewardCoins: JsonValues.asInt(json['reward_coins']),
        rewardDiamonds: JsonValues.asInt(json['reward_diamonds']),
        result: JsonValues.asString(json['result'], fallback: 'lost'),
        coinsBalance: 0,
        diamondsBalance: 0,
        outcome: JsonValues.asMap(json['outcome']),
        createdAt: JsonValues.asDate(json['created_at']),
      );

  static QuizPrompt quizFromJson(Map<String, dynamic> json) => QuizPrompt(
    id: JsonValues.asString(json['id']),
    question: JsonValues.asString(json['question']),
    options: JsonValues.asStringList(json['options']),
    betCoins: JsonValues.asInt(json['bet_coins']),
    rewardCoins: JsonValues.asInt(json['reward_coins']),
    difficulty: JsonValues.asNullableString(json['difficulty']),
  );

  static LotteryRoundInfo lotteryFromJson(Map<String, dynamic> json) =>
      LotteryRoundInfo(
        id: JsonValues.asString(json['id']),
        ticketPrice: JsonValues.asInt(json['ticket_price']),
        maxNumber: JsonValues.asInt(json['max_number'], fallback: 10),
        picks: JsonValues.asInt(json['picks'], fallback: 1),
        status: JsonValues.asString(json['status']),
        closesAt: JsonValues.asDate(json['closes_at']),
      );

  static List<GameLeaderEntry> leadersFromJson(Object? data) =>
      JsonValues.asMapList(data)
          .map((Map<String, dynamic> json) {
            final String? display = JsonValues.asNullableString(
              json['display_name'],
            );
            final String? username = JsonValues.asNullableString(
              json['username'],
            );
            return GameLeaderEntry(
              rank: JsonValues.asInt(json['rank']),
              displayName: display ?? username ?? 'Player',
              username: username,
              score: JsonValues.asInt(json['score']),
              wins: JsonValues.asInt(json['wins']),
            );
          })
          .toList(growable: false);

  static CivinMatch matchFromJson(Map<String, dynamic> json) {
    final Object? players = json['players'];
    return CivinMatch(
      id: JsonValues.asString(json['id']),
      status: JsonValues.asString(json['status']),
      stakeCoins: JsonValues.asInt(json['stake_coins']),
      maxPlayers: JsonValues.asInt(json['max_players'], fallback: 2),
      state: JsonValues.asMap(json['state']),
      players: _players(players),
    );
  }

  static List<CivinMatchPlayer> _players(Object? value) {
    if (value is! List<dynamic>) return const <CivinMatchPlayer>[];
    final List<CivinMatchPlayer> players = <CivinMatchPlayer>[];
    for (final dynamic item in value) {
      if (item is Map) {
        players.add(playerFromJson(JsonValues.asStringDynamicMap(item)));
      }
    }
    return List<CivinMatchPlayer>.unmodifiable(players);
  }

  static CivinMatchPlayer playerFromJson(Map<String, dynamic> json) =>
      CivinMatchPlayer(
        userId: JsonValues.asString(json['user_id']),
        seat: JsonValues.asInt(json['seat']),
        displayName:
            JsonValues.asNullableString(json['display_name']) ??
            JsonValues.asNullableString(json['username']) ??
            'Player',
        score: JsonValues.asInt(json['score']),
        status: JsonValues.asString(json['status']),
      );
}
