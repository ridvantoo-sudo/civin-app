import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/voice_rooms/data/datasources/voice_rtc_data_source.dart';
import 'package:civin/features/voice_rooms/data/repositories/voice_room_repository_impl.dart';
import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:civin/features/voice_rooms/domain/repositories/voice_room_repository.dart';
import 'package:civin/features/voice_rooms/presentation/screens/create_voice_room_screen.dart';
import 'package:civin/features/voice_rooms/presentation/screens/voice_room_home_screen.dart';
import 'package:civin/features/voice_rooms/presentation/screens/voice_room_screen.dart';
import 'package:civin/features/voice_rooms/presentation/widgets/voice_audience_count.dart';
import 'package:civin/features/voice_rooms/presentation/widgets/voice_mic_seats.dart';
import 'package:civin/features/voice_rooms/presentation/widgets/voice_room_header.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

void main() {
  testWidgets('VoiceRoomHome shows create and join actions', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          voiceRoomRepositoryProvider.overrideWithValue(_FakeVoiceRepository()),
          voiceRtcDataSourceProvider.overrideWithValue(_FakeRtc()),
        ],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData(
            useMaterial3: true,
            brightness: Brightness.dark,
            colorScheme: ColorScheme.fromSeed(
              seedColor: const Color(0xFF2DD4BF),
              brightness: Brightness.dark,
            ),
          ),
          home: const VoiceRoomHome(),
        ),
      ),
    );

    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text('Voice'), findsOneWidget);
    expect(find.text('Create'), findsOneWidget);
    expect(find.text('Join room'), findsOneWidget);
    expect(find.text('Paste room ID'), findsOneWidget);
  });

  testWidgets('CreateVoiceRoom validates title and creates room', (
    WidgetTester tester,
  ) async {
    final _FakeVoiceRepository repository = _FakeVoiceRepository();
    final GoRouter router = GoRouter(
      initialLocation: '/create',
      routes: <RouteBase>[
        GoRoute(
          path: '/create',
          builder: (BuildContext context, GoRouterState state) =>
              const CreateVoiceRoom(),
        ),
        GoRoute(
          path: '/voice/:roomId',
          builder: (BuildContext context, GoRouterState state) =>
              VoiceRoomScreen(roomId: state.pathParameters['roomId']!),
        ),
      ],
    );
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          voiceRoomRepositoryProvider.overrideWithValue(repository),
          voiceRtcDataSourceProvider.overrideWithValue(_FakeRtc()),
        ],
        child: MaterialApp.router(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData.dark(useMaterial3: true),
          routerConfig: router,
        ),
      ),
    );

    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.text('Create voice room'), findsOneWidget);
    expect(find.text('Start room'), findsOneWidget);

    await tester.tap(find.text('Start room'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));
    expect(find.textContaining('at least 2 characters'), findsOneWidget);

    await tester.enterText(find.byType(TextField).first, 'Night talk');
    await tester.tap(find.text('Start room'));
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(repository.lastCreatedTitle, 'Night talk');
  });

  testWidgets('VoiceRoomScreen shows seats, header, audience, and chat', (
    WidgetTester tester,
  ) async {
    final _FakeVoiceRepository repository = _FakeVoiceRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          voiceRoomRepositoryProvider.overrideWithValue(repository),
          voiceRtcDataSourceProvider.overrideWithValue(_FakeRtc()),
        ],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData.dark(useMaterial3: true),
          home: VoiceRoomScreen(
            roomId: 'room-1',
            connection: VoiceRoomConnection(
              room: _FakeVoiceRepository.liveRoom,
              rtc: _FakeVoiceRepository.rtc,
            ),
          ),
        ),
      ),
    );

    await tester.pump();
    await tester.pump(const Duration(milliseconds: 400));

    expect(find.byType(VoiceRoomHeader), findsOneWidget);
    expect(find.byType(VoiceAudienceCount), findsOneWidget);
    expect(find.byType(VoiceMicSeats), findsOneWidget);
    expect(find.text('Open mic'), findsOneWidget);
    expect(find.text('3 listening'), findsOneWidget);
    expect(find.text('Chat…'), findsOneWidget);
    expect(find.text('Host'), findsWidgets);
  });
}

final class _FakeRtc implements VoiceRtcDataSource {
  // ignore: close_sinks
  final StreamController<VoiceRtcEvent> _events =
      StreamController<VoiceRtcEvent>.broadcast();

  @override
  Stream<VoiceRtcEvent> get events => _events.stream;

