import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/splash/data/splash_local_data_source.dart';
import 'package:civin/features/splash/domain/repositories/splash_repository.dart';

final class SplashRepositoryImpl extends BaseRepository
    implements SplashRepository {
  const SplashRepositoryImpl(this._dataSource);

  final SplashLocalDataSource _dataSource;

  @override
  Future<void> initializeSecureStorage() =>
      _dataSource.initializeSecureStorage();

  @override
  Future<void> initializeSharedPreferences() =>
      _dataSource.initializeSharedPreferences();

  @override
  Future<bool> checkConnectivity() => _dataSource.checkConnectivity();

  @override
  Future<bool> isFirebaseReady() => _dataSource.isFirebaseReady();

  @override
  Future<bool> isOnboardingCompleted() => _dataSource.isOnboardingCompleted();

  @override
  Future<bool> isLoggedIn() => _dataSource.isLoggedIn();
}
