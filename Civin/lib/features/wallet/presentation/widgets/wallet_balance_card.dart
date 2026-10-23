import 'package:civin/core/utils/responsive_helper.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:flutter/material.dart';

final class WalletBalanceCard extends StatelessWidget {
  const WalletBalanceCard({
    required this.balance,
    this.animate = true,
    super.key,
  });

  final WalletBalance? balance;
  final bool animate;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final double gap = ResponsiveHelper.value(
      context,
      mobile: 12,
      tablet: 16,
      desktop: 20,
    );

    final Widget content = Row(
      children: [
        Expanded(
          child: _BalanceTile(
            label: 'Coins',
            value: balance?.coinsBalance ?? 0,
            icon: Icons.monetization_on_rounded,
            accent: const Color(0xFFFFC857),
          ),
        ),
        SizedBox(width: gap),
        Expanded(
          child: _BalanceTile(
            label: 'Diamonds',
            value: balance?.diamondsBalance ?? 0,
            icon: Icons.diamond_rounded,
            accent: const Color(0xFF7AD7FF),
          ),
        ),
      ],
    );

    if (!animate) return content;

    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0.92, end: 1),
      duration: const Duration(milliseconds: 420),
      curve: Curves.easeOutBack,
      builder: (BuildContext context, double scale, Widget? child) =>
          Transform.scale(scale: scale, child: child),
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(24),
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[
              colors.surfaceContainerHighest.withValues(alpha: 0.9),
              colors.surface.withValues(alpha: 0.72),
            ],
          ),
          border: Border.all(color: colors.outlineVariant.withValues(alpha: 0.4)),
        ),
        child: Padding(padding: EdgeInsets.all(gap + 4), child: content),
      ),
    );
  }
}

final class _BalanceTile extends StatelessWidget {
  const _BalanceTile({
    required this.label,
    required this.value,
    required this.icon,
    required this.accent,
  });

  final String label;
  final int value;
  final IconData icon;
  final Color accent;

  @override
  Widget build(BuildContext context) {
    final TextTheme textTheme = Theme.of(context).textTheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(icon, color: accent, size: 20),
            const SizedBox(width: 8),
            Text(
              label,
              style: textTheme.labelLarge?.copyWith(
                color: Theme.of(
                  context,
                ).colorScheme.onSurface.withValues(alpha: 0.72),
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        TweenAnimationBuilder<int>(
          tween: IntTween(begin: 0, end: value),
          duration: const Duration(milliseconds: 520),
          curve: Curves.easeOutCubic,
          builder: (BuildContext context, int animated, Widget? child) => Text(
            _format(animated),
            style: textTheme.headlineMedium?.copyWith(
              fontWeight: FontWeight.w700,
              letterSpacing: -0.5,
            ),
          ),
        ),
      ],
    );
  }

  String _format(int n) {
    final String raw = n.toString();
    final StringBuffer buffer = StringBuffer();
    for (int i = 0; i < raw.length; i++) {
      final int fromEnd = raw.length - i;
      buffer.write(raw[i]);
      if (fromEnd > 1 && fromEnd % 3 == 1) buffer.write(',');
    }
    return buffer.toString();
  }
}
