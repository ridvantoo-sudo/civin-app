import 'package:civin/core/network/dio_client.dart';
import 'package:civin/features/wallet/data/models/wallet_model.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<WalletRemoteDataSource> walletRemoteDataSourceProvider =
    Provider<WalletRemoteDataSource>(
      (Ref ref) => DioWalletRemoteDataSource(ref.watch(dioClientProvider)),
    );

abstract interface class WalletRemoteDataSource {
  Future<WalletBalance> getWallet();

  Future<List<WalletTransaction>> getTransactions({int perPage = 30});

  Future<RechargeOrder> recharge({
    required String packageName,
    required int coins,
    required int price,
    required String currency,
    required String paymentProvider,
    required String transactionId,
    Map<String, dynamic>? metadata,
  });

  Future<WithdrawRequest> requestWithdraw({
    required int diamonds,
    required int amount,
    Map<String, dynamic>? metadata,
  });
}

final class DioWalletRemoteDataSource implements WalletRemoteDataSource {
  const DioWalletRemoteDataSource(this._client);

  final DioClient _client;

  @override
  Future<WalletBalance> getWallet() async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/wallet',
    );
    return WalletModel.balanceFromJson(_data(response));
  }

  @override
  Future<List<WalletTransaction>> getTransactions({int perPage = 30}) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/wallet/transactions',
      queryParameters: <String, dynamic>{'per_page': perPage},
    );
    final Object? data = _body(response)['data'];
    if (data is! List<dynamic>) {
      throw const FormatException('Invalid wallet transactions response.');
    }
    return WalletModel.transactionsFromJson(data);
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
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/wallet/recharge',
      data: <String, dynamic>{
        'package_name': packageName,
        'coins': coins,
        'price': price,
        'currency': currency,
        'payment_provider': paymentProvider,
        'transaction_id': transactionId,
        'metadata': ?metadata,
      },
    );
    return WalletModel.rechargeOrderFromJson(_data(response));
  }

  @override
  Future<WithdrawRequest> requestWithdraw({
    required int diamonds,
    required int amount,
    Map<String, dynamic>? metadata,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/wallet/withdraw',
      data: <String, dynamic>{
        'diamonds': diamonds,
        'amount': amount,
        'metadata': ?metadata,
      },
    );
    return WalletModel.withdrawFromJson(_data(response));
  }

  Map<String, dynamic> _body(Response<dynamic> response) {
    final Object? data = response.data;
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid API envelope.');
  }

  Map<String, dynamic> _data(Response<dynamic> response) {
    final Object? data = _body(response)['data'];
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid API data payload.');
  }
}
