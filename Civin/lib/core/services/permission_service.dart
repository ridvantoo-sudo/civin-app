import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:permission_handler/permission_handler.dart';

final Provider<PermissionService> permissionServiceProvider =
    Provider<PermissionService>((Ref ref) => const PermissionService());

final class PermissionService {
  const PermissionService();

  Future<PermissionStatus> status(Permission permission) => permission.status;

  Future<PermissionStatus> request(Permission permission) =>
      permission.request();

  Future<Map<Permission, PermissionStatus>> requestAll(
    Iterable<Permission> permissions,
  ) => permissions.toList(growable: false).request();

  Future<bool> openSettings() => openAppSettings();
}
