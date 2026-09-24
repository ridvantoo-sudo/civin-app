final class FamilyClub {
  const FamilyClub({
    required this.id,
    required this.name,
    required this.level,
    required this.membersCount,
    required this.maxMembers,
    required this.walletCoins,
    required this.walletDiamonds,
    this.slug,
    this.description,
    this.badgeKey,
    this.ownerName,
  });

  final String id;
  final String name;
  final String? slug;
  final String? description;
  final String? badgeKey;
  final int level;
  final int membersCount;
  final int maxMembers;
  final int walletCoins;
  final int walletDiamonds;
  final String? ownerName;
}

final class FamilyPerson {
  const FamilyPerson({required this.id, required this.label});

  final String id;
  final String label;
}

final class FamilyMemberView {
  const FamilyMemberView({
    required this.id,
    required this.role,
    required this.status,
    required this.contributionXp,
    this.user,
  });

  final String id;
  final String role;
  final String status;
  final int contributionXp;
  final FamilyPerson? user;
}

final class FamilyMessageView {
  const FamilyMessageView({
    required this.id,
    required this.body,
    this.author,
    this.createdAt,
  });

  final String id;
  final String body;
  final FamilyPerson? author;
  final DateTime? createdAt;
}

final class FamilyMissionView {
  const FamilyMissionView({
    required this.id,
    required this.title,
    required this.progress,
    required this.targetValue,
    required this.rewardXp,
    required this.rewardCoins,
    required this.isClaimable,
    this.description,
    this.claimedAt,
  });

  final String id;
  final String title;
  final String? description;
  final int progress;
  final int targetValue;
  final int rewardXp;
  final int rewardCoins;
  final bool isClaimable;
  final DateTime? claimedAt;

  double get fraction =>
      targetValue <= 0 ? 0 : (progress / targetValue).clamp(0, 1);
}

final class FamilyInviteView {
  const FamilyInviteView({
    required this.id,
    required this.code,
    this.expiresAt,
  });

  final String id;
  final String code;
  final DateTime? expiresAt;
}
