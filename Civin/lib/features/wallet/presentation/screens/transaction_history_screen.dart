import 'package:civin/core/utils/responsive_helper.dart';
import 'package:civin/core/widgets/empty_widget.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:civin/features/wallet/presentation/wallet_providers.dart';
import 'package:civin/features/wallet/presentation/widgets/transaction_tile.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';


final class TransactionHistoryScreen extends ConsumerStatefulWidget {
  const TransactionHistoryScreen({super.key});

  @override
  ConsumerState<TransactionHistoryScreen> createState() =>
      _TransactionHistoryScreenState();
}

final class _TransactionHistoryScreenState
    extends ConsumerState<TransactionHistoryScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(transactionProvider.notifier).load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final TransactionHistoryState state = ref.watch(transactionProvider);
    final double horizontal = ResponsiveHelper.value(
      context,
      mobile: 16,
      tablet: 28,
      desktop: 40,
    );
    final double maxWidth = ResponsiveHelper.value(
      context,
      mobile: 720,
      tablet: 820,
      desktop: 920,
    );

    return Scaffold(
      appBar: AppBar(title: const Text('Transaction history')),
      body: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxWidth),
          child: RefreshIndicator.adaptive(
            onRefresh: () => ref.read(transactionProvider.notifier).refresh(),
            child: _body(context, state, horizontal),
          ),
        ),
      ),
    );
  }

  Widget _body(
    BuildContext context,
    TransactionHistoryState state,
    double horizontal,
  ) {
    if (state.isLoading && state.items.isEmpty) {
      return const AppLoadingWidget(message: 'Loading transactions');
    }
    if (state.errorMessage != null && state.items.isEmpty) {
      return AppErrorWidget(
        message: state.errorMessage!,
        onRetry: () => ref.read(transactionProvider.notifier).load(),
      );
    }
    if (state.items.isEmpty) {
      return const EmptyStateWidget(
        message: 'No transactions yet. Recharges, gifts, and withdrawals will show up here.',
        icon: Icons.receipt_long_outlined,
      );
    }

    return ListView.separated(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: EdgeInsets.fromLTRB(horizontal, 8, horizontal, 28),
      itemCount: state.items.length,
      separatorBuilder: (BuildContext context, int index) =>
          const Divider(height: 1),
      itemBuilder: (BuildContext context, int index) {
        final WalletTransaction tx = state.items[index];
        return TweenAnimationBuilder<double>(
          tween: Tween<double>(begin: 0, end: 1),
          duration: Duration(milliseconds: 220 + (index * 24).clamp(0, 240)),
          curve: Curves.easeOutCubic,
          builder: (BuildContext context, double value, Widget? child) =>
              Opacity(
                opacity: value,
                child: Transform.translate(
                  offset: Offset(0, (1 - value) * 12),
                  child: child,
                ),
              ),
          child: TransactionTile(transaction: tx),
        );
      },
    );
  }
}
