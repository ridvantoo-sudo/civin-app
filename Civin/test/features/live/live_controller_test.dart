import 'dart:async';

import 'package:agora_rtc_engine/agora_rtc_engine.dart';
import 'package:camera/camera.dart';
import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/live/data/datasources/live_camera_data_source.dart';
import 'package:civin/features/live/data/datasources/live_rtc_data_source.dart';
import 'package:civin/features/live/data/repositories/live_repository_impl.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/domain/entities/live_session_state.dart';
import 'package:civin/features/live/domain/repositories/live_repository.dart';
import 'package:civin/features/live/presentation/live_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late _FakeLiveRepository repository;
  late _FakeLiveRtcDataSource rtc;
  late _FakeLiveCameraDataSource camera;
  late ProviderContainer container;

  setUp(() {
    repository = _FakeLiveRepository();
    rtc = _FakeLiveRtcDataSource();
    camera = _FakeLiveCameraDataSource();
    container = ProviderContainer(
      overrides: [
        liveRepositoryProvider.overrideWithValue(repository),
        liveRtcDataSourceProvider.overrideWithValue(rtc),
        liveCameraDataSourceProvider.overrideWithValue(camera),
      ],
    );
    addTearDown(() async {
      container.dispose();
      await rtc.dispose();
      await camera.dispose();
    });
  });

  test(
    'host moves through preview, connecting, and connected states',
    () async {
      final LiveSessionController controller = container.read(
        liveSessionProvider.notifier,
      );

      expect(await controller.prepareHost(), isTrue);
      expect(camera.previewStarted, isTrue);
      expect(
        container.read(liveSessionProvider).status,
        LiveConnectionStatus.disconnected,
      );
      expect(container.read(liveSessionProvider).previewReady, isTrue);

      final LiveRoom? room = await controller.start(
        title: 'Town hall',
        categoryId: 2,
      );
      expect(room, isNotNull);
      expect(repository.createdTitle, 'Town hall');
      expect(repository.createdCategoryId, 2);
      expect(repository.started, isTrue);
      expect(camera.previewStopped, isTrue);
      expect(rtc.joinedRole, LiveRole.host);
      expect(
        container.read(liveSessionProvider).status,
        LiveConnectionStatus.connecting,
      );

      rtc.emit(const LiveRtcConnected());
      await Future<void>.delayed(Duration.zero);
      expect(
        container.read(liveSessionProvider).status,
        LiveConnectionStatus.connected,
      );
    },
  );

  test('viewer joins, tracks remote host, and leaves', () async {
    final LiveSessionController controller = container.read(
      liveSessionProvider.notifier,
    );

    expect(await controller.join(_FakeLiveRepository.room), isTrue);
    expect(repository.joined, isTrue);
    expect(rtc.joinedRole, LiveRole.viewer);

    rtc.emit(const LiveRtcRemoteUserJoined(77));
    await Future<void>.delayed(Duration.zero);
    expect(container.read(liveSessionProvider).remoteUid, 77);
    expect(container.read(liveSessionProvider).viewerCount, 10);

    await controller.leave();
    expect(repository.left, isTrue);
    expect(rtc.left, isTrue);
    expect(
      container.read(liveSessionProvider).status,
      LiveConnectionStatus.disconnected,
    );
  });

  test('host increments viewer count when remote users join', () async {
    final LiveSessionController controller = container.read(
      liveSessionProvider.notifier,
    );

    await controller.prepareHost();
    await controller.start(title: 'Town hall', categoryId: 2);
    rtc.emit(const LiveRtcConnected());
    rtc.emit(const LiveRtcRemoteUserJoined(88));
    await Future<void>.delayed(Duration.zero);

    expect(container.read(liveSessionProvider).viewerCount, 11);
    expect(container.read(liveSessionProvider).remoteUid, 88);
  });

  test('permission denial produces an error state', () async {
    camera.permissionsGranted = false;

    final bool prepared = await container
        .read(liveSessionProvider.notifier)
        .prepareHost();

    expect(prepared, isFalse);
    expect(
      container.read(liveSessionProvider).status,
      LiveConnectionStatus.error,
    );
    expect(
      container.read(liveSessionProvider).message,
      contains('permissions'),
    );
  });
}

final class _FakeLiveRepository implements LiveRepository {
  static const LiveRoom room = LiveRoom(
    id: 'room-1',
    title: 'Town hall',
    channelName: 'channel-1',
    token: 'token',
    hostName: 'River',
    viewerCount: 10,
    isLive: true,
  );

  static const LiveConnection connection = LiveConnection(
    room: room,
    rtc: LiveRtcCredentials(
      appId: 'app-id',
      channel: 'channel-1',
      uid: 42,
      token: 'token',
    ),
  );

  String? createdTitle;
  int? createdCategoryId;
  bool started = false;
  bool joined = false;
  bool left = false;

  @override
  Future<RepositoryResult<List<LiveRoom>>> getLiveRooms() async =>
      const RepositorySuccess<List<LiveRoom>>(<LiveRoom>[room]);

  @override
  Future<RepositoryResult<List<LiveCategory>>> getCategories() async =>
      const RepositorySuccess<List<LiveCategory>>(<LiveCategory>[
        LiveCategory(id: 2, name: 'Talk'),
      ]);

  @override
  Future<RepositoryResult<LiveRoom>> createLiveRoom({
    required String title,
    required int categoryId,
    String? description,
  }) async {
    createdTitle = title;
    createdCategoryId = categoryId;
    return const RepositorySuccess<LiveRoom>(room);
  }

  @override
  Future<RepositoryResult<LiveConnection>> startStream(String roomId) async {
    started = true;
    return const RepositorySuccess<LiveConnection>(connection);
  }

  @override
  Future<RepositoryResult<LiveConnection>> joinRoom(String roomId) async {
    joined = true;
    return const RepositorySuccess<LiveConnection>(connection);
  }

  @override
  Future<RepositoryResult<void>> leaveRoom(String roomId) async {
    left = true;
    return const RepositorySuccess<void>(null);
  }

  @override
  Future<RepositoryResult<void>> endStream(String roomId) async =>
      const RepositorySuccess<void>(null);
}

final class _FakeLiveRtcDataSource implements LiveRtcDataSource {
  final StreamController<LiveRtcEvent> _events =
      StreamController<LiveRtcEvent>.broadcast();

  bool left = false;
  LiveRole? joinedRole;

  void emit(LiveRtcEvent event) => _events.add(event);

  @override
  Stream<LiveRtcEvent> get events => _events.stream;

  @override
  RtcEngine? get engine => null;

  @override
  Future<void> join(LiveRtcCredentials credentials, LiveRole role) async {
    joinedRole = role;
  }

  @override
  Future<void> muteMicrophone(bool muted) async {}

  @override
  Future<void> switchCamera() async {}

  @override
  Future<void> leave() async {
    left = true;
  }

  @override
  Future<void> dispose() => _events.close();
}

final class _FakeLiveCameraDataSource implements LiveCameraDataSource {
  bool permissionsGranted = true;
  bool previewStarted = false;
  bool previewStopped = false;

  @override
  CameraController? get controller => null;

  @override
  Future<bool> requestHostPermissions() async => permissionsGranted;

  @override
  Future<void> startPreview({bool frontCamera = true}) async {
    previewStarted = true;
  }

  @override
  Future<void> switchCamera() async {}

  @override
  Future<void> stopPreview() async {
    previewStopped = true;
  }

  @override
  Future<void> dispose() async {}
}
