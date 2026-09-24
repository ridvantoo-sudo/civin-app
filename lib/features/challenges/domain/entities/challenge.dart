final class CivinChallenge {
  const CivinChallenge({
    required this.id,
    required this.title,
    required this.period,
    required this.taskType,
    required this.target,
    required this.progress,
    required this.rewardXp,
    required this.rewardCoins,
    required this.rewardDiamonds,
    required this.isCompleted,
    required this.isClaimed,
    required this.canClaim,
    this.description,
  });

  final String id;
  final String title;
  final String? description;
  final String period;
  final String taskType;
  final int target;
  final int progress;
  final int rewardXp;
  final int rewardCoins;
  final int rewardDiamonds;
  final bool isCompleted;
  final bool isClaimed;
  final bool canClaim;

  double get fraction => target <= 0 ? 0 : (progress / target).clamp(0, 1);
}

final class ChallengeClaim {
  const ChallengeClaim({
    required this.claimed,
    required this.xpAwarded,
    required this.coinsAwarded,
    required this.diamondsAwarded,
    required this.leveledUp,
    this.challenge,
  });

  final bool claimed;
  final int xpAwarded;
  final int coinsAwarded;
  final int diamondsAwarded;
  final bool leveledUp;
  final CivinChallenge? challenge;
}