  @override
  Future<void> join(
    VoiceRtcCredentials credentials, {
    required bool asSpeaker,
  }) async {
    _events.add(const VoiceRtcConnected());
  }

  @override
  Future<void> muteMicrophone(bool muted) async {}

  @override
  Future<void> leave() async {}

  @override
  Future<void> dispose() async {}
}

final class _FakeVoiceRepository implements VoiceRoomRepository {
  // ignore: close_sinks
  final StreamController<VoiceRealtimeEvent> _events =
      StreamController<VoiceRealtimeEvent>.broadcast();

  String? lastCreatedTitle;

  static const VoiceUser host = VoiceUser(
    id: 'host-1',
    username: 'host',
    nickname: 'Host',
  );

  static final VoiceRoom liveRoom = VoiceRoom(
    id: 'room-1',
    title: 'Open mic',
    status: VoiceRoomStatus.live,
    seatCount: 8,
    participantCount: 3,
    host: host,
    seats: const <VoiceSeat>[
      VoiceSeat(
        id: 's0',
        seatIndex: 0,
        status: VoiceSeatStatus.occupied,
        user: host,
      ),
      VoiceSeat(id: 's1', seatIndex: 1, status: VoiceSeatStatus.empty),
      VoiceSeat(id: 's2', seatIndex: 2, status: VoiceSeatStatus.empty),
      VoiceSeat(id: 's3', seatIndex: 3, status: VoiceSeatStatus.empty),
      VoiceSeat(id: 's4', seatIndex: 4, status: VoiceSeatStatus.empty),
      VoiceSeat(id: 's5', seatIndex: 5, status: VoiceSeatStatus.empty),
      VoiceSeat(id: 's6', seatIndex: 6, status: VoiceSeatStatus.empty),
      VoiceSeat(id: 's7', seatIndex: 7, status: VoiceSeatStatus.empty),
    ],
  );

  static const VoiceRtcCredentials rtc = VoiceRtcCredentials(
    appId: 'app',
    channel: 'voice-room-1',
    uid: 42,
    token: 'token',
  );

  @override
  Future<RepositoryResult<VoiceRoomConnection>> createRoom({
    required String title,
    String? description,
    String? thumbnail,
    int? seatCount,
  }) async {
    lastCreatedTitle = title;
    return RepositorySuccess<VoiceRoomConnection>(
      VoiceRoomConnection(
        room: VoiceRoom(
          id: 'room-1',
          title: title,
          status: VoiceRoomStatus.live,
          seatCount: seatCount ?? 8,
          participantCount: 1,
          host: host,
          seats: liveRoom.seats,
        ),
        rtc: rtc,
      ),
    );
  }

  @override
  Future<RepositoryResult<VoiceRoomConnection>> joinRoom(String roomId) async =>
      RepositorySuccess<VoiceRoomConnection>(
        VoiceRoomConnection(room: liveRoom, rtc: rtc),
      );

  @override
  Future<RepositoryResult<VoiceRoom>> leaveRoom(String roomId) async =>
      RepositorySuccess<VoiceRoom>(liveRoom);

  @override
  Future<RepositoryResult<VoiceRoom>> requestSeat(
    String roomId, {
    required int seatIndex,
  }) async => RepositorySuccess<VoiceRoom>(liveRoom);

  @override
  Future<RepositoryResult<VoiceRoom>> approveSeat(
    String roomId, {
    required int seatIndex,
  }) async => RepositorySuccess<VoiceRoom>(liveRoom);

  @override
  Future<RepositoryResult<VoiceRoom>> rejectSeat(
    String roomId, {
    required int seatIndex,
  }) async => RepositorySuccess<VoiceRoom>(liveRoom);

  @override
  Future<RepositoryResult<VoiceRoom>> removeSpeaker(
    String roomId, {
    required int seatIndex,
  }) async => RepositorySuccess<VoiceRoom>(liveRoom);

  @override
  Future<RepositoryResult<VoiceRoom>> muteSpeaker(
    String roomId, {
    required int seatIndex,
    bool muted = true,
  }) async => RepositorySuccess<VoiceRoom>(liveRoom);

  @override
  Future<RepositoryResult<VoiceRoom>> endRoom(String roomId) async =>
      RepositorySuccess<VoiceRoom>(
        liveRoom.copyWith(status: VoiceRoomStatus.ended),
      );

  @override
  Stream<VoiceRealtimeEvent> watchEvents(String roomId) => _events.stream;

  @override
  Future<void> connectRealtime(String roomId) async {}

  @override
  Future<void> disconnectRealtime() async {}
}
