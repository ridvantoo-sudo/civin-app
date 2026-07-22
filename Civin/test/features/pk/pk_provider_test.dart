import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/pk/data/repositories/pk_repository_impl.dart';
import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:civin/features/pk/domain/repositories/pk_repository.dart';
import 'package:civin/features/pk/presentation/pk_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('pkProvider listens for PKStarted and updates battle state', () async {
    final _FakePkRepository repository = _FakePkRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [pkRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container.read(pkProvider('room-a').notifier).startListening();
    expect(repository.connectedRoomId, 'room-a');
    expect(container.read(pkProvider('room-a')).isListening, isTrue);

    repository.emit(
      PkRealtimeEvent(
        type: PkRealtimeEventType.started,
        battle: _FakePkRepository.runningBattle,
      ),
    );
    await Future<void>.delayed(Duration.zero);

    final PkRoomState state = container.read(pkProvider('room-a'));
    expect(state.battle?.status, PkBattleStatus.running);
    expect(state.battle?.hostNameA, 'Alpha');
  });

  test('pkScoreProvider reflects score updates from realtime events', () async {
    final _FakePkRepository repository = _FakePkRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [pkRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container.read(pkProvider('room-a').notifier).startListening();
    repository.emit(
      PkRealtimeEvent(
        type: PkRealtimeEventType.scoreUpdated,
        battle: _FakePkRepository.scoredBattle,
        score: _FakePkRepository.scoredBattle.scores.first,
      ),
    );
    await Future<void>.delayed(Duration.zero);

    final PkScoreView score = container.read(pkScoreProvider('room-a'));
    expect(score.scoreA, 50);
    expect(score.scoreB, 20);
    expect(score.hostNameA, 'Alpha');
  });

  test('pkTimerProvider syncs remaining time when battle is running', () async {
    final _FakePkRepository repository = _FakePkRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [pkRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    // Touch providers so family notifiers are created.
    container.read(pkProvider('room-a'));
    container.read(pkTimerProvider('room-a'));

    await container.read(pkProvider('room-a').notifier).startListening();
    repository.emit(
      PkRealtimeEvent(
        type: PkRealtimeEventType.started,
        battle: _FakePkRepository.runningBattle,
      ),
    );
    await Future<void>.delayed(Duration.zero);

    final PkTimerState timer = container.read(pkTimerProvider('room-a'));
    expect(timer.durationSeconds, 120);
    expect(timer.isRunning, isTrue);
    expect(timer.remainingSeconds, greaterThan(0));
  });

  test('pkProvider requestPk stores waiting battle and surfaces failures', () async {
    final _FakePkRepository repository = _FakePkRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [pkRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    final bool ok = await container
        .read(pkProvider('room-a').notifier)
        .requestPk(opponentRoomId: 'room-b', durationSeconds: 90);
    expect(ok, isTrue);
    expect(container.read(pkProvider('room-a')).battle?.isWaiting, isTrue);
    expect(repository.lastOpponentRoomId, 'room-b');

    repository.failRequest = true;
    final bool failed = await container
        .read(pkProvider('room-a').notifier)
        .requestPk(opponentRoomId: 'room-c');
    expect(failed, isFalse);
    expect(
      container.read(pkProvider('room-a')).errorMessage,
      'Opponent unavailable',
    );
  });

  test('pkProvider startPk and endPk update battle lifecycle', () async {
    final _FakePkRepository repository = _FakePkRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [pkRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container
        .read(pkProvider('room-a').notifier)
        .requestPk(opponentRoomId: 'room-b');
    await container.read(pkProvider('room-a').notifier).acceptPk();
    final bool started = await container
        .read(pkProvider('room-a').notifier)
        .startPk();
    expect(started, isTrue);
    expect(container.read(pkProvider('room-a')).battle?.isRunning, isTrue);

    final bool ended = await container
        .read(pkProvider('room-a').notifier)
        .endPk();
    expect(ended, isTrue);
    expect(container.read(pkProvider('room-a')).battle?.isFinished, isTrue);
    expect(container.read(pkProvider('room-a')).showResult, isTrue);
  });
}

final class _FakePkRepository implements PkRepository {
  // ignore: close_sinks
  final StreamController<PkRealtimeEvent> _events =
      StreamController<PkRealtimeEvent>.broadcast();

  String? connectedRoomId;
  String? lastOpponentRoomId;
  bool failRequest = false;

  static const PkUser hostA = PkUser(
    id: 'host-a',
    username: 'alpha',
    nickname: 'Alpha',
  );
  static const PkUser hostB = PkUser(
    id: 'host-b',
    username: 'beta',
    nickname: 'Beta',
  );

  static final PkBattle waitingBattle = PkBattle(
    id: 'battle-1',
    status: PkBattleStatus.waiting,
    durationSeconds: 120,
    roomAId: 'room-a',
    roomBId: 'room-b',
    hostAId: 'host-a',
    hostBId: 'host-b',
    hostA: hostA,
    hostB: hostB,
  );

  static final PkBattle acceptedBattle = PkBattle(
    id: 'battle-1',
    status: PkBattleStatus.waiting,
    durationSeconds: 120,
    roomAId: 'room-a',
    roomBId: 'room-b',
    hostAId: 'host-a',
    hostBId: 'host-b',
    hostA: hostA,
    hostB: hostB,
    scores: const <PkScore>[
      PkScore(id: 's1', battleId: 'battle-1', userId: 'host-a', score: 0),
      PkScore(id: 's2', battleId: 'battle-1', userId: 'host-b', score: 0),
    ],
  );

  static final PkBattle runningBattle = PkBattle(
    id: 'battle-1',
    status: PkBattleStatus.running,
    durationSeconds: 120,
    roomAId: 'room-a',
    roomBId: 'room-b',
    hostAId: 'host-a',
    hostBId: 'host-b',
    hostA: hostA,
    hostB: hostB,
    startedAt: DateTime.now().toUtc().subtract(const Duration(seconds: 5)),
    scores: acceptedBattle.scores,
  );

  static final PkBattle scoredBattle = PkBattle(
    id: 'battle-1',
    status: PkBattleStatus.running,
    durationSeconds: 120,
    roomAId: 'room-a',
    roomBId: 'room-b',
    hostAId: 'host-a',
    hostBId: 'host-b',
    hostA: hostA,
    hostB: hostB,
    startedAt: DateTime.now().toUtc().subtract(const Duration(seconds: 5)),
    scores: const <PkScore>[
      PkScore(id: 's1', battleId: 'battle-1', userId: 'host-a', score: 50),
      PkScore(id: 's2', battleId: 'battle-1', userId: 'host-b', score: 20),
    ],
  );

  static final PkBattle finishedBattle = PkBattle(
    id: 'battle-1',
    status: PkBattleStatus.finished,
    durationSeconds: 120,
    roomAId: 'room-a',
    roomBId: 'room-b',
    hostAId: 'host-a',
    hostBId: 'host-b',
    hostA: hostA,
    hostB: hostB,
    winnerId: 'host-a',
    winner: hostA,
    scores: scoredBattle.scores,
    rewards: const <PkReward>[
      PkReward(
        id: 'r1',
        battleId: 'battle-1',
        winnerId: 'host-a',
        rewardType: 'DIAMONDS',
        amount: 50,
      ),
    ],
  );

  @override
  Future<RepositoryResult<PkBattle>> requestPk(
    String roomId, {
    required String opponentRoomId,
    int? durationSeconds,
  }) async {
    if (failRequest) {
      return const RepositoryFailure<PkBattle>(
        AppFailure.network(message: 'Opponent unavailable'),
      );
    }
    lastOpponentRoomId = opponentRoomId;
    return RepositorySuccess<PkBattle>(waitingBattle);
  }

  @override
  Future<RepositoryResult<PkBattle>> acceptPk(String roomId) async =>
      RepositorySuccess<PkBattle>(acceptedBattle);

  @override
  Future<RepositoryResult<PkBattle>> startPk(String battleId) async =>
      RepositorySuccess<PkBattle>(runningBattle);

  @override
  Future<RepositoryResult<PkBattle>> endPk(String battleId) async =>
      RepositorySuccess<PkBattle>(finishedBattle);

  @override
  Future<RepositoryResult<PkBattle>> getPk(String battleId) async =>
      RepositorySuccess<PkBattle>(waitingBattle);

  @override
  Stream<PkRealtimeEvent> watchPkEvents(String roomId) => _events.stream;

  @override
  Future<void> connect(String roomId) async {
    connectedRoomId = roomId;
  }

  @override
  Future<void> disconnect() async {
    connectedRoomId = null;
  }

  void emit(PkRealtimeEvent event) => _events.add(event);
}
