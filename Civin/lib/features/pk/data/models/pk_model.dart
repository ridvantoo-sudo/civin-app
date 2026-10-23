import 'package:civin/features/pk/domain/entities/pk_battle.dart';

abstract final class PkModel {
  static PkBattle battleFromJson(Map<String, dynamic> json) {
    final Object? scoresJson = json['scores'];
    final Object? rewardsJson = json['rewards'];
    final Object? hostAJson = json['host_a'];
    final Object? hostBJson = json['host_b'];
    final Object? winnerJson = json['winner'];

    return PkBattle(
      id: json['id'] as String,
      status: _status(json['status'] as String?),
      durationSeconds: _int(json['duration_seconds'], fallback: 180),
      roomAId: json['room_a_id'] as String? ?? '',
      roomBId: json['room_b_id'] as String? ?? '',
      hostAId: json['host_a_id'] as String? ?? '',
      hostBId: json['host_b_id'] as String? ?? '',
      winnerId: json['winner_id'] as String?,
      hostA: hostAJson is Map<String, dynamic> ? userFromJson(hostAJson) : null,
      hostB: hostBJson is Map<String, dynamic> ? userFromJson(hostBJson) : null,
      winner: winnerJson is Map<String, dynamic>
          ? userFromJson(winnerJson)
          : null,
      scores: scoresJson is List<dynamic>
          ? scoresJson
                .whereType<Map<String, dynamic>>()
                .map(scoreFromJson)
                .toList(growable: false)
          : const <PkScore>[],
      rewards: rewardsJson is List<dynamic>
          ? rewardsJson
                .whereType<Map<String, dynamic>>()
                .map(rewardFromJson)
                .toList(growable: false)
          : const <PkReward>[],
      startedAt: _date(json['started_at']),
      endedAt: _date(json['ended_at']),
      createdAt: _date(json['created_at']),
    );
  }

  static PkScore scoreFromJson(Map<String, dynamic> json) {
    final Object? userJson = json['user'];
    return PkScore(
      id: json['id'] as String? ?? '',
      battleId: json['pk_battle_id'] as String? ?? '',
      userId: json['user_id'] as String? ?? '',
      score: _int(json['score']),
      giftCoins: _int(json['gift_coins']),
      user: userJson is Map<String, dynamic> ? userFromJson(userJson) : null,
      updatedAt: _date(json['updated_at']),
    );
  }

  static PkReward rewardFromJson(Map<String, dynamic> json) {
    final Object? winnerJson = json['winner'];
    return PkReward(
      id: json['id'] as String? ?? '',
      battleId: json['pk_battle_id'] as String? ?? '',
      winnerId: json['winner_id'] as String? ?? '',
      rewardType: json['reward_type'] as String? ?? 'DIAMONDS',
      amount: _int(json['amount']),
      winner: winnerJson is Map<String, dynamic>
          ? userFromJson(winnerJson)
          : null,
      createdAt: _date(json['created_at']),
    );
  }

  static PkUser userFromJson(Map<String, dynamic> json) => PkUser(
    id: json['id']?.toString() ?? json['user_id']?.toString() ?? '',
    username: json['username'] as String? ?? 'user',
    nickname: json['nickname'] as String?,
    avatarUrl: json['avatar_url'] as String?,
  );

  static PkRealtimeEvent eventFromJson(
    PkRealtimeEventType type,
    Map<String, dynamic> json,
  ) {
    final Object? battleJson = json['battle'];
    if (battleJson is! Map<String, dynamic>) {
      throw const FormatException('Invalid PK event: missing battle.');
    }
    final Object? scoreJson = json['score'];
    return PkRealtimeEvent(
      type: type,
      battle: battleFromJson(battleJson),
      score: scoreJson is Map<String, dynamic>
          ? scoreFromJson(scoreJson)
          : null,
    );
  }

  static PkBattleStatus _status(String? value) => switch (value
      ?.toUpperCase()) {
    'WAITING' => PkBattleStatus.waiting,
    'RUNNING' => PkBattleStatus.running,
    'FINISHED' => PkBattleStatus.finished,
    'CANCELLED' => PkBattleStatus.cancelled,
    _ => PkBattleStatus.unknown,
  };

  static int _int(Object? value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }

  static DateTime? _date(Object? value) {
    if (value is! String || value.isEmpty) return null;
    return DateTime.tryParse(value);
  }
}
