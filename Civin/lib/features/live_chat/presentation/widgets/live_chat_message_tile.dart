import 'package:cached_network_image/cached_network_image.dart';
import 'package:civin/features/live_chat/domain/entities/live_message.dart';
import 'package:flutter/material.dart';

final class LiveChatMessageTile extends StatelessWidget {
  const LiveChatMessageTile({
    required this.message,
    required this.canModerate,
    required this.onDelete,
    super.key,
  });

  final LiveMessage message;
  final bool canModerate;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    if (message.isJoin || message.isLeave || message.isSystem) {
      return _SystemLine(message: message);
    }

    final LiveChatUser? user = message.user;
    final Widget content = Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Avatar(url: user?.avatarUrl, name: user?.displayName ?? '?'),
        const SizedBox(width: 8),
        Expanded(
          child: DecoratedBox(
            decoration: BoxDecoration(
              color: Colors.black.withValues(alpha: 0.45),
              borderRadius: BorderRadius.circular(14),
            ),
            child: Padding(
              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    user?.displayName ?? 'Viewer',
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 2),
                  Text(
                    message.message,
                    style: const TextStyle(color: Colors.white, fontSize: 13),
                  ),
                ],
              ),
            ),
          ),
        ),
      ],
    );

    if (!canModerate) return content;

    return GestureDetector(
      onLongPress: onDelete,
      child: content,
    );
  }
}

final class _SystemLine extends StatelessWidget {
  const _SystemLine({required this.message});

  final LiveMessage message;

  @override
  Widget build(BuildContext context) {
    final Color accent = switch (message.type) {
      LiveMessageType.join => const Color(0xFF7CFFB2),
      LiveMessageType.leave => const Color(0xFFFFC48A),
      _ => const Color(0xFFB7C0FF),
    };
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 2),
      child: Text(
        message.message,
        style: TextStyle(
          color: accent.withValues(alpha: 0.95),
          fontSize: 12,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }
}

final class _Avatar extends StatelessWidget {
  const _Avatar({required this.url, required this.name});

  final String? url;
  final String name;

  @override
  Widget build(BuildContext context) {
    final String initial = name.isEmpty ? '?' : name.characters.first.toUpperCase();
    final Widget fallback = CircleAvatar(
      radius: 14,
      backgroundColor: const Color(0xFF6C4DFF),
      child: Text(
        initial,
        style: const TextStyle(color: Colors.white, fontSize: 12),
      ),
    );
    final String? avatarUrl = url;
    if (avatarUrl == null || avatarUrl.isEmpty) return fallback;
    return ClipOval(
      child: CachedNetworkImage(
        imageUrl: avatarUrl,
        width: 28,
        height: 28,
        fit: BoxFit.cover,
        errorWidget: (BuildContext context, String url, Object error) =>
            fallback,
        placeholder: (BuildContext context, String url) => fallback,
      ),
    );
  }
}
