enum DevicePlatform { android, ios, macos, windows, linux, web, unknown }

final class Device {
  const Device({
    required this.id,
    required this.platform,
    required this.biometricEnabled,
    required this.lastActiveAt,
  });

  final String id;
  final DevicePlatform platform;
  final bool biometricEnabled;
  final DateTime lastActiveAt;
}
