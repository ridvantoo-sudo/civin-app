import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:civin/features/pk/presentation/pk_providers.dart';
import 'package:civin/features/pk/presentation/widgets/pk_score_bar.dart';
import 'package:civin/features/pk/presentation/widgets/pk_split_view.dart';
import 'package:civin/features/pk/presentation/widgets/pk_timer_badge.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Live PK battle HUD: split hosts, score bars, and countdown timer.
final class PkBattleScreen extends ConsumerWidget {
  const PkBattleScreen({required this.roomId, super.key});

  final String roomId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final PkRoomState pkState = ref.watch(pkProvider(roomId));
    final PkBattle? battle = pkState.battle;
    if (battle == null || !battle.isRunning) {
      return const SizedBox.shrink();
    }

    final PkScoreView score = ref.watch(pkScoreProvider(roomId));
    final PkTimerState timer = ref.watch(pkTimerProvider(roomId));

    return IgnorePointer(
      ignoring: true,
      child: Material(
        type: MaterialType.transparency,
        child: Stack(
          fit: StackFit.expand,
          children: [
            // Soft vignette so host labels stay readable over live video.
            DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Colors.black.withValues(alpha: 0.45),
                    Colors.transparent,
                    Colors.black.withValues(alpha: 0.55),
                  ],
                  stops: const <double>[0, 0.35, 1],
                ),
              ),
            ),
            PkSplitView(score: score),
            SafeArea(
              child: Align(
                alignment: Alignment.topCenter,
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 0),
                  child: Column(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      PkTimerBadge(timer: timer),
                      const SizedBox(height: 12),
                      PkScoreBar(score: score),
                      const SizedBox(height: 8),
                      Text(
                        'PK BATTLE',
                        style: Theme.of(context).textTheme.labelLarge?.copyWith(
                          letterSpacing: 2.4,
                          fontWeight: FontWeight.w800,
                          color: Colors.white.withValues(alpha: 0.9),
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
