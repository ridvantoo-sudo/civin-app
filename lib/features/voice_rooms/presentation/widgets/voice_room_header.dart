import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:civin/features/voice_rooms/presentation/voice_room_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class VoiceRoomHeader extends ConsumerWidget {
  const VoiceRoomHeader({
    required this.roomId,
    this.onLeave,
    super.key,
  });

  final String roomId;
  final VoidCallback? onLeave;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final VoiceRoomSessionState session = ref.watch(voiceRoomProvider(roomId));
    final VoiceRoom? room = session.room;
    final ThemeData theme = Theme.of(context);

    return Row(
      children: [
        Container(
          width: 44,
          height: 44,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            gradient: const LinearGradient(
              colors: <Color>[Color(0xFF2DD4BF), Color(0xFF0EA5E9)],
            ),
            border: Border.all(color: Colors.white24),
          ),
          child: const Icon(Icons.graphic_eq_rounded, color: Colors.white),
        ),
        const SizedBox(width: 12),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                room?.title ?? 'Voice room',
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: theme.textTheme.titleMedium?.copyWith(
                  fontWeight: FontWeight.w700,
                  color: Colors.white,
                ),
              ),
              const SizedBox(height: 2),
              Text(
                room?.hostName ?? 'Connecting…',
                style: theme.textTheme.bodySmall?.copyWith(
                  color: Colors.white70,
                ),
              ),
            ],
          ),
        ),
        if (session.isHost)
          Padding(
            padding: const EdgeInsets.only(right: 8),
            child: FilledButton.tonal(
              onPressed: session.isBusy
                  ? null
                  : () => ref.read(voiceRoomProvider(roomId).notifier).endRoom(),
              child: const Text('End'),
            ),
          ),
        IconButton.filledTonal(
          onPressed: onLeave,
          icon: const Icon(Icons.close_rounded),
          tooltip: 'Leave',
        ),
      ],
    );
  }
}
