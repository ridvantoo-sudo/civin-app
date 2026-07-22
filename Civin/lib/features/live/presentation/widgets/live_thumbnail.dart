import 'package:cached_network_image/cached_network_image.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/presentation/widgets/viewer_counter.dart';
import 'package:flutter/material.dart';

final class LiveThumbnail extends StatelessWidget {
  const LiveThumbnail({required this.room, required this.onTap, super.key});

  final LiveRoom room;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    clipBehavior: Clip.antiAlias,
    child: InkWell(
      onTap: onTap,
      child: Stack(
        fit: StackFit.expand,
        children: [
          if (room.thumbnailUrl == null)
            const DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: [Color(0xFF392A74), Color(0xFF0D8A91)],
                ),
              ),
              child: Icon(Icons.live_tv_rounded, size: 52),
            )
          else
            CachedNetworkImage(
              imageUrl: room.thumbnailUrl!,
              fit: BoxFit.cover,
              errorWidget: (_, _, _) => const Icon(Icons.live_tv_rounded),
            ),
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Colors.transparent, Colors.black87],
              ),
            ),
          ),
          Positioned(
            top: 10,
            left: 10,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
              decoration: BoxDecoration(
                color: Colors.redAccent,
                borderRadius: BorderRadius.circular(6),
              ),
              child: const Text(
                'LIVE',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 11,
                ),
              ),
            ),
          ),
          Positioned(
            top: 8,
            right: 8,
            child: ViewerCounter(count: room.viewerCount),
          ),
          Positioned(
            left: 12,
            right: 12,
            bottom: 12,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  room.title,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  room.hostName,
                  maxLines: 1,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: Colors.white70),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}
