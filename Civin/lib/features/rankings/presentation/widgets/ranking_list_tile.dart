import 'package:civin/core/widgets/app_network_image.dart';
import 'package:civin/features/rankings/domain/entities/ranking.dart';
import 'package:flutter/material.dart';

final class RankingVipBadge extends StatelessWidget {
  const RankingVipBadge({super.key, this.compact = false});

  final bool compact;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 6 : 8,
        vertical: compact ? 2 : 4,
      ),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: <Color>[
            colors.tertiary,
            colors.secondary,
          ],
        ),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        'VIP',
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: colors.onSecondary,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.4,
          fontSize: compact ? 9 : 10,
        ),
      ),
    );
  }
}

final class RankingAvatar extends StatelessWidget {
  const RankingAvatar({
    required this.user,
    required this.size,
    super.key,
    this.borderColor,
    this.borderWidth = 2,
  });

  final RankingUser user;
  final double size;
  final Color? borderColor;
  final double borderWidth;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final String? url = user.avatarUrl;
    final Widget child = url == null || url.isEmpty
        ? CircleAvatar(
            radius: size / 2,
            backgroundColor: colors.primaryContainer,
            child: Text(
              user.displayName.characters.first.toUpperCase(),
              style: TextStyle(
                color: colors.onPrimaryContainer,
                fontWeight: FontWeight.w700,
                fontSize: size * 0.38,
              ),
            ),
          )
        : AppNetworkImage(
            url: url,
            width: size,
            height: size,
            borderRadius: BorderRadius.circular(size / 2),
          );

    if (borderColor == null) return child;
    return Container(
      width: size + borderWidth * 2,
      height: size + borderWidth * 2,
      padding: EdgeInsets.all(borderWidth),
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        color: borderColor,
      ),
      child: ClipOval(child: child),
    );
  }
}

final class RankingListTile extends StatelessWidget {
  const RankingListTile({
    required this.entry,
    required this.index,
    super.key,
  });

  final RankingEntry entry;
  final int index;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final TextTheme textTheme = Theme.of(context).textTheme;

    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0, end: 1),
      duration: Duration(milliseconds: 280 + (index * 40).clamp(0, 360)),
      curve: Curves.easeOutCubic,
      builder: (BuildContext context, double value, Widget? child) => Opacity(
        opacity: value,
        child: Transform.translate(
          offset: Offset(0, (1 - value) * 16),
          child: child,
        ),
      ),
      child: Material(
        color: colors.surfaceContainerHighest.withValues(alpha: 0.45),
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
          child: Row(
            children: [
              SizedBox(
                width: 34,
                child: Text(
                  '#${entry.rank}',
                  style: textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: colors.onSurfaceVariant,
                  ),
                ),
              ),
              RankingAvatar(user: entry.user, size: 44),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      children: [
                        Flexible(
                          child: Text(
                            entry.user.displayName,
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                            style: textTheme.titleSmall?.copyWith(
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                        if (entry.user.isVip) ...[
                          const SizedBox(width: 6),
                          const RankingVipBadge(compact: true),
                        ],
                      ],
                    ),
                    if (entry.user.countryCode != null)
                      Text(
                        entry.user.countryCode!,
                        style: textTheme.bodySmall?.copyWith(
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                  ],
                ),
              ),
              Text(
                _formatScore(entry.score),
                style: textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: colors.primary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

String _formatScore(int score) {
  if (score >= 1000000) {
    return '${(score / 1000000).toStringAsFixed(1)}M';
  }
  if (score >= 1000) {
    return '${(score / 1000).toStringAsFixed(1)}K';
  }
  return score.toString();
}

String formatRankingScore(int score) => _formatScore(score);
