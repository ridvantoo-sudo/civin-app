import 'package:civin/features/challenges/data/models/challenge_model.dart';
import 'package:civin/features/games/data/models/game_model.dart';
import 'package:civin/features/games/domain/entities/civin_game.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('parses a game catalog item and a settled round', () {
    final CivinGame game = GameModel.gameFromJson(<String, dynamic>{
      'id': '1',
      'slug': 'lucky_wheel',
      'name': 'Lucky Wheel',
      'description': 'Spin to win',
      'min_bet_coins': 10,
      'max_bet_coins': 500,
      'supports_multiplayer': false,
      'status': 'active',
      'config': <String, dynamic>{'segments': 5},
    });

    expect(game.kind, CivinGameKind.luckyWheel);
    expect(game.minBetCoins, 10);
    expect(game.configInt('segments'), 5);

    final GameRoundResult round = GameModel.roundFromPlayEnvelope(
      <String, dynamic>{
        'play': <String, dynamic>{
          'id': 'play-1',
          'bet_coins': 10,
          'reward_coins': 50,
          'reward_diamonds': 0,
          'result': 'won',
        },
        'outcome': <String, dynamic>{'label': 'x5', 'multiplier': 5},
        'wallet': <String, dynamic>{
          'coins_balance': 140,
          'diamonds_balance': 2,
        },
      },
    );

    expect(round.won, isTrue);
    expect(round.headline, 'You won 50 coins');
    expect(round.detail, contains('x5'));
    expect(round.coinsBalance, 140);
  });

  test('parses challenge progress and claim flags', () {
    final challenge = ChallengeModel.fromJson(<String, dynamic>{
      'id': 'abc',
      'title': 'Watch live',
      'period': 'daily',
      'task_type': 'watch',
      'target': 10,
      'progress': 4,
      'reward_xp': 20,
      'reward_coins': 15,
      'reward_diamonds': 0,
      'is_completed': false,
      'is_claimed': false,
      'can_claim': false,
    });

    expect(challenge.fraction, 0.4);
    expect(challenge.canClaim, isFalse);
  });
}
