import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/vip/data/repositories/vip_repository_impl.dart';
import 'package:civin/features/vip/domain/entities/vip.dart';
import 'package:civin/features/vip/domain/repositories/vip_repository.dart';
import 'package:civin/features/vip/presentation/screens/vip_home_screen.dart';
import 'package:civin/features/vip/presentation/screens/vip_levels_screen.dart';
import 'package:civin/features/vip/presentation/screens/vip_profile_badge_screen.dart';
import 'package:civin/features/vip/presentation/screens/vip_purchase_screen.dart';
import 'package:civin/features/vip/presentation/widgets/vip_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  ThemeData darkTheme() => ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    colorScheme: ColorScheme.fromSeed(
      seedColor: const Color(0xFFD4A017),
      brightness: Brightness.dark,
    ),
  );

  Future<void> pumpVip(
    WidgetTester tester,
    Widget home, {
    VipRepository? repository,
  }) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          vipRepositoryProvider.overrideWithValue(
            repository ?? _FakeVipRepository(),
          ),
        ],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: darkTheme(),
          home: home,
        ),
      ),
    );
    // Finite pumps — VIP badge pulse uses a repeating ticker.
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    await tester.pump(const Duration(milliseconds: 700));
  }

  testWidgets('VipHome shows current VIP, benefits, and actions', (
    WidgetTester tester,
  ) async {
    await pumpVip(tester, const VipHome());

    expect(find.text('VIP'), findsWidgets);
    expect(find.text('Current VIP'), findsOneWidget);
    expect(find.text('Bronze'), findsWidgets);
    expect(find.text('Benefits'), findsOneWidget);
    expect(find.text('VIP badge'), findsWidgets);
    expect(find.text('Expiration date'), findsOneWidget);
    expect(find.text('2026-08-22'), findsOneWidget);
    expect(find.text('Upgrade'), findsOneWidget);
    expect(find.text('VIP levels'), findsOneWidget);
  });

  testWidgets('VipLevels lists premium cards', (WidgetTester tester) async {
    await pumpVip(tester, const VipLevels());

    expect(find.text('VIP levels'), findsOneWidget);
    expect(find.text('Bronze'), findsWidgets);
    expect(find.text('Gold'), findsWidgets);
    expect(find.text('500 coins'), findsOneWidget);
    expect(find.text('2000 coins'), findsOneWidget);
    expect(find.byType(VipLevelCard), findsNWidgets(2));
  });

  testWidgets('VipPurchase shows purchase button for non-VIP', (
    WidgetTester tester,
  ) async {
    final _FakeVipRepository repository = _FakeVipRepository()
      ..subscription = const VipSubscription(isVip: false);
    await pumpVip(tester, const VipPurchase(), repository: repository);

    expect(find.text('Purchase VIP'), findsOneWidget);
    expect(find.text('Not a VIP yet'), findsOneWidget);
    expect(find.text('Bronze'), findsWidgets);
    expect(find.text('500 coins'), findsWidgets);
    expect(find.widgetWithText(FilledButton, 'Purchase - 500 coins'), findsOneWidget);
  });

  testWidgets('VipProfileBadge and chat badge render', (
    WidgetTester tester,
  ) async {
    await pumpVip(tester, const VipProfileBadge());

    expect(find.text('VIP badge'), findsOneWidget);
    expect(find.text('Profile integration'), findsOneWidget);
    expect(find.text('Chat badge integration'), findsOneWidget);
    expect(find.byType(VipBadge), findsWidgets);
    expect(find.byType(VipChatBadge), findsOneWidget);
    expect(find.text('VIP 1'), findsOneWidget);
  });

  testWidgets('VipBadge and VipChatBadge display labels', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        themeMode: ThemeMode.dark,
        darkTheme: darkTheme(),
        home: const Scaffold(
          body: Column(
            children: <Widget>[
              VipBadge(level: 2, levelName: 'Silver', animated: false),
              VipChatBadge(level: 2),
            ],
          ),
        ),
      ),
    );

    await tester.pump();
    expect(find.text('Silver'), findsOneWidget);
    expect(find.text('VIP 2'), findsOneWidget);
  });
}

final class _FakeVipRepository implements VipRepository {
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
  Future<RepositoryResult<List<VipLevel>>> getLevels() async =>
      const RepositorySuccess<List<VipLevel>>(<VipLevel>[_bronze, _gold]);

  @override
  Future<RepositoryResult<VipSubscription>> getMyVip() async =>
      RepositorySuccess<VipSubscription>(subscription);

  @override
  Future<RepositoryResult<VipSubscription>> purchase({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  }) async => RepositorySuccess<VipSubscription>(subscription);

  @override
  Future<RepositoryResult<VipSubscription>> upgrade({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  }) async => RepositorySuccess<VipSubscription>(subscription);
}
