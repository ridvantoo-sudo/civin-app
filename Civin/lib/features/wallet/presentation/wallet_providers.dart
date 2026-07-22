import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/wallet/data/repositories/wallet_repository_impl.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:civin/features/wallet/domain/repositories/wallet_repository.dart';
import 'package:civin/features/wallet/domain/usecases/wallet_usecases.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<GetWallet> getWalletUseCaseProvider = Provider<GetWallet>(
  (Ref ref) => GetWallet(ref.watch(walletRepositoryProvider)),
);

final Provider<GetWalletTransactions> getWalletTransactionsUseCaseProvider =
    Provider<GetWalletTransactions>(
      (Ref ref) => GetWalletTransactions(ref.watch(walletRepositoryProvider)),
    );

final Provider<RechargeWallet> rechargeWalletUseCaseProvider =
    Provider<RechargeWallet>(
      (Ref ref) => RechargeWallet(ref.watch(walletRepositoryProvider)),
    );

final Provider<RequestWalletWithdraw> requestWalletWithdrawUseCaseProvider =
    Provider<RequestWalletWithdraw>(
      (Ref ref) => RequestWalletWithdraw(ref.watch(walletRepositoryProvider)),
    );

final Provider<ConnectWalletRealtime> connectWalletRealtimeProvider =
    Provider<ConnectWalletRealtime>(
      (Ref ref) => ConnectWalletRealtime(ref.watch(walletRepositoryProvider)),
    );

final Provider<DisconnectWalletRealtime> disconnectWalletRealtimeProvider =
    Provider<DisconnectWalletRealtime>(
      (Ref ref) =>
          DisconnectWalletRealtime(ref.watch(walletRepositoryProvider)),
    );

/// Wallet balances + realtime `WalletUpdated` stream.
final NotifierProvider<WalletController, WalletViewState> walletProvider =
    NotifierProvider<WalletController, WalletViewState>(WalletController.new);

/// Ledger / transaction history.
final NotifierProvider<TransactionController, TransactionHistoryState>
transactionProvider =
    NotifierProvider<TransactionController, TransactionHistoryState>(
      TransactionController.new,
    );

/// Recharge packages + purchase action.
final NotifierProvider<RechargeController, RechargeState> rechargeProvider =
    NotifierProvider<RechargeController, RechargeState>(RechargeController.new);

/// Withdraw form + latest request status.
final NotifierProvider<WithdrawController, WithdrawFormState> withdrawProvider =
    NotifierProvider<WithdrawController, WithdrawFormState>(
      WithdrawController.new,
    );

/// Default client-side coin packages (API accepts package fields on recharge).
const List<RechargePackage> kDefaultRechargePackages = <RechargePackage>[
  RechargePackage(
    id: 'starter',
    name: 'Starter Pack',
    coins: 100,
    price: 99,
    badge: 'Popular',
  ),
  RechargePackage(
    id: 'plus',
    name: 'Plus Pack',
    coins: 500,
    price: 449,
    bonusCoins: 25,
  ),
  RechargePackage(
    id: 'mega',
    name: 'Mega Pack',
    coins: 1200,
    price: 999,
    bonusCoins: 100,
    badge: 'Best value',
  ),
  RechargePackage(
    id: 'ultra',
    name: 'Ultra Pack',
    coins: 3000,
    price: 2299,
    bonusCoins: 400,
  ),
];

/// Approximate payout: 1 diamond ≈ 15 minor currency units (matches API tests).
int diamondsToPayoutAmount(int diamonds) => diamonds * 15;

final class WalletController extends Notifier<WalletViewState> {
  StreamSubscription<WalletUpdatedEvent>? _walletSub;
  bool _started = false;

  @override
  WalletViewState build() {
    final WalletRepository repository = ref.read(walletRepositoryProvider);
    ref.onDispose(() {
      unawaited(_walletSub?.cancel());
      unawaited(repository.disconnectRealtime());
    });
    return const WalletViewState();
  }

  Future<void> load({bool listenRealtime = true}) async {
    state = state.copyWith(isLoading: true, clearError: true);
    final RepositoryResult<WalletBalance> result = await ref.read(
      getWalletUseCaseProvider,
    )();

    if (!ref.mounted) return;

    result.fold(
      onSuccess: (WalletBalance balance) {
        state = state.copyWith(
          balance: balance,
          isLoading: false,
          clearError: true,
        );
      },
      onFailure: (failure) {
        state = state.copyWith(
          isLoading: false,
          errorMessage: failure.message,
        );
      },
    );

    if (listenRealtime && state.balance != null) {
      await startListening();
    }
  }

  Future<void> refresh() => load(listenRealtime: !_started);

  Future<void> startListening() async {
    final WalletBalance? balance = state.balance;
    if (balance == null || _started) return;
    _started = true;

    final WalletRepository repository = ref.read(walletRepositoryProvider);
    await _walletSub?.cancel();
    if (!ref.mounted) return;
    _walletSub = repository.watchWalletUpdated().listen(_onWalletUpdated);

    try {
      await ref.read(connectWalletRealtimeProvider)(balance.userId);
      if (!ref.mounted) return;
      state = state.copyWith(isListening: true, clearError: true);
    } on Object catch (error) {
      if (!ref.mounted) return;
      _started = false;
      state = state.copyWith(
        isListening: false,
        errorMessage: error.toString(),
      );
    }
  }

