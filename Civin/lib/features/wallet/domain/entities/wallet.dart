enum WalletCurrency { coins, diamonds, unknown }

enum WalletTransactionType {
  coinPurchase,
  giftSent,
  giftReceived,
  pkReward,
  withdraw,
  adminAdjustment,
  unknown,
}

enum WithdrawStatus { pending, approved, rejected, unknown }

enum RechargeOrderStatus { pending, completed, failed, unknown }

final class WalletBalance {
  const WalletBalance({
    required this.id,
    required this.userId,
    required this.coinsBalance,
    required this.diamondsBalance,
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String userId;
  final int coinsBalance;
  final int diamondsBalance;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  WalletBalance copyWith({
    String? id,
    String? userId,
    int? coinsBalance,
    int? diamondsBalance,
    DateTime? createdAt,
    DateTime? updatedAt,
  }) => WalletBalance(
    id: id ?? this.id,
    userId: userId ?? this.userId,
    coinsBalance: coinsBalance ?? this.coinsBalance,
    diamondsBalance: diamondsBalance ?? this.diamondsBalance,
    createdAt: createdAt ?? this.createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
  );
}

final class WalletTransaction {
  const WalletTransaction({
    required this.id,
    required this.userId,
    required this.type,
    required this.amount,
    required this.currency,
    required this.createdAt,
    this.referenceType,
    this.referenceId,
    this.metadata,
  });

  final String id;
  final String userId;
  final WalletTransactionType type;
  final int amount;
  final WalletCurrency currency;
  final String? referenceType;
  final String? referenceId;
  final Map<String, dynamic>? metadata;
  final DateTime createdAt;

  bool get isCredit => amount > 0;
}

final class RechargePackage {
  const RechargePackage({
    required this.id,
    required this.name,
    required this.coins,
    required this.price,
    this.currency = 'USD',
    this.badge,
    this.bonusCoins = 0,
  });

  final String id;
  final String name;
  final int coins;
  final int price;
  final String currency;
  final String? badge;
  final int bonusCoins;

  int get totalCoins => coins + bonusCoins;
}

final class RechargeOrder {
  const RechargeOrder({
    required this.id,
    required this.userId,
    required this.packageName,
    required this.coins,
    required this.price,
    required this.currency,
    required this.status,
    required this.paymentProvider,
    required this.transactionId,
    required this.createdAt,
  });

  final String id;
  final String userId;
  final String packageName;
  final int coins;
  final int price;
  final String currency;
  final RechargeOrderStatus status;
  final String paymentProvider;
  final String transactionId;
  final DateTime createdAt;
}

final class WithdrawRequest {
  const WithdrawRequest({
    required this.id,
    required this.userId,
    required this.diamonds,
    required this.amount,
    required this.status,
    required this.createdAt,
    this.approvedBy,
  });

  final String id;
  final String userId;
  final int diamonds;
  final int amount;
  final WithdrawStatus status;
  final String? approvedBy;
  final DateTime createdAt;
}

/// Broadcast payload for `wallet.updated` on `private-user.wallet.{userId}`.
final class WalletUpdatedEvent {
  const WalletUpdatedEvent({
    required this.walletId,
    required this.userId,
    required this.coinsBalance,
    required this.diamondsBalance,
    this.updatedAt,
  });

  final String walletId;
  final String userId;
  final int coinsBalance;
  final int diamondsBalance;
  final DateTime? updatedAt;

  WalletBalance toBalance() => WalletBalance(
    id: walletId,
    userId: userId,
    coinsBalance: coinsBalance,
    diamondsBalance: diamondsBalance,
    updatedAt: updatedAt,
  );
}

final class WalletViewState {
  const WalletViewState({
    this.balance,
    this.isListening = false,
    this.isLoading = false,
    this.recentWithdrawals = const <WithdrawRequest>[],
    this.errorMessage,
  });

  final WalletBalance? balance;
  final bool isListening;
  final bool isLoading;
  final List<WithdrawRequest> recentWithdrawals;
  final String? errorMessage;

  int get coins => balance?.coinsBalance ?? 0;
  int get diamonds => balance?.diamondsBalance ?? 0;

  WithdrawRequest? get latestWithdrawal =>
      recentWithdrawals.isEmpty ? null : recentWithdrawals.first;

  WalletViewState copyWith({
    WalletBalance? balance,
    bool? isListening,
    bool? isLoading,
    List<WithdrawRequest>? recentWithdrawals,
    String? errorMessage,
    bool clearError = false,
    bool clearBalance = false,
  }) => WalletViewState(
    balance: clearBalance ? null : balance ?? this.balance,
    isListening: isListening ?? this.isListening,
    isLoading: isLoading ?? this.isLoading,
    recentWithdrawals: recentWithdrawals ?? this.recentWithdrawals,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
  );
}

final class TransactionHistoryState {
  const TransactionHistoryState({
    this.items = const <WalletTransaction>[],
    this.isLoading = false,
    this.errorMessage,
  });

  final List<WalletTransaction> items;
  final bool isLoading;
  final String? errorMessage;

  TransactionHistoryState copyWith({
    List<WalletTransaction>? items,
    bool? isLoading,
    String? errorMessage,
    bool clearError = false,
  }) => TransactionHistoryState(
    items: items ?? this.items,
    isLoading: isLoading ?? this.isLoading,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
  );
}

final class RechargeState {
  const RechargeState({
    this.packages = const <RechargePackage>[],
    this.selectedPackageId,
    this.isSubmitting = false,
    this.lastOrder,
    this.errorMessage,
  });

  final List<RechargePackage> packages;
  final String? selectedPackageId;
  final bool isSubmitting;
  final RechargeOrder? lastOrder;
  final String? errorMessage;

  RechargePackage? get selectedPackage {
    final String? id = selectedPackageId;
    if (id == null) return null;
    for (final RechargePackage package in packages) {
      if (package.id == id) return package;
    }
    return null;
  }

  RechargeState copyWith({
    List<RechargePackage>? packages,
    String? selectedPackageId,
    bool? isSubmitting,
    RechargeOrder? lastOrder,
    String? errorMessage,
    bool clearError = false,
    bool clearSelection = false,
  }) => RechargeState(
    packages: packages ?? this.packages,
    selectedPackageId: clearSelection
        ? null
        : selectedPackageId ?? this.selectedPackageId,
    isSubmitting: isSubmitting ?? this.isSubmitting,
    lastOrder: lastOrder ?? this.lastOrder,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
  );
}

final class WithdrawFormState {
  const WithdrawFormState({
    this.diamonds = 0,
    this.amount = 0,
    this.isSubmitting = false,
    this.lastRequest,
    this.errorMessage,
  });

  final int diamonds;
  final int amount;
  final bool isSubmitting;
  final WithdrawRequest? lastRequest;
  final String? errorMessage;

  WithdrawFormState copyWith({
    int? diamonds,
    int? amount,
    bool? isSubmitting,
    WithdrawRequest? lastRequest,
    String? errorMessage,
    bool clearError = false,
  }) => WithdrawFormState(
    diamonds: diamonds ?? this.diamonds,
    amount: amount ?? this.amount,
    isSubmitting: isSubmitting ?? this.isSubmitting,
    lastRequest: lastRequest ?? this.lastRequest,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
  );
}
