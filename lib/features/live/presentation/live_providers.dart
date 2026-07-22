import 'dart:async';

import 'package:agora_rtc_engine/agora_rtc_engine.dart';
import 'package:camera/camera.dart';
import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/live/data/datasources/live_camera_data_source.dart';
import 'package:civin/features/live/data/datasources/live_rtc_data_source.dart';
import 'package:civin/features/live/data/repositories/live_repository_impl.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/domain/entities/live_session_state.dart';
import 'package:civin/features/live/domain/usecases/live_usecases.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<BrowseLiveRooms> browseLiveRoomsProvider =
    Provider<BrowseLiveRooms>(
      (Ref ref) => BrowseLiveRooms(ref.watch(liveRepositoryProvider)),
    );
final Provider<BrowseLiveCategories> browseLiveCategoriesProvider =
    Provider<BrowseLiveCategories>(
      (Ref ref) => BrowseLiveCategories(ref.watch(liveRepositoryProvider)),
    );
final Provider<CreateLiveRoom> createLiveRoomProvider =
    Provider<CreateLiveRoom>(
      (Ref ref) => CreateLiveRoom(ref.watch(liveRepositoryProvider)),
    );
final Provider<StartLiveStream> startLiveStreamProvider =
    Provider<StartLiveStream>(
      (Ref ref) => StartLiveStream(ref.watch(liveRepositoryProvider)),
    );
final Provider<JoinLiveRoom> joinLiveRoomProvider = Provider<JoinLiveRoom>(
  (Ref ref) => JoinLiveRoom(ref.watch(liveRepositoryProvider)),
);
final Provider<LeaveLiveRoom> leaveLiveRoomProvider = Provider<LeaveLiveRoom>(
  (Ref ref) => LeaveLiveRoom(ref.watch(liveRepositoryProvider)),
);
final Provider<EndLiveStream> endLiveStreamProvider = Provider<EndLiveStream>(
  (Ref ref) => EndLiveStream(ref.watch(liveRepositoryProvider)),
);

final AsyncNotifierProvider<LiveRoomsController, List<LiveRoom>>
liveRoomsProvider = AsyncNotifierProvider<LiveRoomsController, List<LiveRoom>>(
  LiveRoomsController.new,
);

final AsyncNotifierProvider<LiveCategoriesController, List<LiveCategory>>
liveCategoriesProvider =
    AsyncNotifierProvider<LiveCategoriesController, List<LiveCategory>>(
      LiveCategoriesController.new,
    );

final NotifierProvider<LiveSessionController, LiveSessionState>
liveSessionProvider = NotifierProvider<LiveSessionController, LiveSessionState>(
  LiveSessionController.new,
);

final Provider<RtcEngine?> liveRtcEngineProvider = Provider<RtcEngine?>((
  Ref ref,
) {
  ref.watch(liveSessionProvider);
  return ref.read(liveRtcDataSourceProvider).engine;
});

final Provider<CameraController?> liveCameraControllerProvider =
    Provider<CameraController?>((Ref ref) {
      ref.watch(liveSessionProvider);
      return ref.read(liveCameraDataSourceProvider).controller;
    });

final class LiveRoomsController extends AsyncNotifier<List<LiveRoom>> {
  @override
  Future<List<LiveRoom>> build() async =>
      _unwrap(await ref.read(browseLiveRoomsProvider)());

  Future<void> refresh() async {
    state = const AsyncLoading<List<LiveRoom>>();
    state = await AsyncValue.guard(
      () async => _unwrap(await ref.read(browseLiveRoomsProvider)()),
    );
  }
}

final class LiveCategoriesController extends AsyncNotifier<List<LiveCategory>> {
  @override
  Future<List<LiveCategory>> build() async =>
      _unwrap(await ref.read(browseLiveCategoriesProvider)());
}

final class LiveSessionController extends Notifier<LiveSessionState> {
  StreamSubscription<LiveRtcEvent>? _eventsSubscription;

  LiveRtcDataSource get _rtc => ref.read(liveRtcDataSourceProvider);
  LiveCameraDataSource get _camera => ref.read(liveCameraDataSourceProvider);

  @override
  LiveSessionState build() {
    final LiveRtcDataSource rtc = ref.read(liveRtcDataSourceProvider);
    final LiveCameraDataSource camera = ref.read(liveCameraDataSourceProvider);
    _eventsSubscription = rtc.events.listen(_onRtcEvent);
    ref.onDispose(() {
      unawaited(_eventsSubscription?.cancel());
      unawaited(camera.stopPreview());
    });
    return const LiveSessionState();
  }

