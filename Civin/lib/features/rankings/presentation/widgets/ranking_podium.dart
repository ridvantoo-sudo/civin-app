import 'package:civin/features/rankings/domain/entities/ranking.dart';
import 'package:civin/features/rankings/presentation/widgets/ranking_list_tile.dart';
import 'package:flutter/material.dart';

final class RankingPodium extends StatelessWidget {
  const RankingPodium({required this.entries, super.key});

  final List<RankingEntry> entries;

  @override
  Widget build(BuildContext context) {
    final RankingEntry? first = _at(1);
    final RankingEntry? second = _at(2);
    final RankingEntry? third = _at(3);

    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0, end: 1),
      duration: const Duration(milliseconds: 700),
      curve: Curves.easeOutBack,
      builder: (BuildContext context, double value, Widget? child) => Opacity(
        opacity: value.clamp(0, 1),
        child: Transform.translate(
          offset: Offset(0, (1 - value) * 28),
          child: child,
        ),
      ),
      child: Padding(
        padding: const EdgeInsets.fromLTRB(8, 8, 8, 4),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.end,
          children: [
            Expanded(
              child: _PodiumSlot(
                entry: second,
                place: 2,
                height: 96,
                accent: const Color(0xFF9CA3AF),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _PodiumSlot(
                entry: first,
                place: 1,
                height: 128,
                accent: const Color(0xFFF5C518),
                highlight: true,
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              child: _PodiumSlot(
                entry: third,
                place: 3,
                height: 80,
                accent: const Color(0xFFCD7F32),
              ),
            ),
          ],
        ),
      ),
    );
  }

  RankingEntry? _at(int rank) {
    for (final RankingEntry entry in entries) {
      if (entry.rank == rank) return entry;
    }
    if (entries.length >= rank) return entries[rank - 1];
    return null;
  }
}

final class _PodiumSlot extends StatelessWidget {
  const _PodiumSlot({
    required this.entry,
    required this.place,
    required this.height,
    required this.accent,
    this.highlight = false,
  });

  final RankingEntry? entry;
  final int place;
  final double height;
  final Color accent;
  final bool highlight;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final TextTheme textTheme = Theme.of(context).textTheme;
    final RankingEntry? current = entry;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (current != null) ...[
          RankingAvatar(
            user: current.user,
            size: highlight ? 64 : 52,
            borderColor: accent,
            borderWidth: highlight ? 3 : 2,
          ),
          const SizedBox(height: 8),
          Text(
            current.user.displayName,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
            textAlign: TextAlign.center,
            style: textTheme.labelLarge?.copyWith(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 2),
          Row(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Text(
                formatRankingScore(current.score),
                style: textTheme.labelMedium?.copyWith(
                  color: colors.primary,
                  fontWeight: FontWeight.w800,
                ),
              ),
              if (current.user.isVip) ...[
                const SizedBox(width: 4),
                const RankingVipBadge(compact: true),
              ],
            ],
          ),
          const SizedBox(height: 10),
        ] else ...[
          SizedBox(height: highlight ? 110 : 96),
        ],
        AnimatedContainer(
          duration: const Duration(milliseconds: 450),
          curve: Curves.easeOutCubic,
          height: height,
          width: double.infinity,
          decoration: BoxDecoration(
            borderRadius: const BorderRadius.vertical(top: Radius.circular(16)),
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: <Color>[
                accent.withValues(alpha: 0.95),
                accent.withValues(alpha: 0.45),
              ],
            ),
            boxShadow: [
              BoxShadow(
                color: accent.withValues(alpha: 0.28),
                blurRadius: 18,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          alignment: Alignment.topCenter,
          padding: const EdgeInsets.only(top: 12),
          child: Text(
            '$place',
            style: textTheme.headlineSmall?.copyWith(
              color: Colors.black.withValues(alpha: 0.78),
              fontWeight: FontWeight.w900,
            ),
          ),
        ),
      ],
    );
  }
}
