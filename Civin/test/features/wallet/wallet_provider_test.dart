import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/wallet/data/repositories/wallet_repository_impl.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:civin/features/wallet/domain/repositories/wallet_repository.dart';
import 'package:civin/features/wallet/presentation/wallet_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('walletProvider loads balance and listens for WalletUpdated', () async {
    final _FakeWalletRepository repository = _FakeWalletRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [walletRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container.read(walletProvider.notifier).load();
    expect(container.read(walletProvider).coins, 1200);
    expect(container.read(walletProvider).diamonds, 80);
    expect(repository.connectedUserId, 'user-1');
    expect(container.read(walletProvider).isListening, isTrue);

    repository.emit(
      const WalletUpdatedEvent(
        walletId: 'w1',
        userId: 'user-1',
        coinsBalance: 1500,
        diamondsBalance: 90,
      ),
    );
    await Future<void>.delayed(Duration.zero);

    final WalletViewState state = container.read(walletProvider);
    expect(state.coins, 1500);
    expect(state.diamonds, 90);
  });

  test('transactionProvider loads ledger entries', () async {
    final _FakeWalletRepository repository = _FakeWalletRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [walletRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container.read(transactionProvider.notifier).load();
    expect(container.read(transactionProvider).items, hasLength(1));
    expect(
      container.read(transactionProvider).items.single.type,
      WalletTransactionType.coinPurchase,
    );
  });

  test('rechargeProvider purchases selected package', () async {
    final _FakeWalletRepository repository = _FakeWalletRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [walletRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    container.read(rechargeProvider.notifier).selectPackage('mega');
    final bool ok = await container
        .read(rechargeProvider.notifier)
        .purchase(transactionId: 'txn_test');

    expect(ok, isTrue);
    expect(repository.lastPackageName, 'Mega Pack');
    expect(repository.lastCoins, 1300);
    expect(container.read(rechargeProvider).lastOrder?.coins, 1300);
  });

  test('rechargeProvider surfaces purchase failures', () async {
    final _FakeWalletRepository repository = _FakeWalletRepository()
      ..failRecharge = true;
    final ProviderContainer container = ProviderContainer(
      overrides: [walletRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    final bool ok = await container.read(rechargeProvider.notifier).purchase();
    expect(ok, isFalse);
    expect(
      container.read(rechargeProvider).errorMessage,
      'Payment declined',
    );
  });
}

final class _FakeWalletRepository implements WalletRepository {
  // ignore: close_sinks
  final StreamController<WalletUpdatedEvent> _updated =
      StreamController<WalletUpdatedEvent>.broadcast();

  String? connectedUserId;
  String? lastPackageName;
  int? lastCoins;
  bool failRecharge = false;

  static const WalletBalance balance = WalletBalance(
    id: 'w1',
    userId: 'user-1',
    coinsBalance: 1200,
    diamondsBalance: 80,
  );

  @override
  Future<RepositoryResult<WalletBalance>> getWallet() async =>
      const RepositorySuccess<WalletBalance>(balance);

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
    if (failRecharge) {
      return const RepositoryFailure<RechargeOrder>(
        AppFailure.network(message: 'Payment declined'),
      );
    }
    lastPackageName = packageName;
    lastCoins = coins;
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
  Future<void> connectRealtime(String userId) async {
    connectedUserId = userId;
  }

  @override
  Future<void> disconnectRealtime() async {
    connectedUserId = null;
  }

  void emit(WalletUpdatedEvent event) => _updated.add(event);
}
