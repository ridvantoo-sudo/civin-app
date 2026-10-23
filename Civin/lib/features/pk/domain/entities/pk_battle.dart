enum PkBattleStatus { waiting, running, finished, cancelled, unknown }

enum PkRealtimeEventType { started, scoreUpdated, finished }

final class PkUser {
  const PkUser({
    required this.id,
    required this.username,
    this.nickname,
    this.avatarUrl,
  });

  final String id;
  final String username;
  final String? nickname;
  final String? avatarUrl;

  String get displayName {
    final String? nick = nickname?.trim();
    if (nick != null && nick.isNotEmpty) return nick;
    return username;
  }
}

final class PkScore {
  const PkScore({
    required this.id,
    required this.battleId,
    required this.userId,
    required this.score,
    this.giftCoins = 0,
    this.user,
    this.updatedAt,
  });

  final String id;
  final String battleId;
  final String userId;
  final int score;
  final int giftCoins;
  final PkUser? user;
  final DateTime? updatedAt;

  PkScore copyWith({int? score, int? giftCoins, PkUser? user}) => PkScore(
    id: id,
    battleId: battleId,
    userId: userId,
    score: score ?? this.score,
    giftCoins: giftCoins ?? this.giftCoins,
    user: user ?? this.user,
    updatedAt: updatedAt,
  );
}

final class PkReward {
  const PkReward({
    required this.id,
    required this.battleId,
    required this.winnerId,
    required this.rewardType,
    required this.amount,
    this.winner,
    this.createdAt,
  });

  final String id;
  final String battleId;
  final String winnerId;
  final String rewardType;
  final int amount;
  final PkUser? winner;
  final DateTime? createdAt;
}

final class PkBattle {
  const PkBattle({
    required this.id,
    required this.status,
    required this.durationSeconds,
    required this.roomAId,
    required this.roomBId,
    required this.hostAId,
    required this.hostBId,
    this.winnerId,
    this.hostA,
    this.hostB,
    this.winner,
    this.scores = const <PkScore>[],
    this.rewards = const <PkReward>[],
    this.startedAt,
    this.endedAt,
    this.createdAt,
  });

  final String id;
  final PkBattleStatus status;
  final int durationSeconds;
  final String roomAId;
  final String roomBId;
  final String hostAId;
  final String hostBId;
  final String? winnerId;
  final PkUser? hostA;
  final PkUser? hostB;
  final PkUser? winner;
  final List<PkScore> scores;
  final List<PkReward> rewards;
  final DateTime? startedAt;
  final DateTime? endedAt;
  final DateTime? createdAt;

  bool get isWaiting => status == PkBattleStatus.waiting;
  bool get isRunning => status == PkBattleStatus.running;
  bool get isFinished => status == PkBattleStatus.finished;

  PkScore? scoreForUser(String userId) {
    for (final PkScore score in scores) {
      if (score.userId == userId) return score;
    }
    return null;
  }

  int get scoreA => scoreForUser(hostAId)?.score ?? 0;
  int get scoreB => scoreForUser(hostBId)?.score ?? 0;

  String get hostNameA => hostA?.displayName ?? 'Host A';
  String get hostNameB => hostB?.displayName ?? 'Host B';

  PkBattle copyWith({
    PkBattleStatus? status,
    String? winnerId,
    PkUser? winner,
    List<PkScore>? scores,
    List<PkReward>? rewards,
    DateTime? startedAt,
    DateTime? endedAt,
  }) => PkBattle(
    id: id,
    status: status ?? this.status,
    durationSeconds: durationSeconds,
    roomAId: roomAId,
    roomBId: roomBId,
    hostAId: hostAId,
    hostBId: hostBId,
    winnerId: winnerId ?? this.winnerId,
    hostA: hostA,
    hostB: hostB,
    winner: winner ?? this.winner,
    scores: scores ?? this.scores,
    rewards: rewards ?? this.rewards,
    startedAt: startedAt ?? this.startedAt,
    endedAt: endedAt ?? this.endedAt,
    createdAt: createdAt,
  );
}

/// Realtime envelope for PKStarted / PKScoreUpdated / PKFinished.
final class PkRealtimeEvent {
  const PkRealtimeEvent({
    required this.type,
    required this.battle,
    this.score,
  });

  final PkRealtimeEventType type;
  final PkBattle battle;
  final PkScore? score;
}

final class PkScoreView {
  const PkScoreView({
    this.scoreA = 0,
    this.scoreB = 0,
    this.hostNameA = 'Host A',
    this.hostNameB = 'Host B',
    this.hostAvatarA,
    this.hostAvatarB,
    this.battleId,
  });

  final int scoreA;
  final int scoreB;
  final String hostNameA;
  final String hostNameB;
  final String? hostAvatarA;
  final String? hostAvatarB;
  final String? battleId;

  int get total => scoreA + scoreB;

  double get ratioA {
    if (total <= 0) return 0.5;
    return scoreA / total;
  }

  double get ratioB => 1 - ratioA;
}

final class PkTimerState {
  const PkTimerState({
    this.remainingSeconds = 0,
    this.durationSeconds = 0,
    this.isRunning = false,
    this.isFinished = false,
  });

  final int remainingSeconds;
  final int durationSeconds;
  final bool isRunning;
  final bool isFinished;

  String get formatted {
    final int safe = remainingSeconds < 0 ? 0 : remainingSeconds;
    final int minutes = safe ~/ 60;
    final int seconds = safe % 60;
    final String mm = minutes.toString().padLeft(2, '0');
    final String ss = seconds.toString().padLeft(2, '0');
    return '$mm:$ss';
  }

  PkTimerState copyWith({
    int? remainingSeconds,
    int? durationSeconds,
    bool? isRunning,
    bool? isFinished,
  }) => PkTimerState(
    remainingSeconds: remainingSeconds ?? this.remainingSeconds,
    durationSeconds: durationSeconds ?? this.durationSeconds,
    isRunning: isRunning ?? this.isRunning,
    isFinished: isFinished ?? this.isFinished,
  );
}

final class PkRoomState {
  const PkRoomState({
    this.roomId,
    this.battle,
    this.isListening = false,
    this.isBusy = false,
    this.showResult = false,
    this.incomingRequest = false,
    this.errorMessage,
  });

  final String? roomId;
  final PkBattle? battle;
  final bool isListening;
  final bool isBusy;
  final bool showResult;
  final bool incomingRequest;
  final String? errorMessage;

  bool get hasActiveBattle {
    final PkBattle? current = battle;
    if (current == null) return false;
    return current.isWaiting || current.isRunning;
  }

  bool get isBattleVisible {
    final PkBattle? current = battle;
    if (current == null) return false;
    return current.isRunning || (current.isFinished && showResult);
  }

  PkRoomState copyWith({
    String? roomId,
    PkBattle? battle,
    bool? isListening,
    bool? isBusy,
    bool? showResult,
    bool? incomingRequest,
    String? errorMessage,
    bool clearBattle = false,
    bool clearError = false,
  }) => PkRoomState(
    roomId: roomId ?? this.roomId,
    battle: clearBattle ? null : battle ?? this.battle,
    isListening: isListening ?? this.isListening,
    isBusy: isBusy ?? this.isBusy,
    showResult: showResult ?? this.showResult,
    incomingRequest: incomingRequest ?? this.incomingRequest,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
  );
}
