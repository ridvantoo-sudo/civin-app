import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/vip/domain/entities/vip.dart';
import 'package:civin/features/vip/domain/repositories/vip_repository.dart';

final class GetVipLevels {
  const GetVipLevels(this._repository);

  final VipRepository _repository;

  Future<RepositoryResult<List<VipLevel>>> call() => _repository.getLevels();
}

final class GetMyVip {
  const GetMyVip(this._repository);

  final VipRepository _repository;

  Future<RepositoryResult<VipSubscription>> call() => _repository.getMyVip();
}

final class PurchaseVip {
  const PurchaseVip(this._repository);

  final VipRepository _repository;

  Future<RepositoryResult<VipSubscription>> call({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  }) => _repository.purchase(vipLevelId: vipLevelId, metadata: metadata);
}

final class UpgradeVip {
  const UpgradeVip(this._repository);

  final VipRepository _repository;

  Future<RepositoryResult<VipSubscription>> call({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  }) => _repository.upgrade(vipLevelId: vipLevelId, metadata: metadata);
}
