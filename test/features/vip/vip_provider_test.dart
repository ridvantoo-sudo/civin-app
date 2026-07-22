import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/vip/data/repositories/vip_repository_impl.dart';
import 'package:civin/features/vip/domain/entities/vip.dart';
import 'package:civin/features/vip/domain/repositories/vip_repository.dart';
import 'package:civin/features/vip/presentation/vip_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('vipProvider loads levels and current subscription', () async {
    final _FakeVipRepository repository = _FakeVipRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [vipRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container.read(vipProvider.notifier).load();

    final VipViewState state = container.read(vipProvider);
    expect(state.levels, hasLength(2));
    expect(state.subscription.isVip, isTrue);
    expect(state.subscription.level?.name, 'Bronze');
    expect(state.selectedLevelId, 'level-1');
    expect(state.currentBenefits, contains('VIP badge'));
  });

  test('vipProvider selects level and purchases', () async {
    final _FakeVipRepository repository = _FakeVipRepository()
      ..subscription = const VipSubscription(isVip: false);
    final ProviderContainer container = ProviderContainer(
      overrides: [vipRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container.read(vipProvider.notifier).load();
    container.read(vipProvider.notifier).selectLevel('level-2');
    final bool ok = await container.read(vipProvider.notifier).purchaseSelected(
      metadata: const <String, dynamic>{'source': 'test'},
    );

    expect(ok, isTrue);
    expect(repository.lastPurchaseId, 'level-2');
    expect(container.read(vipProvider).subscription.isVip, isTrue);
    expect(container.read(vipProvider).actionMessage, contains('activated'));
  });

  test('vipProvider upgrades to higher level', () async {
    final _FakeVipRepository repository = _FakeVipRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [vipRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container.read(vipProvider.notifier).load();
    container.read(vipProvider.notifier).selectLevel('level-2');
    final bool ok = await container.read(vipProvider.notifier).upgradeSelected();

    expect(ok, isTrue);
    expect(repository.lastUpgradeId, 'level-2');
    expect(container.read(vipProvider).subscription.level?.name, 'Gold');
    expect(container.read(vipProvider).actionMessage, contains('upgraded'));
  });

  test('vipProvider surfaces load failures', () async {
    final _FakeVipRepository repository = _FakeVipRepository()..fail = true;
    final ProviderContainer container = ProviderContainer(
      overrides: [vipRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container.read(vipProvider.notifier).load();

    expect(container.read(vipProvider).levels, isEmpty);
    expect(container.read(vipProvider).errorMessage, 'VIP unavailable');
  });
}

final class _FakeVipRepository implements VipRepository {
  bool fail = false;
  String? lastPurchaseId;
  String? lastUpgradeId;
  VipSubscription subscription = VipSubscription(
    id: 'sub-1',
    isVip: true,
    status: VipStatus.active,
    startedAt: DateTime.utc(2026, 7, 22),
    expiresAt: DateTime.utc(2026, 8, 22),
    level: _bronze,
    privileges: _bronze.privileges,
  );

  static const VipLevel _bronze = VipLevel(
    id: 'level-1',
    name: 'Bronze',
    level: 1,
    coinPrice: 500,
    durationDays: 30,
    privileges: VipPrivileges(badge: 'bronze-badge'),
  );

  static const VipLevel _gold = VipLevel(
    id: 'level-2',
    name: 'Gold',
    level: 3,
    coinPrice: 2000,
    durationDays: 30,
    privileges: VipPrivileges(
      badge: 'gold-badge',
      exclusiveGifts: true,
    ),
  );

  @override
  Future<RepositoryResult<List<VipLevel>>> getLevels() async {
    if (fail) {
      return const RepositoryFailure<List<VipLevel>>(
        AppFailure.network(message: 'VIP unavailable'),
      );
    }
    return const RepositorySuccess<List<VipLevel>>(<VipLevel>[_bronze, _gold]);
  }

  @override
  Future<RepositoryResult<VipSubscription>> getMyVip() async {
    if (fail) {
      return const RepositoryFailure<VipSubscription>(
        AppFailure.network(message: 'VIP unavailable'),
      );
    }
    return RepositorySuccess<VipSubscription>(subscription);
  }

  @override
  Future<RepositoryResult<VipSubscription>> purchase({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  }) async {
    lastPurchaseId = vipLevelId;
    subscription = VipSubscription(
      id: 'sub-new',
      isVip: true,
      status: VipStatus.active,
      startedAt: DateTime.utc(2026, 7, 22),
      expiresAt: DateTime.utc(2026, 8, 22),
      level: vipLevelId == _gold.id ? _gold : _bronze,
      privileges: vipLevelId == _gold.id
          ? _gold.privileges
          : _bronze.privileges,
    );
    return RepositorySuccess<VipSubscription>(subscription);
  }

  @override
  Future<RepositoryResult<VipSubscription>> upgrade({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  }) async {
    lastUpgradeId = vipLevelId;
    subscription = VipSubscription(
      id: 'sub-1',
      isVip: true,
      status: VipStatus.active,
      startedAt: DateTime.utc(2026, 7, 22),
      expiresAt: DateTime.utc(2026, 8, 22),
      level: _gold,
      privileges: _gold.privileges,
    );
    return RepositorySuccess<VipSubscription>(subscription);
  }
}
