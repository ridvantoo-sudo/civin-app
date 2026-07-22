import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/pk/data/datasources/pk_realtime_data_source.dart';
import 'package:civin/features/pk/data/datasources/pk_remote_data_source.dart';
import 'package:civin/features/pk/data/repositories/pk_repository_impl.dart';
import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late _FakeRemote remote;
  late _FakeRealtime realtime;
  late PkRepositoryImpl repository;

  setUp(() {
    remote = _FakeRemote();
    realtime = _FakeRealtime();
    repository = PkRepositoryImpl(remote, realtime);
  });

  test('requests, accepts, starts, and ends PK through remote datasource', () async {
    final RepositoryResult<PkBattle> requested = await repository.requestPk(
      'room-a',
      opponentRoomId: 'room-b',
      durationSeconds: 120,
    );
    expect(requested, isA<RepositorySuccess<PkBattle>>());
    expect(
      (requested as RepositorySuccess<PkBattle>).data.status,
      PkBattleStatus.waiting,
    );
    expect(remote.lastOpponentRoomId, 'room-b');
    expect(remote.lastDuration, 120);

    final RepositoryResult<PkBattle> accepted = await repository.acceptPk(
      'room-b',
    );
    expect(accepted, isA<RepositorySuccess<PkBattle>>());
    expect(
      (accepted as RepositorySuccess<PkBattle>).data.scores,
      hasLength(2),
    );

    final RepositoryResult<PkBattle> started = await repository.startPk(
      'battle-1',
    );
    expect(started, isA<RepositorySuccess<PkBattle>>());
    expect(
      (started as RepositorySuccess<PkBattle>).data.status,
      PkBattleStatus.running,
    );

    final RepositoryResult<PkBattle> ended = await repository.endPk('battle-1');
    expect(ended, isA<RepositorySuccess<PkBattle>>());
    expect(
      (ended as RepositorySuccess<PkBattle>).data.status,
      PkBattleStatus.finished,
    );
  });

  test('loads battle and maps remote failures', () async {
    final RepositoryResult<PkBattle> shown = await repository.getPk('battle-1');
    expect(shown, isA<RepositorySuccess<PkBattle>>());
    expect((shown as RepositorySuccess<PkBattle>).data.id, 'battle-1');

    remote.error = DioException(
      requestOptions: RequestOptions(path: '/api/v1/pk/battle-1/start'),
      response: Response<dynamic>(
        requestOptions: RequestOptions(path: '/api/v1/pk/battle-1/start'),
        statusCode: 422,
        data: <String, dynamic>{'message': 'Battle is not ready'},
      ),
    );

    final RepositoryResult<PkBattle> failed = await repository.startPk(
      'battle-1',
    );
    expect(failed, isA<RepositoryFailure<PkBattle>>());
    expect(
      (failed as RepositoryFailure<PkBattle>).failure,
      isA<NetworkFailure>(),
    );
    expect(failed.failure.message, 'Battle is not ready');
  });

  test('connects realtime channel and exposes PKStarted stream', () async {
    await repository.connect('room-a');
    expect(realtime.connectedRoomId, 'room-a');

    final Future<void> expectation = expectLater(
      repository.watchPkEvents('room-a'),
      emits(
        isA<PkRealtimeEvent>().having(
          (PkRealtimeEvent e) => e.type,
          'type',
          PkRealtimeEventType.started,
        ),
      ),
    );
    realtime.emit(
      PkRealtimeEvent(
        type: PkRealtimeEventType.started,
        battle: _FakeRemote.runningBattle,
      ),
    );
    await expectation;
  });
}

final class _FakeRemote implements PkRemoteDataSource {
  Object? error;
  String? lastOpponentRoomId;
  int? lastDuration;

  static const PkUser hostA = PkUser(id: 'host-a', username: 'alpha');
  static const PkUser hostB = PkUser(id: 'host-b', username: 'beta');

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
      PkScore(
        id: 's1',
        battleId: 'battle-1',
        userId: 'host-a',
        score: 0,
      ),
      PkScore(
        id: 's2',
        battleId: 'battle-1',
        userId: 'host-b',
        score: 0,
      ),
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
    startedAt: DateTime.utc(2026, 7, 22, 12),
    scores: acceptedBattle.scores,
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
    scores: const <PkScore>[
      PkScore(
        id: 's1',
        battleId: 'battle-1',
        userId: 'host-a',
        score: 40,
      ),
      PkScore(
        id: 's2',
        battleId: 'battle-1',
        userId: 'host-b',
        score: 10,
      ),
    ],
  );

  @override
  Future<PkBattle> requestPk(
    String roomId, {
    required String opponentRoomId,
    int? durationSeconds,
  }) async {
    _throwIfNeeded();
    lastOpponentRoomId = opponentRoomId;
    lastDuration = durationSeconds;
    return waitingBattle;
  }

  @override
  Future<PkBattle> acceptPk(String roomId) async {
    _throwIfNeeded();
    return acceptedBattle;
  }

  @override
  Future<PkBattle> startPk(String battleId) async {
    _throwIfNeeded();
    return runningBattle;
  }

  @override
  Future<PkBattle> endPk(String battleId) async {
    _throwIfNeeded();
    return finishedBattle;
  }

  @override
  Future<PkBattle> getPk(String battleId) async {
    _throwIfNeeded();
    return waitingBattle;
  }

  void _throwIfNeeded() {
    final Object? current = error;
    if (current != null) throw current;
  }
}

final class _FakeRealtime implements PkRealtimeDataSource {
  // ignore: close_sinks
  final StreamController<PkRealtimeEvent> _events =
      StreamController<PkRealtimeEvent>.broadcast();

  String? connectedRoomId;

  @override
  Stream<PkRealtimeEvent> get events => _events.stream;

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
