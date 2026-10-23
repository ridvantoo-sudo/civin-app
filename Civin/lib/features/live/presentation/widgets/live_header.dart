import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/presentation/widgets/viewer_counter.dart';
import 'package:flutter/material.dart';

final class LiveHeader extends StatelessWidget {
  const LiveHeader({required this.room, required this.onClose, super.key});

  final LiveRoom room;
  final VoidCallback onClose;

  @override
  Widget build(BuildContext context) => SafeArea(
    bottom: false,
    child: Padding(
      padding: const EdgeInsets.all(12),
      child: Row(
        children: [
          CircleAvatar(
            foregroundImage: room.hostAvatarUrl == null
                ? null
                : NetworkImage(room.hostAvatarUrl!),
            child: const Icon(Icons.person_rounded),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  room.hostName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w700,
                  ),
                ),
                Text(
                  room.title,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.white70, fontSize: 12),
                ),
              ],
            ),
          ),
          const SizedBox(width: 8),
          ViewerCounter(count: room.viewerCount),
          const SizedBox(width: 8),
          IconButton.filledTonal(
            tooltip: 'Leave live stream',
            onPressed: onClose,
            icon: const Icon(Icons.close_rounded),
          ),
        ],
      ),
    ),
  );
}
