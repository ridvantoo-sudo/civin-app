import 'package:agora_rtc_engine/agora_rtc_engine.dart';
import 'package:camera/camera.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:flutter/material.dart';

final class LivePlayer extends StatefulWidget {
  const LivePlayer({
    required this.role,
    this.room,
    this.engine,
    this.cameraController,
    this.remoteUid,
    this.useCameraPreview = false,
    super.key,
  });

  final LiveRoom? room;
  final LiveRole role;
  final RtcEngine? engine;
  final CameraController? cameraController;
  final int? remoteUid;
  final bool useCameraPreview;

  @override
  State<LivePlayer> createState() => _LivePlayerState();
}

final class _LivePlayerState extends State<LivePlayer> {
  VideoViewController? _controller;
  String? _controllerKey;

  @override
  void initState() {
    super.initState();
    _syncController();
  }

  @override
  void didUpdateWidget(covariant LivePlayer oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.role != widget.role ||
        oldWidget.engine != widget.engine ||
        oldWidget.remoteUid != widget.remoteUid ||
        oldWidget.room?.channelName != widget.room?.channelName ||
        oldWidget.useCameraPreview != widget.useCameraPreview) {
      _syncController();
    }
  }

  @override
  void dispose() {
    _controller = null;
    super.dispose();
  }

  void _syncController() {
    if (widget.useCameraPreview) {
      _controller = null;
      _controllerKey = null;
      return;
    }

    final RtcEngine? rtcEngine = widget.engine;
    final int? videoUid = widget.role == LiveRole.host ? 0 : widget.remoteUid;
    final String? channelName = widget.room?.channelName;
    if (rtcEngine == null || videoUid == null) {
      _controller = null;
      _controllerKey = null;
      return;
    }

    final String nextKey =
        '${widget.role.name}|$videoUid|${channelName ?? ''}|${identityHashCode(rtcEngine)}';
    if (nextKey == _controllerKey && _controller != null) return;

    _controllerKey = nextKey;
    _controller = widget.role == LiveRole.host
        ? VideoViewController(
            rtcEngine: rtcEngine,
            canvas: const VideoCanvas(uid: 0),
          )
        : VideoViewController.remote(
            rtcEngine: rtcEngine,
            canvas: VideoCanvas(uid: videoUid),
            connection: RtcConnection(channelId: channelName ?? ''),
          );
  }

  @override
  Widget build(BuildContext context) {
    if (widget.useCameraPreview) {
      final CameraController? preview = widget.cameraController;
      if (preview == null || !preview.value.isInitialized) {
        return const _LivePlaceholder(message: 'Preparing camera…');
      }
      return ColoredBox(
        color: Colors.black,
        child: FittedBox(
          fit: BoxFit.cover,
          child: SizedBox(
            width: preview.value.previewSize?.height ?? 720,
            height: preview.value.previewSize?.width ?? 1280,
            child: CameraPreview(preview),
          ),
        ),
      );
    }

    final VideoViewController? controller = _controller;
    if (controller == null) {
      return _LivePlaceholder(
        message: widget.role == LiveRole.host
            ? 'Preparing camera…'
            : 'Waiting for the host…',
      );
    }

    // Platform views + rapid parent rebuilds trip Flutter's
    // `!semantics.parentDataDirty` debug assertion. Keep one stable view.
    return ColoredBox(
      color: Colors.black,
      child: ExcludeSemantics(
        child: RepaintBoundary(child: AgoraVideoView(controller: controller)),
      ),
    );
  }
}

final class _LivePlaceholder extends StatelessWidget {
  const _LivePlaceholder({required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => ColoredBox(
    color: Colors.black,
    child: Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircularProgressIndicator.adaptive(),
          const SizedBox(height: 12),
          Text(message, style: const TextStyle(color: Colors.white)),
        ],
      ),
    ),
  );
}
