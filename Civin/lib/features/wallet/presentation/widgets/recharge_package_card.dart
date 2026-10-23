import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:flutter/material.dart';

final class RechargePackageCard extends StatelessWidget {
  const RechargePackageCard({
    required this.package,
    required this.selected,
    required this.onTap,
    super.key,
  });

  final RechargePackage package;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final BorderRadius radius = BorderRadius.circular(20);

    return AnimatedScale(
      scale: selected ? 1.02 : 1,
      duration: const Duration(milliseconds: 220),
      curve: Curves.easeOutCubic,
      child: Material(
        color: selected
            ? colors.primaryContainer.withValues(alpha: 0.55)
            : colors.surfaceContainerHighest.withValues(alpha: 0.55),
        borderRadius: radius,
        child: InkWell(
          onTap: onTap,
          borderRadius: radius,
          child: AnimatedContainer(
            duration: const Duration(milliseconds: 220),
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              borderRadius: radius,
              border: Border.all(
                color: selected
                    ? colors.primary
                    : colors.outlineVariant.withValues(alpha: 0.35),
                width: selected ? 1.6 : 1,
              ),
            ),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Expanded(
                      child: Text(
                        package.name,
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(fontWeight: FontWeight.w700),
                      ),
                    ),
                    if (package.badge != null)
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 4,
                        ),
                        decoration: BoxDecoration(
                          color: colors.secondary.withValues(alpha: 0.2),
                          borderRadius: BorderRadius.circular(999),
                        ),
                        child: Text(
                          package.badge!,
                          style: Theme.of(context).textTheme.labelSmall
                              ?.copyWith(
                                color: colors.secondary,
                                fontWeight: FontWeight.w700,
                              ),
                        ),
                      ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  '${package.totalCoins} coins',
                  style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: const Color(0xFFFFC857),
                  ),
                ),
                if (package.bonusCoins > 0) ...[
                  const SizedBox(height: 4),
                  Text(
                    '+${package.bonusCoins} bonus',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                      color: colors.onSurface.withValues(alpha: 0.65),
                    ),
                  ),
                ],
                const Spacer(),
                Text(
                  _priceLabel(package.price, package.currency),
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _priceLabel(int minorUnits, String currency) {
    final double major = minorUnits / 100;
    return '\$${major.toStringAsFixed(2)} $currency';
  }
}
