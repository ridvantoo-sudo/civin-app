import 'package:civin/core/router/router.dart';
import 'package:civin/features/rankings/domain/entities/ranking.dart';
import 'package:civin/features/rankings/presentation/widgets/ranking_leaderboard.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

final class RankingHome extends StatelessWidget {
  const RankingHome({super.key});

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: rankingDarkTheme(context),
      child: Builder(
        builder: (BuildContext context) {
          final ColorScheme colors = Theme.of(context).colorScheme;
          return Scaffold(
            appBar: AppBar(title: const Text('Rankings')),
            body: DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: <Color>[
                    colors.primary.withValues(alpha: 0.28),
                    const Color(0xFF0E0E12),
                    colors.secondary.withValues(alpha: 0.12),
                  ],
                ),
              ),
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
                children: [
                  Text(
                    'Climb the boards',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Daily, weekly, and monthly leaders across hosts, gifters, PK, and voice.',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: colors.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 24),
                  for (final RankingType type in RankingType.values) ...[
                    _RankingCategoryCard(
                      type: type,
                      onTap: () => context.push(_routeFor(type)),
                    ),
                    const SizedBox(height: 12),
                  ],
                ],
              ),
            ),
          );
        },
      ),
    );
  }

  String _routeFor(RankingType type) => switch (type) {
    RankingType.host => AppRoutes.hostRanking,
    RankingType.gifter => AppRoutes.gifterRanking,
    RankingType.pk => AppRoutes.pkRanking,
    RankingType.voice => AppRoutes.voiceRanking,
  };
}

final class _RankingCategoryCard extends StatelessWidget {
  const _RankingCategoryCard({required this.type, required this.onTap});

  final RankingType type;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final IconData icon = switch (type) {
      RankingType.host => Icons.live_tv_rounded,
      RankingType.gifter => Icons.card_giftcard_rounded,
      RankingType.pk => Icons.sports_esports_rounded,
      RankingType.voice => Icons.mic_rounded,
    };

    return Material(
      color: colors.surfaceContainerHighest.withValues(alpha: 0.55),
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Row(
            children: [
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: colors.primary.withValues(alpha: 0.18),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, color: colors.primary),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      type.title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      type.subtitle,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: colors.onSurfaceVariant),
            ],
          ),
        ),
      ),
    );
  }
}
