import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';

abstract interface class WalletRepository {
  Future<RepositoryResult<WalletBalance>> getWallet();

  Future<RepositoryResult<List<WalletTransaction>>> getTransactions({
    int perPage = 30,
  });

  Future<RepositoryResult<RechargeOrder>> recharge({
    required String packageName,
    required int coins,
    required int price,
    required String currency,
    required String paymentProvider,
    required String transactionId,
    Map<String, dynamic>? metadata,
  });

  Future<RepositoryResult<WithdrawRequest>> requestWithdraw({
    required int diamonds,
    required int amount,
    Map<String, dynamic>? metadata,
  });

  Stream<WalletUpdatedEvent> watchWalletUpdated();

  Future<void> connectRealtime(String userId);

  Future<void> disconnectRealtime();
}
