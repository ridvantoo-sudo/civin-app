import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:civin/features/wallet/domain/repositories/wallet_repository.dart';

final class GetWallet {
  const GetWallet(this._repository);

  final WalletRepository _repository;

  Future<RepositoryResult<WalletBalance>> call() => _repository.getWallet();
}

final class GetWalletTransactions {
  const GetWalletTransactions(this._repository);

  final WalletRepository _repository;

  Future<RepositoryResult<List<WalletTransaction>>> call({
    int perPage = 30,
  }) => _repository.getTransactions(perPage: perPage);
}

final class RechargeWallet {
  const RechargeWallet(this._repository);

  final WalletRepository _repository;

  Future<RepositoryResult<RechargeOrder>> call({
    required String packageName,
    required int coins,
    required int price,
    required String currency,
    required String paymentProvider,
    required String transactionId,
    Map<String, dynamic>? metadata,
  }) => _repository.recharge(
    packageName: packageName,
    coins: coins,
    price: price,
    currency: currency,
    paymentProvider: paymentProvider,
    transactionId: transactionId,
    metadata: metadata,
  );
}

final class RequestWalletWithdraw {
  const RequestWalletWithdraw(this._repository);

  final WalletRepository _repository;

  Future<RepositoryResult<WithdrawRequest>> call({
    required int diamonds,
    required int amount,
    Map<String, dynamic>? metadata,
  }) => _repository.requestWithdraw(
    diamonds: diamonds,
    amount: amount,
    metadata: metadata,
  );
}

final class ConnectWalletRealtime {
  const ConnectWalletRealtime(this._repository);

  final WalletRepository _repository;

  Future<void> call(String userId) => _repository.connectRealtime(userId);
}

final class DisconnectWalletRealtime {
  const DisconnectWalletRealtime(this._repository);

  final WalletRepository _repository;

  Future<void> call() => _repository.disconnectRealtime();
}
