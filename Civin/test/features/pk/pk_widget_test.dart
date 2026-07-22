import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/domain/repositories/live_repository.dart';
import 'package:civin/features/live/domain/usecases/live_usecases.dart';
import 'package:civin/features/live/presentation/live_providers.dart';
import 'package:civin/features/pk/data/repositories/pk_repository_impl.dart';
import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:civin/features/pk/domain/repositories/pk_repository.dart';
import 'package:civin/features/pk/presentation/pk_providers.dart';
import 'package:civin/features/pk/presentation/screens/pk_battle_screen.dart';
import 'package:civin/features/pk/presentation/screens/pk_request_dialog.dart';
import 'package:civin/features/pk/presentation/screens/pk_result_screen.dart';
import 'package:civin/features/pk/presentation/widgets/pk_score_bar.dart';
import 'package:civin/features/pk/presentation/widgets/pk_timer_badge.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('PK request dialog shows send and accept actions', (
    WidgetTester tester,
  ) async {
    final _FakePkRepository repository = _FakePkRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          pkRepositoryProvider.overrideWithValue(repository),
          browseLiveRoomsProvider.overrideWithValue(
            BrowseLiveRooms(_FakeLiveRepository()),
          ),
        ],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData(
            useMaterial3: true,
            brightness: Brightness.dark,
            colorScheme: ColorScheme.fromSeed(
              seedColor: const Color(0xFF6C4DFF),
              brightness: Brightness.dark,
            ),
          ),
          home: const Scaffold(body: PkRequestDialog(roomId: 'room-a')),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('PK Battle'), findsOneWidget);
    expect(find.text('Send request'), findsOneWidget);
    expect(find.text('Accept PK'), findsOneWidget);
    expect(find.text('Opponent room ID'), findsOneWidget);
    expect(find.text('Rival'), findsOneWidget);

    await tester.enterText(find.byType(TextField), 'room-b');
    await tester.tap(find.text('Send request'));
    await tester.pumpAndSettle();

    expect(repository.lastOpponentRoomId, 'room-b');
  });

  testWidgets('PK battle screen shows hosts, scores, and timer', (
    WidgetTester tester,
  ) async {
    final _FakePkRepository repository = _FakePkRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [pkRepositoryProvider.overrideWithValue(repository)],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData.dark(useMaterial3: true),
          home: const Scaffold(body: PkBattleScreen(roomId: 'room-a')),
        ),
      ),
    );

    final ProviderContainer container = ProviderScope.containerOf(
      tester.element(find.byType(PkBattleScreen)),
    );
    await container.read(pkProvider('room-a').notifier).startListening();
    repository.emit(
      PkRealtimeEvent(
        type: PkRealtimeEventType.started,
        battle: _FakePkRepository.runningBattle,
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text('Host A'), findsOneWidget);
    expect(find.text('Host B'), findsOneWidget);
    expect(find.text('Alpha'), findsWidgets);
    expect(find.text('Beta'), findsWidgets);
    expect(find.text('PK BATTLE'), findsOneWidget);
    expect(find.byType(PkScoreBar), findsOneWidget);
    expect(find.byType(PkTimerBadge), findsOneWidget);
  });

  testWidgets('PK result screen shows winner animation and continue', (
    WidgetTester tester,
  ) async {
    final _FakePkRepository repository = _FakePkRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [pkRepositoryProvider.overrideWithValue(repository)],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData.dark(useMaterial3: true),
          home: const Scaffold(body: PkResultScreen(roomId: 'room-a')),
        ),
      ),
    );

    final ProviderContainer container = ProviderScope.containerOf(
      tester.element(find.byType(PkResultScreen)),
    );
    await container.read(pkProvider('room-a').notifier).startListening();
    repository.emit(
      PkRealtimeEvent(
        type: PkRealtimeEventType.finished,
        battle: _FakePkRepository.finishedBattle,
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));

    expect(find.text('Winner'), findsOneWidget);
    expect(find.textContaining('Alpha wins'), findsOneWidget);
    expect(find.text('+50 diamonds'), findsOneWidget);
    expect(find.text('Continue'), findsOneWidget);

    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();
    expect(container.read(pkProvider('room-a')).showResult, isFalse);
  });
}

final class _FakeLiveRepository implements LiveRepository {
  @override
  Future<RepositoryResult<List<LiveRoom>>> getLiveRooms() async =>
      const RepositorySuccess<List<LiveRoom>>(
        <LiveRoom>[
          LiveRoom(
            id: 'room-b',
            title: 'Rival live',
            hostName: 'Rival',
            viewerCount: 12,
            isLive: true,
          ),
        ],
      );

  @override
  Future<RepositoryResult<List<LiveCategory>>> getCategories() async =>
      const RepositorySuccess<List<LiveCategory>>(<LiveCategory>[]);

  @override
  Future<RepositoryResult<LiveRoom>> createLiveRoom({
    required String title,
    required int categoryId,
    String? description,
  }) => throw UnimplementedError();

  @override
  Future<RepositoryResult<LiveConnection>> startStream(String roomId) =>
      throw UnimplementedError();

  @override
  Future<RepositoryResult<LiveConnection>> joinRoom(String roomId) =>
      throw UnimplementedError();

  @override
  Future<RepositoryResult<void>> leaveRoom(String roomId) =>
      throw UnimplementedError();

  @override
  Future<RepositoryResult<void>> endStream(String roomId) =>
      throw UnimplementedError();
}

final class _FakePkRepository implements PkRepository {
  // ignore: close_sinks
  final StreamController<PkRealtimeEvent> _events =
      StreamController<PkRealtimeEvent>.broadcast();

  String? lastOpponentRoomId;

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
    startedAt: DateTime.now().toUtc().subtract(const Duration(seconds: 3)),
    scores: const <PkScore>[
      PkScore(id: 's1', battleId: 'battle-1', userId: 'host-a', score: 12),
      PkScore(id: 's2', battleId: 'battle-1', userId: 'host-b', score: 8),
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
    scores: const <PkScore>[
      PkScore(id: 's1', battleId: 'battle-1', userId: 'host-a', score: 50),
      PkScore(id: 's2', battleId: 'battle-1', userId: 'host-b', score: 20),
    ],
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
    lastOpponentRoomId = opponentRoomId;
    return RepositorySuccess<PkBattle>(
      PkBattle(
        id: 'battle-1',
        status: PkBattleStatus.waiting,
        durationSeconds: durationSeconds ?? 180,
        roomAId: roomId,
        roomBId: opponentRoomId,
        hostAId: 'host-a',
        hostBId: 'host-b',
        hostA: hostA,
        hostB: hostB,
      ),
    );
  }

  @override
  Future<RepositoryResult<PkBattle>> acceptPk(String roomId) async =>
      RepositorySuccess<PkBattle>(runningBattle);

  @override
  Future<RepositoryResult<PkBattle>> startPk(String battleId) async =>
      RepositorySuccess<PkBattle>(runningBattle);

  @override
  Future<RepositoryResult<PkBattle>> endPk(String battleId) async =>
      RepositorySuccess<PkBattle>(finishedBattle);

  @override
  Future<RepositoryResult<PkBattle>> getPk(String battleId) async =>
      RepositorySuccess<PkBattle>(runningBattle);

  @override
  Stream<PkRealtimeEvent> watchPkEvents(String roomId) => _events.stream;

  @override
  Future<void> connect(String roomId) async {}

  @override
  Future<void> disconnect() async {}

  void emit(PkRealtimeEvent event) => _events.add(event);
}
