import 'package:camera/camera.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:permission_handler/permission_handler.dart';

final Provider<LiveCameraDataSource> liveCameraDataSourceProvider =
    Provider<LiveCameraDataSource>((Ref ref) {
      final CameraLiveCameraDataSource source = CameraLiveCameraDataSource();
      ref.onDispose(() {
        source.dispose();
      });
      return source;
    });

abstract interface class LiveCameraDataSource {
  CameraController? get controller;
  Future<bool> requestHostPermissions();
  Future<void> startPreview({bool frontCamera = true});
  Future<void> switchCamera();
  Future<void> stopPreview();
  Future<void> dispose();
}

final class CameraLiveCameraDataSource implements LiveCameraDataSource {
  CameraController? _controller;
  List<CameraDescription> _cameras = const <CameraDescription>[];
  bool _frontCamera = true;

  @override
  CameraController? get controller => _controller;

  @override
  Future<bool> requestHostPermissions() async {
    final Map<Permission, PermissionStatus> statuses = await <Permission>[
      Permission.camera,
      Permission.microphone,
    ].request();
    return statuses.values.every((PermissionStatus status) => status.isGranted);
  }

  @override
  Future<void> startPreview({bool frontCamera = true}) async {
    _frontCamera = frontCamera;
    _cameras = await availableCameras();
    if (_cameras.isEmpty) {
      throw StateError('No camera is available on this device.');
    }
    await _bindCamera(_resolveLens());
  }

  @override
  Future<void> switchCamera() async {
    if (_cameras.length < 2) return;
    _frontCamera = !_frontCamera;
    await _bindCamera(_resolveLens());
  }

  @override
  Future<void> stopPreview() async {
    final CameraController? current = _controller;
    _controller = null;
    await current?.dispose();
  }

  @override
  Future<void> dispose() => stopPreview();

  CameraLensDirection _resolveLens() => _frontCamera
      ? CameraLensDirection.front
      : CameraLensDirection.back;

  Future<void> _bindCamera(CameraLensDirection direction) async {
    final CameraDescription description = _cameras.firstWhere(
      (CameraDescription camera) => camera.lensDirection == direction,
      orElse: () => _cameras.first,
    );
    final CameraController next = CameraController(
      description,
      ResolutionPreset.high,
      enableAudio: true,
    );
    await next.initialize();
    final CameraController? previous = _controller;
    _controller = next;
    await previous?.dispose();
  }
}
