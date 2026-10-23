import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/wallet/data/datasources/wallet_realtime_data_source.dart';
import 'package:civin/features/wallet/data/datasources/wallet_remote_data_source.dart';
import 'package:civin/features/wallet/data/repositories/wallet_repository_impl.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late _FakeRemote remote;
  late _FakeRealtime realtime;
  late WalletRepositoryImpl repository;

  setUp(() {
    remote = _FakeRemote();
    realtime = _FakeRealtime();
    repository = WalletRepositoryImpl(remote, realtime);
  });

  test('loads wallet balance through remote datasource', () async {
    final RepositoryResult<WalletBalance> result = await repository.getWallet();
    expect(result, isA<RepositorySuccess<WalletBalance>>());
    expect(
      (result as RepositorySuccess<WalletBalance>).data.coinsBalance,
      1200,
    );
    expect(result.data.diamondsBalance, 80);
  });

  test('loads transactions and maps remote failures', () async {
    final RepositoryResult<List<WalletTransaction>> history = await repository
        .getTransactions();
    expect(history, isA<RepositorySuccess<List<WalletTransaction>>>());
    expect(
      (history as RepositorySuccess<List<WalletTransaction>>).data.single.type,
      WalletTransactionType.coinPurchase,
    );

    remote.error = DioException(
      requestOptions: RequestOptions(path: '/api/v1/wallet/recharge'),
      response: Response<dynamic>(
        requestOptions: RequestOptions(path: '/api/v1/wallet/recharge'),
        statusCode: 422,
        data: <String, dynamic>{'message': 'Invalid payment'},
      ),
    );

    final RepositoryResult<RechargeOrder> failed = await repository.recharge(
      packageName: 'Starter Pack',
      coins: 100,
      price: 99,
      currency: 'USD',
      paymentProvider: 'stripe',
      transactionId: 'txn_fail',
    );
    expect(failed, isA<RepositoryFailure<RechargeOrder>>());
    expect(
      (failed as RepositoryFailure<RechargeOrder>).failure,
      isA<NetworkFailure>(),
    );
    expect(failed.failure.message, 'Invalid payment');
  });

  test('recharges and requests withdraw through remote', () async {
    final RepositoryResult<RechargeOrder> order = await repository.recharge(
      packageName: 'Mega Pack',
      coins: 1200,
      price: 999,
      currency: 'USD',
      paymentProvider: 'app_store',
      transactionId: 'txn_1',
    );
    expect(order, isA<RepositorySuccess<RechargeOrder>>());
    expect(
      (order as RepositorySuccess<RechargeOrder>).data.packageName,
      'Mega Pack',
    );
    expect(remote.lastTransactionId, 'txn_1');

    final RepositoryResult<WithdrawRequest> withdraw = await repository
        .requestWithdraw(diamonds: 50, amount: 750);
    expect(withdraw, isA<RepositorySuccess<WithdrawRequest>>());
    expect(
      (withdraw as RepositorySuccess<WithdrawRequest>).data.status,
      WithdrawStatus.pending,
    );
    expect(remote.lastWithdrawDiamonds, 50);
  });

  test('connects realtime channel and exposes wallet.updated stream', () async {
    await repository.connectRealtime('user-1');
    expect(realtime.connectedUserId, 'user-1');

    final Future<void> expectation = expectLater(
      repository.watchWalletUpdated(),
      emits(
        isA<WalletUpdatedEvent>().having(
          (WalletUpdatedEvent e) => e.coinsBalance,
          'coinsBalance',
          1500,
        ),
      ),
    );
    realtime.emit(
      const WalletUpdatedEvent(
        walletId: 'w1',
        userId: 'user-1',
        coinsBalance: 1500,
        diamondsBalance: 80,
      ),
    );
    await expectation;
  });
}

final class _FakeRemote implements WalletRemoteDataSource {
  Object? error;
  String? lastTransactionId;
  int? lastWithdrawDiamonds;

  static const WalletBalance sample = WalletBalance(
    id: 'w1',
    userId: 'user-1',
    coinsBalance: 1200,
    diamondsBalance: 80,
  );

  @override
  Future<WalletBalance> getWallet() async {
    _throwIfNeeded();
    return sample;
  }

  @override
  Future<List<WalletTransaction>> getTransactions({int perPage = 30}) async {
    _throwIfNeeded();
    return <WalletTransaction>[
      WalletTransaction(
        id: 'tx-1',
        userId: 'user-1',
        type: WalletTransactionType.coinPurchase,
        amount: 500,
        currency: WalletCurrency.coins,
        createdAt: DateTime.utc(2026, 7, 22),
      ),
    ];
  }

  @override
  Future<RechargeOrder> recharge({
    required String packageName,
    required int coins,
    required int price,
    required String currency,
    required String paymentProvider,
    required String transactionId,
    Map<String, dynamic>? metadata,
  }) async {
    _throwIfNeeded();
    lastTransactionId = transactionId;
    return RechargeOrder(
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
    );
  }

  @override
  Future<WithdrawRequest> requestWithdraw({
    required int diamonds,
    required int amount,
    Map<String, dynamic>? metadata,
  }) async {
    _throwIfNeeded();
    lastWithdrawDiamonds = diamonds;
    return WithdrawRequest(
      id: 'wd-1',
      userId: 'user-1',
      diamonds: diamonds,
      amount: amount,
      status: WithdrawStatus.pending,
      createdAt: DateTime.utc(2026, 7, 22),
    );
  }

  void _throwIfNeeded() {
    final Object? current = error;
    if (current != null) throw current;
  }
}

final class _FakeRealtime implements WalletRealtimeDataSource {
  // ignore: close_sinks
  final StreamController<WalletUpdatedEvent> _updated =
      StreamController<WalletUpdatedEvent>.broadcast();

  String? connectedUserId;

  @override
  Stream<WalletUpdatedEvent> get walletUpdated => _updated.stream;

  @override
  Future<void> connect(String userId) async {
    connectedUserId = userId;
  }

  @override
  Future<void> disconnect() async {
    connectedUserId = null;
  }

  void emit(WalletUpdatedEvent event) => _updated.add(event);
}
