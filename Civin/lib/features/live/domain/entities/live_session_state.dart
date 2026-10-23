import 'package:civin/features/live/domain/entities/live_room.dart';

enum LiveConnectionStatus {
  loading,
  connecting,
  connected,
  disconnected,
  error,
}

final class LiveSessionState {
  const LiveSessionState({
    this.status = LiveConnectionStatus.disconnected,
    this.room,
    this.role,
    this.remoteUid,
    this.isMicMuted = false,
    this.isFrontCamera = true,
    this.previewReady = false,
    this.message,
  });

  final LiveConnectionStatus status;
  final LiveRoom? room;
  final LiveRole? role;
  final int? remoteUid;
  final bool isMicMuted;
  final bool isFrontCamera;
  final bool previewReady;
  final String? message;

  int get viewerCount => room?.viewerCount ?? 0;

  LiveSessionState copyWith({
    LiveConnectionStatus? status,
    LiveRoom? room,
    LiveRole? role,
    int? remoteUid,
    bool clearRemoteUid = false,
    bool? isMicMuted,
    bool? isFrontCamera,
    bool? previewReady,
    String? message,
    bool clearMessage = false,
  }) => LiveSessionState(
    status: status ?? this.status,
    room: room ?? this.room,
    role: role ?? this.role,
    remoteUid: clearRemoteUid ? null : remoteUid ?? this.remoteUid,
    isMicMuted: isMicMuted ?? this.isMicMuted,
    isFrontCamera: isFrontCamera ?? this.isFrontCamera,
    previewReady: previewReady ?? this.previewReady,
    message: clearMessage ? null : message ?? this.message,
  );
}
