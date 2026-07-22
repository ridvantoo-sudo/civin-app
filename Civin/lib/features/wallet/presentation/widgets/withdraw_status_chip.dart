import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:flutter/material.dart';

final class WithdrawStatusChip extends StatelessWidget {
  const WithdrawStatusChip({required this.status, super.key});

  final WithdrawStatus status;

  @override
  Widget build(BuildContext context) {
    final ({Color bg, Color fg, String label}) style = switch (status) {
      WithdrawStatus.pending => (
        bg: const Color(0xFFFFB020).withValues(alpha: 0.18),
        fg: const Color(0xFFFFB020),
        label: 'Pending',
      ),
      WithdrawStatus.approved => (
        bg: const Color(0xFF21C17A).withValues(alpha: 0.18),
        fg: const Color(0xFF21C17A),
        label: 'Approved',
      ),
      WithdrawStatus.rejected => (
        bg: Theme.of(context).colorScheme.error.withValues(alpha: 0.18),
        fg: Theme.of(context).colorScheme.error,
        label: 'Rejected',
      ),
      WithdrawStatus.unknown => (
        bg: Theme.of(context).colorScheme.outlineVariant.withValues(alpha: 0.3),
        fg: Theme.of(context).colorScheme.onSurface.withValues(alpha: 0.7),
        label: 'Unknown',
      ),
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
      decoration: BoxDecoration(
        color: style.bg,
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        style.label,
        style: Theme.of(context).textTheme.labelMedium?.copyWith(
          color: style.fg,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

final class WithdrawRequestTile extends StatelessWidget {
  const WithdrawRequestTile({required this.request, super.key});

  final WithdrawRequest request;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final DateTime local = request.createdAt.toLocal();
    return ListTile(
      contentPadding: EdgeInsets.zero,
      title: Text(
        '${request.diamonds} diamonds',
        style: Theme.of(
          context,
        ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w600),
      ),
      subtitle: Text(
        'Payout ${(request.amount / 100).toStringAsFixed(2)} · '
        '${local.year}-${local.month.toString().padLeft(2, '0')}-'
        '${local.day.toString().padLeft(2, '0')}',
        style: Theme.of(context).textTheme.bodySmall?.copyWith(
          color: colors.onSurface.withValues(alpha: 0.6),
        ),
      ),
      trailing: WithdrawStatusChip(status: request.status),
    );
  }
}
