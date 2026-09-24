enum CivinGameKind {
  luckyWheel,
  luckyDice,
  luckyNumber,
  slotMachine,
  treasureBox,
  quiz,
  rockPaperScissors,
  redEnvelope,
  lottery,
  ludo,
  other;

  static CivinGameKind fromSlug(String slug) => switch (slug) {
    'lucky_wheel' => CivinGameKind.luckyWheel,
    'lucky_dice' => CivinGameKind.luckyDice,
    'lucky_number' => CivinGameKind.luckyNumber,
    'slot_machine' => CivinGameKind.slotMachine,
    'treasure_box' => CivinGameKind.treasureBox,
    'quiz' => CivinGameKind.quiz,
    'rock_paper_scissors' => CivinGameKind.rockPaperScissors,
    'red_envelope' => CivinGameKind.redEnvelope,
    'lottery' => CivinGameKind.lottery,
    'ludo' => CivinGameKind.ludo,
    _ => CivinGameKind.other,
  };

  bool get usesBet => switch (this) {
    CivinGameKind.lottery || CivinGameKind.ludo || CivinGameKind.quiz => false,
    _ => true,
  };
}

final class CivinGame {
  const CivinGame({
    required this.id,
    required this.slug,
    required this.name,
    required this.minBetCoins,
    required this.maxBetCoins,
    required this.supportsMultiplayer,
    required this.status,
    this.description,
    this.icon,
    this.category,
    this.config = const <String, Object?>{},
  });

  final String id;
  final String slug;
  final String name;
  final String? description;
  final String? icon;
  final String? category;
  final int minBetCoins;
  final int maxBetCoins;
  final bool supportsMultiplayer;
  final String status;
  final Map<String, Object?> config;

  CivinGameKind get kind => CivinGameKind.fromSlug(slug);

  int configInt(String key, {int fallback = 0}) {
    final Object? value = config[key];
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value) ?? fallback;
    return fallback;
  }

  List<String> get treasureTiers {
    final Object? tiers = config['tiers'];
    if (tiers is Map) {
      final List<String> keys = <String>[];
      for (final Object? key in tiers.keys) {
        if (key is String && key.isNotEmpty) keys.add(key);
      }
      if (keys.isNotEmpty) return List<String>.unmodifiable(keys);
    }
    return const <String>['bronze', 'silver', 'gold'];
  }
}

final class GameRoundResult {
  const GameRoundResult({
    required this.id,
    required this.betCoins,
    required this.rewardCoins,
    required this.rewardDiamonds,
    required this.result,
    required this.coinsBalance,
    required this.diamondsBalance,
    this.gameSlug,
    this.outcome = const <String, Object?>{},
    this.createdAt,
  });

  final String id;
  final String? gameSlug;
  final int betCoins;
  final int rewardCoins;
  final int rewardDiamonds;
  final String result;
  final int coinsBalance;
  final int diamondsBalance;
  final Map<String, Object?> outcome;
  final DateTime? createdAt;

  bool get won => result == 'won';

  String? get envelopeId {
    final Object? value = outcome['envelope_id'];
    if (value is String && value.isNotEmpty) return value;
    return null;
  }

  String get headline => switch (result) {
    'won' => 'You won $rewardCoins coins',
    'draw' => 'Draw · $rewardCoins coins back',
    _ => rewardCoins > 0 ? 'Reward $rewardCoins coins' : 'No win this round',
  };

  String get detail {
    final List<String> parts = <String>[];
    void add(String label, Object? value) {
      if (value == null) return;
      if (value is List) {
        if (value.isEmpty) return;
        parts.add('$label ${value.join(' · ')}');
        return;
      }
      parts.add('$label $value');
    }

    add('Landed on', outcome['label']);
    add('Roll', outcome['roll'] ?? outcome['dice']);
    add('Drawn', outcome['drawn']);
    add('Picked', outcome['picked']);
    add('Reels', outcome['reels']);
    add(
      'Multiplier',
      outcome['multiplier'] == null ? null : 'x${outcome['multiplier']}',
    );
    add('Numbers', outcome['numbers']);
    if (outcome['correct'] is bool) {
      parts.add(outcome['correct'] == true ? 'Correct answer' : 'Wrong answer');
    }
    final String? envelope = envelopeId;
    if (envelope != null) parts.add('Envelope $envelope');
    return parts.join(' · ');
  }
}

final class QuizPrompt {
  const QuizPrompt({
    required this.id,
    required this.question,
    required this.options,
    required this.betCoins,
    required this.rewardCoins,
    this.difficulty,
  });

  final String id;
  final String question;
  final List<String> options;
  final int betCoins;
  final int rewardCoins;
  final String? difficulty;
}

final class LotteryRoundInfo {
  const LotteryRoundInfo({
    required this.id,
    required this.ticketPrice,
    required this.maxNumber,
    required this.picks,
    required this.status,
    this.closesAt,
  });

  final String id;
  final int ticketPrice;
  final int maxNumber;
  final int picks;
  final String status;
  final DateTime? closesAt;
}

final class GameLeaderEntry {
  const GameLeaderEntry({
    required this.rank,
    required this.displayName,
    required this.score,
    required this.wins,
    this.username,
  });

  final int rank;
  final String displayName;
  final String? username;
  final int score;
  final int wins;
}

final class CivinMatchPlayer {
  const CivinMatchPlayer({
    required this.userId,
    required this.seat,
    required this.displayName,
    required this.score,
    required this.status,
  });

  final String userId;
  final int seat;
  final String displayName;
  final int score;
  final String status;
}

final class CivinMatch {
  const CivinMatch({
    required this.id,
    required this.status,
    required this.stakeCoins,
    required this.maxPlayers,
    required this.players,
    this.state = const <String, Object?>{},
  });

  final String id;
  final String status;
  final int stakeCoins;
  final int maxPlayers;
  final List<CivinMatchPlayer> players;
  final Map<String, Object?> state;

  String? get lastRollLabel {
    final Object? last = state['last_roll'];
    if (last is! Map) return null;
    final Object? dice = last['dice'];
    if (dice == null) return null;
    return 'Last roll $dice';
  }
}
