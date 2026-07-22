import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/onboarding/data/onboarding_local_data_source.dart';
import 'package:civin/features/onboarding/domain/repositories/onboarding_repository.dart';

final class OnboardingRepositoryImpl extends BaseRepository
    implements OnboardingRepository {
  const OnboardingRepositoryImpl(this._dataSource);

  final OnboardingLocalDataSource _dataSource;

  @override
  Future<bool> isCompleted() => _dataSource.isCompleted();

  @override
  Future<void> complete() => _dataSource.complete();
}
