import 'package:civin/features/wallet/domain/entities/wallet.dart';

abstract final class WalletModel {
  static WalletBalance balanceFromJson(Map<String, dynamic> json) =>
      WalletBalance(
        id: json['id']?.toString() ?? '',
        userId: json['user_id']?.toString() ?? '',
        coinsBalance: _int(json['coins_balance']),
        diamondsBalance: _int(json['diamonds_balance']),
        createdAt: _date(json['created_at']),
        updatedAt: _date(json['updated_at']),
      );

  static WalletTransaction transactionFromJson(Map<String, dynamic> json) =>
      WalletTransaction(
        id: json['id']?.toString() ?? '',
        userId: json['user_id']?.toString() ?? '',
        type: _transactionType(json['type'] as String?),
        amount: _int(json['amount']),
        currency: _currency(json['currency'] as String?),
        referenceType: json['reference_type'] as String?,
        referenceId: json['reference_id']?.toString(),
        metadata: json['metadata'] is Map<String, dynamic>
            ? Map<String, dynamic>.from(json['metadata'] as Map)
            : null,
        createdAt: _date(json['created_at']) ?? DateTime.now().toUtc(),
      );

  static List<WalletTransaction> transactionsFromJson(List<dynamic> items) =>
      items
          .map((dynamic item) {
            if (item is! Map<String, dynamic>) {
              throw const FormatException('Invalid wallet transaction item.');
            }
            return transactionFromJson(item);
          })
          .toList(growable: false);

  static RechargeOrder rechargeOrderFromJson(Map<String, dynamic> json) =>
      RechargeOrder(
        id: json['id']?.toString() ?? '',
        userId: json['user_id']?.toString() ?? '',
        packageName: json['package_name'] as String? ?? 'Package',
        coins: _int(json['coins']),
        price: _int(json['price']),
        currency: json['currency'] as String? ?? 'USD',
        status: _rechargeStatus(json['status'] as String?),
        paymentProvider: json['payment_provider'] as String? ?? '',
        transactionId: json['transaction_id'] as String? ?? '',
        createdAt: _date(json['created_at']) ?? DateTime.now().toUtc(),
      );

  static WithdrawRequest withdrawFromJson(Map<String, dynamic> json) =>
      WithdrawRequest(
        id: json['id']?.toString() ?? '',
        userId: json['user_id']?.toString() ?? '',
        diamonds: _int(json['diamonds']),
        amount: _int(json['amount']),
        status: _withdrawStatus(json['status'] as String?),
        approvedBy: json['approved_by']?.toString(),
        createdAt: _date(json['created_at']) ?? DateTime.now().toUtc(),
      );

  static WalletUpdatedEvent updatedEventFromJson(Map<String, dynamic> json) =>
      WalletUpdatedEvent(
        walletId: json['wallet_id']?.toString() ?? json['id']?.toString() ?? '',
        userId: json['user_id']?.toString() ?? '',
        coinsBalance: _int(json['coins_balance']),
        diamondsBalance: _int(json['diamonds_balance']),
        updatedAt: _date(json['updated_at']),
      );

  static WalletTransactionType _transactionType(String? value) =>
      switch (value?.toUpperCase()) {
        'COIN_PURCHASE' => WalletTransactionType.coinPurchase,
        'GIFT_SENT' => WalletTransactionType.giftSent,
        'GIFT_RECEIVED' => WalletTransactionType.giftReceived,
        'PK_REWARD' => WalletTransactionType.pkReward,
        'WITHDRAW' => WalletTransactionType.withdraw,
        'ADMIN_ADJUSTMENT' => WalletTransactionType.adminAdjustment,
        _ => WalletTransactionType.unknown,
      };

  static WalletCurrency _currency(String? value) =>
      switch (value?.toLowerCase()) {
        'coins' => WalletCurrency.coins,
        'diamonds' => WalletCurrency.diamonds,
        _ => WalletCurrency.unknown,
      };

  static RechargeOrderStatus _rechargeStatus(String? value) =>
      switch (value?.toLowerCase()) {
        'pending' => RechargeOrderStatus.pending,
        'completed' => RechargeOrderStatus.completed,
        'failed' => RechargeOrderStatus.failed,
        _ => RechargeOrderStatus.unknown,
      };

  static WithdrawStatus _withdrawStatus(String? value) =>
      switch (value?.toLowerCase()) {
        'pending' => WithdrawStatus.pending,
        'approved' => WithdrawStatus.approved,
        'rejected' => WithdrawStatus.rejected,
        _ => WithdrawStatus.unknown,
      };

  static int _int(Object? value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }

  static DateTime? _date(Object? value) {
    if (value is DateTime) return value;
    if (value is String && value.isNotEmpty) {
      return DateTime.tryParse(value);
    }
    return null;
  }
}
