import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:flutter/material.dart';

/// Circular countdown badge for the active PK battle.
final class PkTimerBadge extends StatelessWidget {
  const PkTimerBadge({required this.timer, super.key});

  final PkTimerState timer;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final bool urgent = timer.remainingSeconds <= 10 && timer.isRunning;

    return AnimatedScale(
      scale: urgent ? 1.08 : 1,
      duration: const Duration(milliseconds: 280),
      child: Container(
        padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
        decoration: BoxDecoration(
          color: urgent
              ? colors.error.withValues(alpha: 0.92)
              : colors.surface.withValues(alpha: 0.88),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(
            color: colors.onSurface.withValues(alpha: 0.18),
          ),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(
              Icons.timer_outlined,
              size: 18,
              color: urgent ? colors.onError : colors.primary,
            ),
            const SizedBox(width: 6),
            Text(
              timer.formatted,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                fontFeatures: const <FontFeature>[FontFeature.tabularFigures()],
                fontWeight: FontWeight.w800,
                color: urgent ? colors.onError : colors.onSurface,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
