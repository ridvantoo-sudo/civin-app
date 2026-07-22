import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/wallet/data/repositories/wallet_repository_impl.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:civin/features/wallet/domain/repositories/wallet_repository.dart';
import 'package:civin/features/wallet/presentation/screens/recharge_screen.dart';
import 'package:civin/features/wallet/presentation/screens/wallet_screen.dart';
import 'package:civin/features/wallet/presentation/widgets/recharge_package_card.dart';
import 'package:civin/features/wallet/presentation/widgets/transaction_tile.dart';
import 'package:civin/features/wallet/presentation/widgets/wallet_balance_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('wallet screen shows coins, diamonds, and actions', (
    WidgetTester tester,
  ) async {
    final _FakeWalletRepository repository = _FakeWalletRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [walletRepositoryProvider.overrideWithValue(repository)],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData(
            useMaterial3: true,
            brightness: Brightness.dark,
            colorScheme: ColorScheme.fromSeed(
              seedColor: const Color(0xFF6C4DFF),
              brightness: Brightness.dark,
            ),
          ),
          home: const WalletScreen(),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('Wallet'), findsOneWidget);
    expect(find.text('Coins'), findsOneWidget);
    expect(find.text('Diamonds'), findsOneWidget);
    expect(find.text('1,200'), findsOneWidget);
    expect(find.text('80'), findsOneWidget);
    expect(find.text('Recharge'), findsOneWidget);
    expect(find.text('Withdraw'), findsOneWidget);
    expect(find.text('Coin purchase'), findsOneWidget);
  });

  testWidgets('recharge screen lists packages and can purchase', (
    WidgetTester tester,
  ) async {
    final _FakeWalletRepository repository = _FakeWalletRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [walletRepositoryProvider.overrideWithValue(repository)],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData.dark(useMaterial3: true),
          home: const RechargeScreen(),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('Choose a package'), findsOneWidget);
    expect(find.text('Starter Pack'), findsOneWidget);
    expect(find.text('Mega Pack'), findsOneWidget);
    expect(find.byType(RechargePackageCard), findsWidgets);

    await tester.tap(find.text('Mega Pack'));
    await tester.pumpAndSettle();
    await tester.tap(find.textContaining('Buy'));
    await tester.pumpAndSettle();

    expect(repository.lastPackageName, 'Mega Pack');
    expect(find.textContaining('Recharge completed'), findsOneWidget);
  });

  testWidgets('wallet balance card and transaction tile render values', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        themeMode: ThemeMode.dark,
        darkTheme: ThemeData.dark(useMaterial3: true),
        home: Scaffold(
          body: Column(
            children: [
              const WalletBalanceCard(
                balance: WalletBalance(
                  id: 'w1',
                  userId: 'u1',
                  coinsBalance: 42,
                  diamondsBalance: 7,
                ),
              ),
              TransactionTile(
                transaction: WalletTransaction(
                  id: 'tx-1',
                  userId: 'u1',
                  type: WalletTransactionType.giftSent,
                  amount: -10,
                  currency: WalletCurrency.coins,
                  createdAt: DateTime.utc(2026, 7, 22, 12),
                ),
              ),
            ],
          ),
        ),
      ),
    );

    await tester.pumpAndSettle();
    expect(find.text('42'), findsOneWidget);
    expect(find.text('7'), findsOneWidget);
    expect(find.text('Gift sent'), findsOneWidget);
    expect(find.text('-10 coins'), findsOneWidget);
  });
}

final class _FakeWalletRepository implements WalletRepository {
  // ignore: close_sinks
  final StreamController<WalletUpdatedEvent> _updated =
      StreamController<WalletUpdatedEvent>.broadcast();

  String? lastPackageName;

  @override
  Future<RepositoryResult<WalletBalance>> getWallet() async =>
      const RepositorySuccess<WalletBalance>(
        WalletBalance(
          id: 'w1',
          userId: 'user-1',
          coinsBalance: 1200,
          diamondsBalance: 80,
        ),
      );

  @override
  Future<RepositoryResult<List<WalletTransaction>>> getTransactions({
    int perPage = 30,
  }) async => RepositorySuccess<List<WalletTransaction>>(
    <WalletTransaction>[
      WalletTransaction(
        id: 'tx-1',
        userId: 'user-1',
        type: WalletTransactionType.coinPurchase,
        amount: 500,
        currency: WalletCurrency.coins,
        createdAt: DateTime.utc(2026, 7, 22),
      ),
    ],
  );

  @override
  Future<RepositoryResult<RechargeOrder>> recharge({
    required String packageName,
    required int coins,
    required int price,
    required String currency,
    required String paymentProvider,
    required String transactionId,
    Map<String, dynamic>? metadata,
  }) async {
    lastPackageName = packageName;
    return RepositorySuccess<RechargeOrder>(
      RechargeOrder(
        id: 'ro-1',
        userId: 'user-1',
        packageName: packageName,
        coins: coins,
        price: price,
        currency: currency,
        status: RechargeOrderStatus.completed,
        paymentProvider: paymentProvider,
        transactionId: transactionId,
        createdAt: DateTime.utc(2026, 7, 22),
      ),
    );
  }

  @override
  Future<RepositoryResult<WithdrawRequest>> requestWithdraw({
    required int diamonds,
    required int amount,
    Map<String, dynamic>? metadata,
  }) async => RepositorySuccess<WithdrawRequest>(
    WithdrawRequest(
      id: 'wd-1',
      userId: 'user-1',
      diamonds: diamonds,
      amount: amount,
      status: WithdrawStatus.pending,
      createdAt: DateTime.utc(2026, 7, 22),
    ),
  );

  @override
  Stream<WalletUpdatedEvent> watchWalletUpdated() => _updated.stream;

  @override
  Future<void> connectRealtime(String userId) async {}

  @override
  Future<void> disconnectRealtime() async {}
}
