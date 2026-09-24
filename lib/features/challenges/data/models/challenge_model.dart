import 'package:civin/core/utils/json_values.dart';
import 'package:civin/features/challenges/domain/entities/challenge.dart';

abstract final class ChallengeModel {
  static List<CivinChallenge> listFromJson(Object? data) =>
      JsonValues.asMapList(data).map(fromJson).toList(growable: false);

  static CivinChallenge fromJson(Map<String, dynamic> json) => CivinChallenge(
    id: JsonValues.asString(json['id']),
    title: JsonValues.asString(json['title'], fallback: 'Challenge'),
    description: JsonValues.asNullableString(json['description']),
    period: JsonValues.asString(json['period']),
    taskType: JsonValues.asString(json['task_type']),
    target: JsonValues.asInt(json['target'], fallback: 1),
    progress: JsonValues.asInt(json['progress']),
    rewardXp: JsonValues.asInt(json['reward_xp']),
    rewardCoins: JsonValues.asInt(json['reward_coins']),
    rewardDiamonds: JsonValues.asInt(json['reward_diamonds']),
    isCompleted: JsonValues.asBool(json['is_completed']),
    isClaimed: JsonValues.asBool(json['is_claimed']),
    canClaim: JsonValues.asBool(json['can_claim']),
  );

  static ChallengeClaim claimFromJson(Map<String, dynamic> json) {
    final Object? challenge = json['challenge'];
    return ChallengeClaim(
      claimed: JsonValues.asBool(json['claimed']),
      xpAwarded: JsonValues.asInt(json['xp_awarded']),
      coinsAwarded: JsonValues.asInt(json['coins_awarded']),
      diamondsAwarded: JsonValues.asInt(json['diamonds_awarded']),
      leveledUp: JsonValues.asBool(json['leveled_up']),
      challenge: challenge is Map
          ? fromJson(JsonValues.asStringDynamicMap(challenge))
          : null,
    );
  }
}
