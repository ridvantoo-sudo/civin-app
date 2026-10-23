import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:flutter/material.dart';

/// Split-screen host panels for Host A (left) and Host B (right).
final class PkSplitView extends StatelessWidget {
  const PkSplitView({required this.score, super.key});

  final PkScoreView score;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: [
        Expanded(
          child: _HostPane(
            label: 'Host A',
            name: score.hostNameA,
            score: score.scoreA,
            avatarUrl: score.hostAvatarA,
            accent: Theme.of(context).colorScheme.primary,
            alignment: Alignment.centerLeft,
          ),
        ),
        Container(
          width: 2,
          margin: const EdgeInsets.symmetric(vertical: 48),
          color: Colors.white.withValues(alpha: 0.35),
        ),
        Expanded(
          child: _HostPane(
            label: 'Host B',
            name: score.hostNameB,
            score: score.scoreB,
            avatarUrl: score.hostAvatarB,
            accent: Theme.of(context).colorScheme.error,
            alignment: Alignment.centerRight,
          ),
        ),
      ],
    );
  }
}

final class _HostPane extends StatelessWidget {
  const _HostPane({
    required this.label,
    required this.name,
    required this.score,
    required this.accent,
    required this.alignment,
    this.avatarUrl,
  });

  final String label;
  final String name;
  final int score;
  final String? avatarUrl;
  final Color accent;
  final Alignment alignment;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: alignment,
      child: Padding(
        padding: const EdgeInsets.fromLTRB(16, 72, 16, 16),
        child: Column(
          crossAxisAlignment: alignment == Alignment.centerLeft
              ? CrossAxisAlignment.start
              : CrossAxisAlignment.end,
          children: [
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
              decoration: BoxDecoration(
                color: accent.withValues(alpha: 0.9),
                borderRadius: BorderRadius.circular(8),
              ),
              child: Text(
                label,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const SizedBox(height: 10),
            CircleAvatar(
              radius: 28,
              backgroundColor: accent.withValues(alpha: 0.35),
              backgroundImage: avatarUrl != null && avatarUrl!.isNotEmpty
                  ? NetworkImage(avatarUrl!)
                  : null,
              child: avatarUrl == null || avatarUrl!.isEmpty
                  ? Text(
                      name.isNotEmpty ? name[0].toUpperCase() : '?',
                      style: const TextStyle(
                        fontWeight: FontWeight.w800,
                        color: Colors.white,
                      ),
                    )
                  : null,
            ),
            const SizedBox(height: 8),
            Text(
              name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                color: Colors.white,
                fontWeight: FontWeight.w700,
              ),
            ),
            AnimatedSwitcher(
              duration: const Duration(milliseconds: 280),
              child: Text(
                '$score',
                key: ValueKey<int>(score),
                style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                  color: Colors.white,
                  fontWeight: FontWeight.w900,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
