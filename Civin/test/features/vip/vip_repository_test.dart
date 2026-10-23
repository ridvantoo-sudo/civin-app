import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/vip/data/datasources/vip_remote_data_source.dart';
import 'package:civin/features/vip/data/models/vip_model.dart';
import 'package:civin/features/vip/data/repositories/vip_repository_impl.dart';
import 'package:civin/features/vip/domain/entities/vip.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late _FakeRemote remote;
  late VipRepositoryImpl repository;

  setUp(() {
    remote = _FakeRemote();
    repository = VipRepositoryImpl(remote);
  });

  test('loads VIP levels through remote datasource', () async {
    final RepositoryResult<List<VipLevel>> result = await repository.getLevels();

    expect(result, isA<RepositorySuccess<List<VipLevel>>>());
    final List<VipLevel> levels =
        (result as RepositorySuccess<List<VipLevel>>).data;
    expect(levels, hasLength(2));
    expect(levels.first.name, 'Bronze');
    expect(levels.first.coinPrice, 500);
    expect(levels.first.privileges.badge, 'bronze-badge');
  });

  test('loads current VIP subscription', () async {
    final RepositoryResult<VipSubscription> result = await repository
        .getMyVip();

    expect(result, isA<RepositorySuccess<VipSubscription>>());
    final VipSubscription subscription =
        (result as RepositorySuccess<VipSubscription>).data;
    expect(subscription.isVip, isTrue);
    expect(subscription.level?.name, 'Bronze');
    expect(subscription.expirationLabel, '2026-08-22');
  });

  test('purchases VIP level', () async {
    final RepositoryResult<VipSubscription> result = await repository.purchase(
      vipLevelId: 'level-1',
      metadata: const <String, dynamic>{'source': 'test'},
    );

    expect(result, isA<RepositorySuccess<VipSubscription>>());
    expect(remote.lastPurchaseId, 'level-1');
    expect(
      (result as RepositorySuccess<VipSubscription>).data.isVip,
      isTrue,
    );
  });

  test('upgrades VIP level', () async {
    final RepositoryResult<VipSubscription> result = await repository.upgrade(
      vipLevelId: 'level-2',
    );

    expect(result, isA<RepositorySuccess<VipSubscription>>());
    expect(remote.lastUpgradeId, 'level-2');
    expect(
      (result as RepositorySuccess<VipSubscription>).data.level?.name,
      'Gold',
    );
  });

  test('maps remote failures to repository failure', () async {
    remote.error = DioException(
      requestOptions: RequestOptions(path: '/api/v1/vip/levels'),
      response: Response<dynamic>(
        requestOptions: RequestOptions(path: '/api/v1/vip/levels'),
        statusCode: 422,
        data: <String, dynamic>{'message': 'VIP unavailable.'},
      ),
    );

    final RepositoryResult<List<VipLevel>> failed = await repository.getLevels();

    expect(failed, isA<RepositoryFailure<List<VipLevel>>>());
    expect(
      (failed as RepositoryFailure<List<VipLevel>>).failure,
      isA<NetworkFailure>(),
    );
    expect(failed.failure.message, 'VIP unavailable.');
  });

  test('parses VIP level and subscription json', () {
    final VipLevel level = VipModel.levelFromJson(<String, dynamic>{
      'id': 'lvl-1',
      'name': 'Silver',
      'level': 2,
      'coin_price': 900,
      'duration_days': 30,
      'status': 'active',
      'sort_order': 2,
      'privileges': <String, dynamic>{
        'badge': 'silver-badge',
        'profile_frame': 'silver-frame',
        'chat_effect': 'silver-chat',
        'entrance_animation': 'silver-entrance',
        'exclusive_gifts': true,
      },
    });

    expect(level.name, 'Silver');
    expect(level.privileges.exclusiveGifts, isTrue);
    expect(level.privileges.benefitLabels, contains('Exclusive gifts'));

    final VipSubscription subscription = VipModel.subscriptionFromJson(
      <String, dynamic>{
        'id': 'sub-1',
        'is_vip': true,
        'status': 'active',
        'started_at': '2026-07-01T00:00:00.000000Z',
        'expires_at': '2026-08-01T00:00:00.000000Z',
        'level': <String, dynamic>{
          'id': 'lvl-1',
          'name': 'Silver',
          'level': 2,
          'coin_price': 900,
          'duration_days': 30,
          'privileges': <String, dynamic>{
            'badge': 'silver-badge',
            'exclusive_gifts': true,
          },
        },
        'privileges': <String, dynamic>{
          'badge': 'silver-badge',
          'exclusive_gifts': true,
        },
      },
    );

    expect(subscription.isVip, isTrue);
    expect(subscription.level?.level, 2);
    expect(subscription.expirationLabel, '2026-08-01');
  });
}

final class _FakeRemote implements VipRemoteDataSource {
  Object? error;
  String? lastPurchaseId;
  String? lastUpgradeId;

  static const VipLevel bronze = VipLevel(
    id: 'level-1',
    name: 'Bronze',
    level: 1,
    coinPrice: 500,
    durationDays: 30,
    privileges: VipPrivileges(badge: 'bronze-badge'),
  );

  static const VipLevel gold = VipLevel(
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
  Future<List<VipLevel>> getLevels() async {
    if (error != null) throw error!;
    return const <VipLevel>[bronze, gold];
  }

  @override
  Future<VipSubscription> getMyVip() async {
    if (error != null) throw error!;
    return VipSubscription(
      id: 'sub-1',
      isVip: true,
      status: VipStatus.active,
      startedAt: DateTime.utc(2026, 7, 22),
      expiresAt: DateTime.utc(2026, 8, 22),
      level: bronze,
      privileges: bronze.privileges,
    );
  }

  @override
  Future<VipSubscription> purchase({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  }) async {
    if (error != null) throw error!;
    lastPurchaseId = vipLevelId;
    return VipSubscription(
      id: 'sub-new',
      isVip: true,
      status: VipStatus.active,
      startedAt: DateTime.utc(2026, 7, 22),
      expiresAt: DateTime.utc(2026, 8, 22),
      level: bronze,
      privileges: bronze.privileges,
    );
  }

  @override
  Future<VipSubscription> upgrade({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  }) async {
    if (error != null) throw error!;
    lastUpgradeId = vipLevelId;
    return VipSubscription(
      id: 'sub-1',
      isVip: true,
      status: VipStatus.active,
      startedAt: DateTime.utc(2026, 7, 22),
      expiresAt: DateTime.utc(2026, 8, 22),
      level: gold,
      privileges: gold.privileges,
    );
  }
}
