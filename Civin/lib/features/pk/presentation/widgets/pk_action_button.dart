import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/domain/entities/live_session_state.dart';
import 'package:civin/features/live/presentation/live_providers.dart';
import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:civin/features/pk/presentation/pk_providers.dart';
import 'package:civin/features/pk/presentation/screens/pk_request_dialog.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Floating PK entry for hosts (request / accept / start / end).
final class PkActionButton extends ConsumerWidget {
  const PkActionButton({required this.roomId, super.key});

  final String roomId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final LiveSessionState session = ref.watch(liveSessionProvider);
    final bool isHost =
        session.role == LiveRole.host && session.room?.id == roomId;
    if (!isHost) return const SizedBox.shrink();

    final PkRoomState pkState = ref.watch(pkProvider(roomId));
    final PkBattle? battle = pkState.battle;

    return SafeArea(
      child: Align(
        alignment: Alignment.bottomLeft,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 0, 96),
          child: FloatingActionButton.extended(
            heroTag: 'pk-fab-$roomId',
            backgroundColor: Theme.of(context).colorScheme.tertiaryContainer,
            foregroundColor: Theme.of(context).colorScheme.onTertiaryContainer,
            onPressed: pkState.isBusy
                ? null
                : () => _onPressed(context, ref, battle),
            icon: const Icon(Icons.sports_kabaddi_rounded),
            label: Text(_label(battle)),
          ),
        ),
      ),
    );
  }

  String _label(PkBattle? battle) {
    if (battle == null) return 'PK';
    if (battle.isRunning) return 'End PK';
    if (battle.isWaiting && battle.scores.isNotEmpty) return 'Start PK';
    if (battle.isWaiting) return 'Waiting';
    return 'PK';
  }

  Future<void> _onPressed(
    BuildContext context,
    WidgetRef ref,
    PkBattle? battle,
  ) async {
    final PkController controller = ref.read(pkProvider(roomId).notifier);

    if (battle != null && battle.isRunning) {
      final bool? confirm = await showDialog<bool>(
        context: context,
        builder: (BuildContext context) => AlertDialog(
          title: const Text('End PK battle?'),
          content: const Text('Scores will be finalized and a winner chosen.'),
          actions: [
            TextButton(
              onPressed: () => Navigator.of(context).pop(false),
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () => Navigator.of(context).pop(true),
              child: const Text('End'),
            ),
          ],
        ),
      );
      if (confirm == true) await controller.endPk();
      return;
    }

    if (battle != null && battle.isWaiting && battle.scores.isNotEmpty) {
      await controller.startPk();
      return;
    }

    if (battle != null && battle.isWaiting && battle.scores.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Waiting for opponent to accept PK')),
      );
      return;
    }

    await showPkRequestDialog(context, roomId: roomId);
  }
}
