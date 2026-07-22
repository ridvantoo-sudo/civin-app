import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/live/data/repositories/live_repository_impl.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/domain/repositories/live_repository.dart';
import 'package:civin/features/live/presentation/screens/live_home_screen.dart';
import 'package:civin/features/live/presentation/widgets/live_controls.dart';
import 'package:civin/features/live/presentation/widgets/viewer_counter.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('live home renders available streams', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          liveRepositoryProvider.overrideWithValue(const _FakeLiveRepository()),
        ],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData.dark(useMaterial3: true),
          home: const LiveHomeScreen(),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('Live'), findsOneWidget);
    expect(find.text('Go live'), findsOneWidget);
    expect(find.text('Town hall'), findsOneWidget);
    expect(find.text('River'), findsOneWidget);
    expect(find.text('LIVE'), findsOneWidget);
  });

  testWidgets('host controls expose mute, camera, and end actions', (
    WidgetTester tester,
  ) async {
    int actionCount = 0;
    await tester.pumpWidget(
      MaterialApp(
        theme: ThemeData.dark(useMaterial3: true),
        home: Scaffold(
          body: LiveControls(
            role: LiveRole.host,
            isMicMuted: false,
            onToggleMute: () => actionCount++,
            onSwitchCamera: () => actionCount++,
            onLeave: () => actionCount++,
          ),
        ),
      ),
    );

    expect(find.byTooltip('Mute microphone'), findsOneWidget);
    expect(find.byTooltip('Switch camera'), findsOneWidget);
    expect(find.byTooltip('End stream'), findsOneWidget);

    await tester.tap(find.byTooltip('Mute microphone'));
    await tester.tap(find.byTooltip('Switch camera'));
    await tester.tap(find.byTooltip('End stream'));
    expect(actionCount, 3);
  });

  testWidgets('viewer counter animates compact counts', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: ViewerCounter(count: 1250)),
      ),
    );

    expect(find.text('1.3K'), findsOneWidget);
  });
}

final class _FakeLiveRepository implements LiveRepository {
  const _FakeLiveRepository();

  static const LiveRoom room = LiveRoom(
    id: 'room-1',
    title: 'Town hall',
    channelName: 'channel-1',
    hostName: 'River',
    viewerCount: 25,
    isLive: true,
  );

  @override
  Future<RepositoryResult<List<LiveRoom>>> getLiveRooms() async =>
      const RepositorySuccess<List<LiveRoom>>(<LiveRoom>[room]);

  @override
  Future<RepositoryResult<List<LiveCategory>>> getCategories() async =>
      const RepositorySuccess<List<LiveCategory>>(<LiveCategory>[
        LiveCategory(id: 1, name: 'Talk'),
      ]);

  @override
  Future<RepositoryResult<LiveRoom>> createLiveRoom({
    required String title,
    required int categoryId,
    String? description,
  }) async => const RepositorySuccess<LiveRoom>(room);

  @override
  Future<RepositoryResult<LiveConnection>> startStream(String roomId) async =>
      const RepositorySuccess<LiveConnection>(
        LiveConnection(
          room: room,
          rtc: LiveRtcCredentials(
            appId: 'app-id',
            channel: 'channel-1',
            uid: 1,
            token: 'token',
          ),
        ),
      );

  @override
  Future<RepositoryResult<LiveConnection>> joinRoom(String roomId) async =>
      startStream(roomId);

  @override
  Future<RepositoryResult<void>> leaveRoom(String roomId) async =>
      const RepositorySuccess<void>(null);

  @override
  Future<RepositoryResult<void>> endStream(String roomId) async =>
      const RepositorySuccess<void>(null);
}
