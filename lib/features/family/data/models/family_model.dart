import 'package:civin/core/utils/json_values.dart';
import 'package:civin/features/family/domain/entities/family_club.dart';

abstract final class FamilyModel {
  static FamilyClub? clubOrNull(Object? data) {
    if (data == null) return null;
    if (data is Map) return clubFromJson(JsonValues.asStringDynamicMap(data));
    return null;
  }

  static FamilyClub clubFromJson(Map<String, dynamic> json) {
    final Object? owner = json['owner'];
    return FamilyClub(
      id: JsonValues.asString(json['id']),
      name: JsonValues.asString(json['name'], fallback: 'Family'),
      slug: JsonValues.asNullableString(json['slug']),
      description: JsonValues.asNullableString(json['description']),
      badgeKey: JsonValues.asNullableString(json['badge_key']),
      level: JsonValues.asInt(json['level'], fallback: 1),
      membersCount: JsonValues.asInt(json['members_count']),
      maxMembers: JsonValues.asInt(json['max_members']),
      walletCoins: JsonValues.asInt(json['wallet_coins']),
      walletDiamonds: JsonValues.asInt(json['wallet_diamonds']),
      ownerName: owner is Map
          ? personFromJson(JsonValues.asStringDynamicMap(owner)).label
          : null,
    );
  }

  static FamilyPerson personFromJson(Map<String, dynamic> json) {
    final String? nickname = JsonValues.asNullableString(json['nickname']);
    final String username = JsonValues.asString(
      json['username'],
      fallback: 'member',
    );
    return FamilyPerson(
      id: JsonValues.asString(json['id']),
      label: nickname ?? username,
    );
  }

  static FamilyMemberView memberFromJson(Map<String, dynamic> json) {
    final Object? user = json['user'];
    return FamilyMemberView(
      id: JsonValues.asString(json['id']),
      role: JsonValues.asString(json['role'], fallback: 'member'),
      status: JsonValues.asString(json['status']),
      contributionXp: JsonValues.asInt(json['contribution_xp']),
      user: user is Map
          ? personFromJson(JsonValues.asStringDynamicMap(user))
          : null,
    );
  }

  static FamilyMessageView messageFromJson(Map<String, dynamic> json) {
    final Object? user = json['user'];
    return FamilyMessageView(
      id: JsonValues.asString(json['id']),
      body: JsonValues.asString(json['body']),
      createdAt: JsonValues.asDate(json['created_at']),
      author: user is Map
          ? personFromJson(JsonValues.asStringDynamicMap(user))
          : null,
    );
  }

  static FamilyMissionView missionFromJson(Map<String, dynamic> json) =>
      FamilyMissionView(
        id: JsonValues.asString(json['id']),
        title: JsonValues.asString(json['title'], fallback: 'Mission'),
        description: JsonValues.asNullableString(json['description']),
        progress: JsonValues.asInt(json['progress']),
        targetValue: JsonValues.asInt(json['target_value'], fallback: 1),
        rewardXp: JsonValues.asInt(json['reward_xp']),
        rewardCoins: JsonValues.asInt(json['reward_coins']),
        isClaimable: JsonValues.asBool(json['is_claimable']),
        claimedAt: JsonValues.asDate(json['claimed_at']),
      );

  static FamilyInviteView inviteFromJson(Map<String, dynamic> json) =>
      FamilyInviteView(
        id: JsonValues.asString(json['id']),
        code: JsonValues.asString(json['code']),
        expiresAt: JsonValues.asDate(json['expires_at']),
      );
}
