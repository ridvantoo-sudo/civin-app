import 'package:civin/core/network/network_checker.dart';
import 'package:civin/core/services/app_logger.dart';
import 'package:civin/core/services/firebase_service.dart';
import 'package:civin/core/services/package_info_service.dart';
import 'package:civin/core/storage/secure_storage.dart';
import 'package:civin/core/storage/shared_pref_service.dart';
import 'package:civin/features/splash/data/splash_local_data_source.dart';
import 'package:civin/features/splash/domain/entities/splash_destination.dart';
import 'package:civin/features/splash/domain/repositories/splash_repository.dart';
import 'package:civin/features/splash/domain/usecases/resolve_splash_destination.dart';
import 'package:civin/features/splash/repository/splash_repository_impl.dart';
import 'package:civin/features/splash/services/splash_bootstrap_service.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:package_info_plus/package_info_plus.dart';

final Provider<SplashLocalDataSource> splashLocalDataSourceProvider =
    Provider<SplashLocalDataSource>(
      (Ref ref) => SplashLocalDataSource(
        sharedPreferences: ref.watch(sharedPrefServiceProvider),
        secureStorage: ref.watch(secureStorageProvider),
        networkChecker: ref.watch(networkCheckerProvider),
        firebaseService: ref.watch(firebaseServiceProvider),
      ),
    );

final Provider<SplashRepository> splashRepositoryProvider =
    Provider<SplashRepository>(
      (Ref ref) =>
          SplashRepositoryImpl(ref.watch(splashLocalDataSourceProvider)),
    );

final Provider<ResolveSplashDestination> resolveSplashDestinationProvider =
    Provider<ResolveSplashDestination>(
      (Ref ref) =>
          ResolveSplashDestination(ref.watch(splashRepositoryProvider)),
    );

final Provider<SplashBootstrapService> splashBootstrapServiceProvider =
    Provider<SplashBootstrapService>(
      (Ref ref) => SplashBootstrapService(
        repository: ref.watch(splashRepositoryProvider),
        resolveDestination: ref.watch(resolveSplashDestinationProvider),
        logger: ref.watch(appLoggerProvider),
      ),
    );

final FutureProvider<PackageInfo> splashPackageInfoProvider =
    FutureProvider<PackageInfo>(
      (Ref ref) => ref.watch(packageInfoServiceProvider).load(),
    );

final FutureProvider<SplashDestination> splashBootstrapProvider =
    FutureProvider<SplashDestination>(
      (Ref ref) => ref.watch(splashBootstrapServiceProvider).bootstrap(),
    );
