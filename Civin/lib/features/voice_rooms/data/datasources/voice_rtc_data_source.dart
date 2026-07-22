import 'dart:async';

import 'package:agora_rtc_engine/agora_rtc_engine.dart';
import 'package:civin/core/config/environment.dart';
import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:wakelock_plus/wakelock_plus.dart';

final Provider<VoiceRtcDataSource> voiceRtcDataSourceProvider =
    Provider<VoiceRtcDataSource>((Ref ref) {
      final AgoraVoiceRtcDataSource source = AgoraVoiceRtcDataSource();
      ref.onDispose(() {
        unawaited(source.dispose());
      });
      return source;
    });

sealed class VoiceRtcEvent {
  const VoiceRtcEvent();
}

final class VoiceRtcConnected extends VoiceRtcEvent {
  const VoiceRtcConnected();
}

final class VoiceRtcFailure extends VoiceRtcEvent {
  const VoiceRtcFailure(this.message);
  final String message;
}

abstract interface class VoiceRtcDataSource {
  Stream<VoiceRtcEvent> get events;

  Future<void> join(VoiceRtcCredentials credentials, {required bool asSpeaker});
  Future<void> muteMicrophone(bool muted);
  Future<void> leave();
  Future<void> dispose();
}

final class AgoraVoiceRtcDataSource implements VoiceRtcDataSource {
  final StreamController<VoiceRtcEvent> _events =
      StreamController<VoiceRtcEvent>.broadcast();
  RtcEngine? _engine;
  String? _initializedAppId;

  @override
  Stream<VoiceRtcEvent> get events => _events.stream;

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
          _events.add(const VoiceRtcConnected());
        },
        onError: (ErrorCodeType code, String message) {
          _events.add(VoiceRtcFailure(message.isEmpty ? code.name : message));
        },
      ),
    );
    await rtcEngine.enableAudio();
    await rtcEngine.disableVideo();
    _engine = rtcEngine;
    _initializedAppId = resolvedAppId;
  }

  @override
  Future<void> join(
    VoiceRtcCredentials credentials, {
    required bool asSpeaker,
  }) async {
    await _initialize(credentials.appId);
    await _engine!.setClientRole(
      role: asSpeaker
          ? ClientRoleType.clientRoleBroadcaster
          : ClientRoleType.clientRoleAudience,
    );
    await _engine!.joinChannel(
      token: credentials.token,
      channelId: credentials.channel,
      uid: credentials.uid,
      options: ChannelMediaOptions(
        channelProfile: ChannelProfileType.channelProfileLiveBroadcasting,
        clientRoleType: asSpeaker
            ? ClientRoleType.clientRoleBroadcaster
            : ClientRoleType.clientRoleAudience,
        publishMicrophoneTrack: asSpeaker,
        publishCameraTrack: false,
        autoSubscribeAudio: true,
        autoSubscribeVideo: false,
      ),
    );
    await WakelockPlus.enable();
  }

  @override
  Future<void> muteMicrophone(bool muted) async {
    await _engine?.muteLocalAudioStream(muted);
  }

  @override
  Future<void> leave() async {
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
