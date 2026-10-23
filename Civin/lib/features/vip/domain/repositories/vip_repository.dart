import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/vip/domain/entities/vip.dart';

abstract interface class VipRepository {
  Future<RepositoryResult<List<VipLevel>>> getLevels();

  Future<RepositoryResult<VipSubscription>> getMyVip();

  Future<RepositoryResult<VipSubscription>> purchase({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  });

  Future<RepositoryResult<VipSubscription>> upgrade({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  });
}
