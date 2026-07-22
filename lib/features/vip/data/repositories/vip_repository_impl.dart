import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/vip/data/datasources/vip_remote_data_source.dart';
import 'package:civin/features/vip/domain/entities/vip.dart';
import 'package:civin/features/vip/domain/repositories/vip_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<VipRepository> vipRepositoryProvider = Provider<VipRepository>(
  (Ref ref) => VipRepositoryImpl(ref.watch(vipRemoteDataSourceProvider)),
);

final class VipRepositoryImpl extends BaseRepository implements VipRepository {
  VipRepositoryImpl(this._remote);

  final VipRemoteDataSource _remote;

  @override
  Future<RepositoryResult<List<VipLevel>>> getLevels() =>
      execute(_remote.getLevels);

  @override
  Future<RepositoryResult<VipSubscription>> getMyVip() =>
      execute(_remote.getMyVip);

  @override
  Future<RepositoryResult<VipSubscription>> purchase({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  }) => execute(
    () => _remote.purchase(vipLevelId: vipLevelId, metadata: metadata),
  );

  @override
  Future<RepositoryResult<VipSubscription>> upgrade({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  }) => execute(
    () => _remote.upgrade(vipLevelId: vipLevelId, metadata: metadata),
  );
}
