import 'dart:async';

import 'package:agora_rtc_engine/agora_rtc_engine.dart';
import 'package:civin/core/config/environment.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:wakelock_plus/wakelock_plus.dart';

final Provider<LiveRtcDataSource> liveRtcDataSourceProvider =
    Provider<LiveRtcDataSource>((Ref ref) {
      final AgoraLiveRtcDataSource source = AgoraLiveRtcDataSource();
      ref.onDispose(() {
        unawaited(source.dispose());
      });
      return source;
    });

sealed class LiveRtcEvent {
  const LiveRtcEvent();
}

final class LiveRtcConnected extends LiveRtcEvent {
  const LiveRtcConnected();
}

final class LiveRtcRemoteUserJoined extends LiveRtcEvent {
  const LiveRtcRemoteUserJoined(this.uid);
  final int uid;
}

final class LiveRtcRemoteUserLeft extends LiveRtcEvent {
  const LiveRtcRemoteUserLeft(this.uid);
  final int uid;
}

final class LiveRtcFailure extends LiveRtcEvent {
  const LiveRtcFailure(this.message);
  final String message;
}

abstract interface class LiveRtcDataSource {
  Stream<LiveRtcEvent> get events;
  RtcEngine? get engine;
  Future<void> join(LiveRtcCredentials credentials, LiveRole role);
  Future<void> muteMicrophone(bool muted);
  Future<void> switchCamera();
  Future<void> leave();
  Future<void> dispose();
}

final class AgoraLiveRtcDataSource implements LiveRtcDataSource {
  final StreamController<LiveRtcEvent> _events =
      StreamController<LiveRtcEvent>.broadcast();
  RtcEngine? _engine;
  String? _initializedAppId;

  @override
  Stream<LiveRtcEvent> get events => _events.stream;

  @override
  RtcEngine? get engine => _engine;

  Future<void> _initialize(String appId) async {
    final String resolvedAppId = appId.isNotEmpty
        ? appId
        : Environment.agoraAppId;
    if (resolvedAppId.isEmpty) {
      throw StateError('AGORA_APP_ID is missing. Pass it with --dart-define.');
    }
    if (_initializedAppId == resolvedAppId && _engine != null) return;

    await leave();
    await _engine?.release();
    _engine = null;

    final RtcEngine rtcEngine = createAgoraRtcEngine();
    await rtcEngine.initialize(
      RtcEngineContext(
        appId: resolvedAppId,
        channelProfile: ChannelProfileType.channelProfileLiveBroadcasting,
      ),
    );
    rtcEngine.registerEventHandler(
      RtcEngineEventHandler(
        onJoinChannelSuccess: (RtcConnection connection, int elapsed) {
          _events.add(const LiveRtcConnected());
        },
        onUserJoined: (RtcConnection connection, int remoteUid, int elapsed) {
          _events.add(LiveRtcRemoteUserJoined(remoteUid));
        },
        onUserOffline:
            (
              RtcConnection connection,
              int remoteUid,
              UserOfflineReasonType reason,
            ) {
              _events.add(LiveRtcRemoteUserLeft(remoteUid));
            },
        onError: (ErrorCodeType code, String message) {
          _events.add(LiveRtcFailure(message.isEmpty ? code.name : message));
        },
      ),
    );
    await rtcEngine.enableVideo();
    _engine = rtcEngine;
    _initializedAppId = resolvedAppId;
  }

  @override
  Future<void> join(LiveRtcCredentials credentials, LiveRole role) async {
    await _initialize(credentials.appId);
    final bool isHost = role == LiveRole.host;
    await _engine!.setClientRole(
      role: isHost
          ? ClientRoleType.clientRoleBroadcaster
          : ClientRoleType.clientRoleAudience,
    );
    if (isHost) {
      await _engine!.startPreview();
    }
    await _engine!.joinChannel(
      token: credentials.token,
      channelId: credentials.channel,
      uid: credentials.uid,
      options: ChannelMediaOptions(
        channelProfile: ChannelProfileType.channelProfileLiveBroadcasting,
        clientRoleType: isHost
            ? ClientRoleType.clientRoleBroadcaster
            : ClientRoleType.clientRoleAudience,
        publishCameraTrack: isHost,
        publishMicrophoneTrack: isHost,
        autoSubscribeAudio: !isHost,
        autoSubscribeVideo: !isHost,
      ),
    );
    await WakelockPlus.enable();
  }

  @override
  Future<void> muteMicrophone(bool muted) async {
    await _engine?.muteLocalAudioStream(muted);
  }

  @override
  Future<void> switchCamera() async {
    await _engine?.switchCamera();
  }

  @override
  Future<void> leave() async {
    await _engine?.stopPreview();
    await _engine?.leaveChannel();
    await WakelockPlus.disable();
  }

  @override
  Future<void> dispose() async {
    await leave();
    await _engine?.release();
    _engine = null;
    _initializedAppId = null;
    await _events.close();
  }
}