  void applyBalance(WalletBalance balance) {
    state = state.copyWith(balance: balance, clearError: true);
  }

  void trackWithdrawal(WithdrawRequest request) {
    final List<WithdrawRequest> next = List<WithdrawRequest>.of(
      state.recentWithdrawals,
    )..insert(0, request);
    if (next.length > 20) {
      next.removeRange(20, next.length);
    }
    state = state.copyWith(
      recentWithdrawals: List<WithdrawRequest>.unmodifiable(next),
      clearError: true,
    );
  }

  void _onWalletUpdated(WalletUpdatedEvent event) {
    if (!ref.mounted) return;
    final WalletBalance? current = state.balance;
    if (current != null &&
        event.userId.isNotEmpty &&
        event.userId != current.userId) {
      return;
    }
    state = state.copyWith(
      balance: event.toBalance().copyWith(
        id: event.walletId.isNotEmpty
            ? event.walletId
            : current?.id ?? event.walletId,
        createdAt: current?.createdAt,
      ),
      clearError: true,
    );
  }
}

final class TransactionController extends Notifier<TransactionHistoryState> {
  @override
  TransactionHistoryState build() => const TransactionHistoryState();

  Future<void> load({int perPage = 30}) async {
    state = state.copyWith(isLoading: true, clearError: true);
    final RepositoryResult<List<WalletTransaction>> result = await ref.read(
      getWalletTransactionsUseCaseProvider,
    )(perPage: perPage);

    if (!ref.mounted) return;

    result.fold(
      onSuccess: (List<WalletTransaction> items) {
        state = state.copyWith(
          items: List<WalletTransaction>.unmodifiable(items),
          isLoading: false,
          clearError: true,
        );
      },
      onFailure: (failure) {
        state = state.copyWith(
          isLoading: false,
          errorMessage: failure.message,
        );
      },
    );
  }

  Future<void> refresh() => load();
}

final class RechargeController extends Notifier<RechargeState> {
  @override
  RechargeState build() => const RechargeState(
    packages: kDefaultRechargePackages,
    selectedPackageId: 'starter',
  );

  void selectPackage(String packageId) {
    state = state.copyWith(selectedPackageId: packageId, clearError: true);
  }

  Future<bool> purchase({
    String paymentProvider = 'app_store',
    String? transactionId,
  }) async {
    final RechargePackage? package = state.selectedPackage;
    if (package == null || state.isSubmitting) return false;

    state = state.copyWith(isSubmitting: true, clearError: true);
    final String resolvedTxnId =
        transactionId ??
        'txn_${package.id}_${DateTime.now().millisecondsSinceEpoch}';

    final RepositoryResult<RechargeOrder> result = await ref.read(
      rechargeWalletUseCaseProvider,
    )(
      packageName: package.name,
      coins: package.totalCoins,
      price: package.price,
      currency: package.currency,
      paymentProvider: paymentProvider,
      transactionId: resolvedTxnId,
    );

    if (!ref.mounted) return false;

    return result.fold(
      onSuccess: (RechargeOrder order) {
        state = state.copyWith(
          isSubmitting: false,
          lastOrder: order,
          clearError: true,
        );
        if (ref.mounted) {
          unawaited(ref.read(walletProvider.notifier).refresh());
          unawaited(ref.read(transactionProvider.notifier).refresh());
        }
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(
          isSubmitting: false,
          errorMessage: failure.message,
        );
        return false;
      },
    );
  }
}

final class WithdrawController extends Notifier<WithdrawFormState> {
  @override
  WithdrawFormState build() => const WithdrawFormState();

  void setDiamonds(int diamonds) {
    final int clamped = diamonds < 0 ? 0 : diamonds;
    state = state.copyWith(
      diamonds: clamped,
      amount: diamondsToPayoutAmount(clamped),
      clearError: true,
    );
  }

  void setAmount(int amount) {
    state = state.copyWith(amount: amount < 0 ? 0 : amount, clearError: true);
  }

  Future<bool> submit() async {
    if (state.diamonds <= 0 || state.amount <= 0 || state.isSubmitting) {
      return false;
    }

    state = state.copyWith(isSubmitting: true, clearError: true);
    final RepositoryResult<WithdrawRequest> result = await ref.read(
      requestWalletWithdrawUseCaseProvider,
    )(diamonds: state.diamonds, amount: state.amount);

    return result.fold(
      onSuccess: (WithdrawRequest request) {
        state = state.copyWith(
          isSubmitting: false,
          lastRequest: request,
          clearError: true,
        );
        ref.read(walletProvider.notifier).trackWithdrawal(request);
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(
          isSubmitting: false,
          errorMessage: failure.message,
        );
        return false;
      },
    );
  }
}
