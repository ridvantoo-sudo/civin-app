import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:flutter/material.dart';

/// Animated dual score bar for Host A vs Host B.
final class PkScoreBar extends StatelessWidget {
  const PkScoreBar({required this.score, this.height = 14, super.key});

  final PkScoreView score;
  final double height;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final double ratioA = score.ratioA.clamp(0.08, 0.92);

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        Row(
          children: [
            Expanded(
              child: Text(
                '${score.hostNameA}  ${score.scoreA}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: colors.onSurface,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            Expanded(
              child: Text(
                '${score.scoreB}  ${score.hostNameB}',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                textAlign: TextAlign.end,
                style: Theme.of(context).textTheme.labelLarge?.copyWith(
                  color: colors.onSurface,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 8),
        ClipRRect(
          borderRadius: BorderRadius.circular(999),
          child: SizedBox(
            height: height,
            child: LayoutBuilder(
              builder: (BuildContext context, BoxConstraints constraints) {
                final double widthA = constraints.maxWidth * ratioA;
                return Stack(
                  fit: StackFit.expand,
                  children: [
                    ColoredBox(color: colors.error.withValues(alpha: 0.85)),
                    Align(
                      alignment: Alignment.centerLeft,
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 420),
                        curve: Curves.easeOutCubic,
                        width: widthA,
                        color: colors.primary,
                      ),
                    ),
                    Center(
                      child: Container(
                        width: 3,
                        color: colors.onSurface.withValues(alpha: 0.9),
                      ),
                    ),
                  ],
                );
              },
            ),
          ),
        ),
      ],
    );
  }
}
