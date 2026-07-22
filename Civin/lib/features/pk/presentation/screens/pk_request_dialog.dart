import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/presentation/live_providers.dart';
import 'package:civin/features/pk/presentation/pk_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

Future<void> showPkRequestDialog(
  BuildContext context, {
  required String roomId,
}) {
  return showDialog<void>(
    context: context,
    barrierDismissible: true,
    builder: (BuildContext context) => PkRequestDialog(roomId: roomId),
  );
}

/// Host dialog to send or accept a PK battle request.
final class PkRequestDialog extends ConsumerStatefulWidget {
  const PkRequestDialog({required this.roomId, super.key});

  final String roomId;

  @override
  ConsumerState<PkRequestDialog> createState() => _PkRequestDialogState();
}

final class _PkRequestDialogState extends ConsumerState<PkRequestDialog> {
  final TextEditingController _opponentController = TextEditingController();
  int _durationSeconds = 180;
  String? _selectedOpponentId;

  @override
  void dispose() {
    _opponentController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final pkState = ref.watch(pkProvider(widget.roomId));
    final roomsAsync = ref.watch(liveRoomsProvider);
    final ColorScheme colors = Theme.of(context).colorScheme;

    return Theme(
      data: ThemeData(
        useMaterial3: true,
        brightness: Brightness.dark,
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF6C4DFF),
          brightness: Brightness.dark,
        ),
      ),
      child: AlertDialog(
        backgroundColor: const Color(0xFF16161E),
        title: const Text('PK Battle'),
        content: SizedBox(
          width: 360,
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Text(
                'Challenge another live host or accept an incoming request.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: colors.onSurfaceVariant,
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: _opponentController,
                decoration: const InputDecoration(
                  labelText: 'Opponent room ID',
                  hintText: 'Paste room UUID or pick below',
                  border: OutlineInputBorder(),
                ),
                onChanged: (_) => setState(() => _selectedOpponentId = null),
              ),
              const SizedBox(height: 12),
              roomsAsync.when(
                data: (List<LiveRoom> rooms) {
                  final List<LiveRoom> opponents = rooms
                      .where((LiveRoom room) => room.id != widget.roomId)
                      .where((LiveRoom room) => room.isLive)
                      .take(8)
                      .toList(growable: false);
                  if (opponents.isEmpty) {
                    return Text(
                      'No other live rooms available right now.',
                      style: Theme.of(context).textTheme.bodySmall,
                    );
                  }
                  return Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: opponents.map((LiveRoom room) {
                      final bool selected = _selectedOpponentId == room.id;
                      return ChoiceChip(
                        selected: selected,
                        label: Text(room.hostName),
                        onSelected: (_) {
                          setState(() {
                            _selectedOpponentId = room.id;
                            _opponentController.text = room.id;
                          });
                        },
                      );
                    }).toList(growable: false),
                  );
                },
                loading: () => const LinearProgressIndicator(),
                error: (_, _) => const SizedBox.shrink(),
              ),
              const SizedBox(height: 16),
              Text(
                'Duration: $_durationSeconds s',
                style: Theme.of(context).textTheme.labelLarge,
              ),
              Slider(
                value: _durationSeconds.toDouble(),
                min: 30,
                max: 300,
                divisions: 9,
                label: '$_durationSeconds s',
                onChanged: (double value) {
                  setState(() => _durationSeconds = value.round());
                },
              ),
              if (pkState.errorMessage != null) ...[
                const SizedBox(height: 8),
                Text(
                  pkState.errorMessage!,
                  style: TextStyle(color: colors.error),
                ),
              ],
            ],
          ),
        ),
        actions: [
          TextButton(
            onPressed: pkState.isBusy
                ? null
                : () => Navigator.of(context).pop(),
            child: const Text('Close'),
          ),
          OutlinedButton(
            onPressed: pkState.isBusy ? null : _accept,
            child: pkState.isBusy
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(strokeWidth: 2),
                  )
                : const Text('Accept PK'),
          ),
          FilledButton(
            onPressed: pkState.isBusy ? null : _request,
            child: const Text('Send request'),
          ),
        ],
      ),
    );
  }

  Future<void> _request() async {
    final String opponentId = _opponentController.text.trim();
    if (opponentId.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter an opponent room ID')),
      );
      return;
    }

    final bool ok = await ref
        .read(pkProvider(widget.roomId).notifier)
        .requestPk(
          opponentRoomId: opponentId,
          durationSeconds: _durationSeconds,
        );
    if (!mounted) return;
    if (ok) {
      if (Navigator.of(context).canPop()) {
        Navigator.of(context).pop();
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('PK request sent. Waiting for accept.')),
      );
    }
  }

  Future<void> _accept() async {
    final bool ok = await ref
        .read(pkProvider(widget.roomId).notifier)
        .acceptPk();
    if (!mounted) return;
    if (ok) {
      if (Navigator.of(context).canPop()) {
        Navigator.of(context).pop();
      }
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('PK accepted. Tap Start PK to begin.')),
      );
    }
  }
}
