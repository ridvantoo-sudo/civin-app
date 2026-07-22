import 'package:civin/core/router/router.dart';
import 'package:civin/core/utils/responsive_helper.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:civin/features/wallet/presentation/wallet_providers.dart';
import 'package:civin/features/wallet/presentation/widgets/transaction_tile.dart';
import 'package:civin/features/wallet/presentation/widgets/wallet_balance_card.dart';
import 'package:civin/features/wallet/presentation/widgets/withdraw_status_chip.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class WalletScreen extends ConsumerStatefulWidget {
  const WalletScreen({super.key});

  @override
  ConsumerState<WalletScreen> createState() => _WalletScreenState();
}

final class _WalletScreenState extends ConsumerState<WalletScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(walletProvider.notifier).load();
      ref.read(transactionProvider.notifier).load(perPage: 8);
    });
  }

  @override
  Widget build(BuildContext context) {
    final WalletViewState wallet = ref.watch(walletProvider);
    final TransactionHistoryState transactions = ref.watch(transactionProvider);
    final double horizontal = ResponsiveHelper.value(
      context,
      mobile: 20,
      tablet: 32,
      desktop: 48,
    );
    final double maxWidth = ResponsiveHelper.value(
      context,
      mobile: 720,
      tablet: 820,
      desktop: 920,
    );

    return Scaffold(
      appBar: AppBar(
        title: const Text('Wallet'),
        actions: [
          IconButton(
            tooltip: 'History',
            onPressed: () => context.push(AppRoutes.walletTransactions),
            icon: const Icon(Icons.history_rounded),
          ),
        ],
      ),
      body: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxWidth),
          child: RefreshIndicator.adaptive(
            onRefresh: () async {
              await ref.read(walletProvider.notifier).refresh();
              await ref.read(transactionProvider.notifier).refresh();
            },
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              padding: EdgeInsets.fromLTRB(horizontal, 16, horizontal, 28),
              children: [
                if (wallet.isLoading && wallet.balance == null)
                  const Padding(
                    padding: EdgeInsets.symmetric(vertical: 48),
                    child: AppLoadingWidget(message: 'Loading wallet'),
                  )
                else if (wallet.errorMessage != null && wallet.balance == null)
                  AppErrorWidget(
                    message: wallet.errorMessage!,
                    onRetry: () => ref.read(walletProvider.notifier).load(),
                  )
                else ...[
                  WalletBalanceCard(balance: wallet.balance),
                  const SizedBox(height: 20),
                  Row(
                    children: [
                      Expanded(
                        child: FilledButton.icon(
                          onPressed: () => context.push(AppRoutes.walletRecharge),
                          icon: const Icon(Icons.add_circle_outline_rounded),
                          label: const Text('Recharge'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () =>
                              context.push(AppRoutes.walletWithdraw),
                          icon: const Icon(Icons.south_west_rounded),
                          label: const Text('Withdraw'),
                        ),
                      ),
                    ],
                  ),
                  if (wallet.latestWithdrawal != null) ...[
                    const SizedBox(height: 24),
                    Text(
                      'Withdrawal status',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 8),
                    WithdrawRequestTile(request: wallet.latestWithdrawal!),
                  ],
                  const SizedBox(height: 24),
                  Row(
                    children: [
                      Expanded(
                        child: Text(
                          'Recent activity',
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.w700),
                        ),
                      ),
                      TextButton(
                        onPressed: () =>
                            context.push(AppRoutes.walletTransactions),
                        child: const Text('See all'),
                      ),
                    ],
                  ),
                  if (transactions.isLoading && transactions.items.isEmpty)
                    const Padding(
                      padding: EdgeInsets.symmetric(vertical: 24),
                      child: Center(child: CircularProgressIndicator()),
                    )
                  else if (transactions.items.isEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(vertical: 24),
                      child: Text(
                        'No transactions yet',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: Theme.of(
                            context,
                          ).colorScheme.onSurface.withValues(alpha: 0.6),
                        ),
                      ),
                    )
                  else
                    ...transactions.items
                        .take(5)
                        .map(
                          (WalletTransaction tx) =>
                              TransactionTile(transaction: tx),
                        ),
                ],
              ],
            ),
          ),
        ),
      ),
    );
  }
}
