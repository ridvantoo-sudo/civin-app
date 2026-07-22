import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/wallet/data/datasources/wallet_realtime_data_source.dart';
import 'package:civin/features/wallet/data/datasources/wallet_remote_data_source.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:civin/features/wallet/domain/repositories/wallet_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<WalletRepository> walletRepositoryProvider =
    Provider<WalletRepository>(
      (Ref ref) => WalletRepositoryImpl(
        ref.watch(walletRemoteDataSourceProvider),
        ref.watch(walletRealtimeDataSourceProvider),
      ),
    );

final class WalletRepositoryImpl extends BaseRepository
    implements WalletRepository {
  WalletRepositoryImpl(this._remote, this._realtime);

  final WalletRemoteDataSource _remote;
  final WalletRealtimeDataSource _realtime;

  @override
  Future<RepositoryResult<WalletBalance>> getWallet() =>
      execute(_remote.getWallet);

  @override
  Future<RepositoryResult<List<WalletTransaction>>> getTransactions({
    int perPage = 30,
  }) => execute(() => _remote.getTransactions(perPage: perPage));

  @override
  Future<RepositoryResult<RechargeOrder>> recharge({
    required String packageName,
    required int coins,
    required int price,
    required String currency,
    required String paymentProvider,
    required String transactionId,
    Map<String, dynamic>? metadata,
  }) => execute(
    () => _remote.recharge(
      packageName: packageName,
      coins: coins,
      price: price,
      currency: currency,
      paymentProvider: paymentProvider,
      transactionId: transactionId,
      metadata: metadata,
    ),
  );

  @override
  Future<RepositoryResult<WithdrawRequest>> requestWithdraw({
    required int diamonds,
    required int amount,
    Map<String, dynamic>? metadata,
  }) => execute(
    () => _remote.requestWithdraw(
      diamonds: diamonds,
      amount: amount,
      metadata: metadata,
    ),
  );

  @override
  Stream<WalletUpdatedEvent> watchWalletUpdated() => _realtime.walletUpdated;

  @override
  Future<void> connectRealtime(String userId) => _realtime.connect(userId);

  @override
  Future<void> disconnectRealtime() => _realtime.disconnect();
}
