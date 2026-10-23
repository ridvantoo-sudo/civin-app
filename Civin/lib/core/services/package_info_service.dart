import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

final Provider<PackageInfoService> packageInfoServiceProvider =
    Provider<PackageInfoService>((Ref ref) => const PackageInfoService());

final class PackageInfoService {
  const PackageInfoService();

  Future<PackageInfo> load() => PackageInfo.fromPlatform();
}
