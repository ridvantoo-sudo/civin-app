import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:civin/features/pk/presentation/pk_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Winner / draw result overlay with celebration animation.
final class PkResultScreen extends ConsumerStatefulWidget {
  const PkResultScreen({required this.roomId, super.key});

  final String roomId;

  @override
  ConsumerState<PkResultScreen> createState() => _PkResultScreenState();
}

final class _PkResultScreenState extends ConsumerState<PkResultScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;
  late final Animation<double> _scale;
  late final Animation<double> _fade;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 720),
    );
    _scale = CurvedAnimation(parent: _controller, curve: Curves.elasticOut);
    _fade = CurvedAnimation(parent: _controller, curve: Curves.easeOut);
    _controller.forward();
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final PkRoomState pkState = ref.watch(pkProvider(widget.roomId));
    final PkBattle? battle = pkState.battle;
    if (battle == null || !battle.isFinished || !pkState.showResult) {
      return const SizedBox.shrink();
    }

    final ColorScheme colors = Theme.of(context).colorScheme;
    final bool isDraw = battle.winnerId == null;
    final String title = isDraw ? 'Draw!' : 'Winner';
    final String subtitle = isDraw
        ? 'Both hosts tied ${battle.scoreA} – ${battle.scoreB}'
        : '${battle.winner?.displayName ?? 'Champion'} wins '
              '${battle.scoreA} – ${battle.scoreB}';
    final PkReward? reward = battle.rewards.isEmpty
        ? null
        : battle.rewards.first;

    return Material(
      color: Colors.black.withValues(alpha: 0.72),
      child: SafeArea(
        child: Center(
          child: FadeTransition(
            opacity: _fade,
            child: ScaleTransition(
              scale: _scale,
              child: Container(
                width: 320,
                padding: const EdgeInsets.fromLTRB(24, 28, 24, 20),
                decoration: BoxDecoration(
                  color: const Color(0xFF16161E),
                  borderRadius: BorderRadius.circular(24),
                  border: Border.all(
                    color: colors.primary.withValues(alpha: 0.45),
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: colors.primary.withValues(alpha: 0.35),
                      blurRadius: 28,
                      spreadRadius: 2,
                    ),
                  ],
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Icon(
                      isDraw
                          ? Icons.handshake_rounded
                          : Icons.emoji_events_rounded,
                      size: 64,
                      color: isDraw ? colors.tertiary : colors.primary,
                    ),
                    const SizedBox(height: 12),
                    Text(
                      title,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w900),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      subtitle,
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.bodyLarge,
                    ),
                    if (reward != null) ...[
                      const SizedBox(height: 12),
                      Text(
                        '+${reward.amount} ${reward.rewardType.toLowerCase()}',
                        style: Theme.of(context).textTheme.titleMedium
                            ?.copyWith(
                              color: colors.tertiary,
                              fontWeight: FontWeight.w800,
                            ),
                      ),
                    ],
                    const SizedBox(height: 20),
                    FilledButton(
                      onPressed: () {
                        ref
                            .read(pkProvider(widget.roomId).notifier)
                            .dismissResult();
                      },
                      child: const Text('Continue'),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
