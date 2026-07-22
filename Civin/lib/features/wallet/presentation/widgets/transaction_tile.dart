import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:flutter/material.dart';

final class TransactionTile extends StatelessWidget {
  const TransactionTile({required this.transaction, super.key});

  final WalletTransaction transaction;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final bool credit = transaction.isCredit;
    final Color amountColor = credit ? const Color(0xFF21C17A) : colors.error;

    return ListTile(
      contentPadding: const EdgeInsets.symmetric(horizontal: 4, vertical: 2),
      leading: CircleAvatar(
        backgroundColor: amountColor.withValues(alpha: 0.16),
        child: Icon(_icon, color: amountColor, size: 20),
      ),
      title: Text(
        _title,
        style: Theme.of(
          context,
        ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        _subtitle,
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: colors.onSurface.withValues(alpha: 0.6),
        ),
      ),
      trailing: Text(
        '${credit ? '+' : ''}${transaction.amount} $_currencyLabel',
        style: Theme.of(context).textTheme.titleSmall?.copyWith(
          color: amountColor,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  IconData get _icon => switch (transaction.type) {
    WalletTransactionType.coinPurchase => Icons.shopping_bag_rounded,
    WalletTransactionType.giftSent => Icons.card_giftcard_rounded,
    WalletTransactionType.giftReceived => Icons.redeem_rounded,
    WalletTransactionType.pkReward => Icons.emoji_events_rounded,
    WalletTransactionType.withdraw => Icons.account_balance_wallet_rounded,
    WalletTransactionType.adminAdjustment => Icons.tune_rounded,
    WalletTransactionType.unknown => Icons.receipt_long_rounded,
  };

  String get _title => switch (transaction.type) {
    WalletTransactionType.coinPurchase => 'Coin purchase',
    WalletTransactionType.giftSent => 'Gift sent',
    WalletTransactionType.giftReceived => 'Gift received',
    WalletTransactionType.pkReward => 'PK reward',
    WalletTransactionType.withdraw => 'Withdrawal',
    WalletTransactionType.adminAdjustment => 'Adjustment',
    WalletTransactionType.unknown => 'Transaction',
  };

  String get _subtitle {
    final DateTime local = transaction.createdAt.toLocal();
    final String stamp =
        '${local.year}-${_two(local.month)}-${_two(local.day)} '
        '${_two(local.hour)}:${_two(local.minute)}';
    return stamp;
  }

  String get _currencyLabel => switch (transaction.currency) {
    WalletCurrency.coins => 'coins',
    WalletCurrency.diamonds => 'diamonds',
    WalletCurrency.unknown => '',
  };

  String _two(int value) => value.toString().padLeft(2, '0');
}