  Future<bool> prepareHost() async {
    state = state.copyWith(
      status: LiveConnectionStatus.loading,
      role: LiveRole.host,
      previewReady: false,
      clearMessage: true,
    );
    try {
      if (!await _camera.requestHostPermissions()) {
        throw const LiveException(
          'Camera and microphone permissions are required to go live.',
        );
      }
      await _camera.startPreview(frontCamera: state.isFrontCamera);
      state = state.copyWith(
        status: LiveConnectionStatus.disconnected,
        previewReady: true,
      );
      return true;
    } on Object catch (error) {
      _setError(error);
      return false;
    }
  }

  Future<LiveRoom?> start({
    required String title,
    required int categoryId,
    String? description,
  }) async {
    state = state.copyWith(
      status: LiveConnectionStatus.connecting,
      role: LiveRole.host,
      clearMessage: true,
    );
    try {
      final LiveRoom created = _unwrap(
        await ref
            .read(createLiveRoomProvider)(
              title: title,
              categoryId: categoryId,
              description: description,
            ),
      );
      state = state.copyWith(room: created);
      final LiveConnection connection = _unwrap(
        await ref.read(startLiveStreamProvider)(created.id),
      );
      await _camera.stopPreview();
      state = state.copyWith(room: connection.room, previewReady: false);
      await _rtc.join(connection.rtc, LiveRole.host);
      return connection.room;
    } on Object catch (error) {
      _setError(error);
      return null;
    }
  }

  Future<bool> join(LiveRoom room) async {
    state = LiveSessionState(
      status: LiveConnectionStatus.connecting,
      room: room,
      role: LiveRole.viewer,
    );
    try {
      final LiveConnection connection = _unwrap(
        await ref.read(joinLiveRoomProvider)(room.id),
      );
      state = state.copyWith(room: connection.room);
      await _rtc.join(connection.rtc, LiveRole.viewer);
      return true;
    } on Object catch (error) {
      _setError(error);
      return false;
    }
  }

  Future<void> toggleMute() async {
    final bool muted = !state.isMicMuted;
    try {
      await _rtc.muteMicrophone(muted);
      state = state.copyWith(isMicMuted: muted);
    } on Object catch (error) {
      _setError(error);
    }
  }

  Future<void> switchCamera() async {
    try {
      if (state.previewReady) {
        await _camera.switchCamera();
      } else {
        await _rtc.switchCamera();
      }
      state = state.copyWith(isFrontCamera: !state.isFrontCamera);
    } on Object catch (error) {
      _setError(error);
    }
  }

  Future<void> leave() async {
    final LiveRoom? room = state.room;
    final LiveRole? role = state.role;
    try {
      if (room != null) {
        if (role == LiveRole.host) {
          _unwrap(await ref.read(endLiveStreamProvider)(room.id));
        } else {
          _unwrap(await ref.read(leaveLiveRoomProvider)(room.id));
        }
      }
      await _rtc.leave();
      await _camera.stopPreview();
      state = const LiveSessionState();
    } on Object catch (error) {
      _setError(error);
    }
  }

  Future<void> cancelPreview() async {
    await _camera.stopPreview();
    await _rtc.leave();
    state = const LiveSessionState();
  }

  void _onRtcEvent(LiveRtcEvent event) {
    if (!ref.mounted) return;
    switch (event) {
      case LiveRtcConnected():
        state = state.copyWith(status: LiveConnectionStatus.connected);
      case LiveRtcRemoteUserJoined(:final uid):
        final LiveRoom? room = state.room;
        final bool isHost = state.role == LiveRole.host;
        state = state.copyWith(
          status: LiveConnectionStatus.connected,
          remoteUid: uid,
          room: isHost
              ? room?.copyWith(viewerCount: room.viewerCount + 1)
              : room,
        );
      case LiveRtcRemoteUserLeft(:final uid):
        final LiveRoom? room = state.room;
        final bool isHost = state.role == LiveRole.host;
        state = state.copyWith(
          clearRemoteUid: state.remoteUid == uid,
          room: isHost
              ? room?.copyWith(
                  viewerCount: (room.viewerCount - 1).clamp(0, 1 << 31),
                )
              : room,
        );
      case LiveRtcFailure(:final message):
        state = state.copyWith(
          status: LiveConnectionStatus.error,
          message: message,
        );
    }
  }

  void _setError(Object error) {
    state = state.copyWith(
      status: LiveConnectionStatus.error,
      message: error.toString(),
    );
  }
}

final class LiveException implements Exception {
  const LiveException(this.message);
  final String message;

  @override
  String toString() => message;
}

T _unwrap<T>(RepositoryResult<T> result) => result.fold(
  onSuccess: (T data) => data,
  onFailure: (failure) => throw LiveException(failure.message),
);
