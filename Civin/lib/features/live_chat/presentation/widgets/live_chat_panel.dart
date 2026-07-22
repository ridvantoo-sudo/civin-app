import 'dart:async';

import 'package:civin/features/live_chat/domain/entities/live_message.dart';
import 'package:civin/features/live_chat/presentation/live_chat_providers.dart';
import 'package:civin/features/live_chat/presentation/widgets/live_chat_input.dart';
import 'package:civin/features/live_chat/presentation/widgets/live_chat_message_tile.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class LiveChatPanel extends ConsumerStatefulWidget {
  const LiveChatPanel({
    required this.roomId,
    this.canModerate = false,
    super.key,
  });

  final String roomId;
  final bool canModerate;

  @override
  ConsumerState<LiveChatPanel> createState() => _LiveChatPanelState();
}

final class _LiveChatPanelState extends ConsumerState<LiveChatPanel> {
  final ScrollController _scrollController = ScrollController();
  int _lastCount = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final ChatController controller = ref.read(
        chatProvider(widget.roomId).notifier,
      );
      controller.updateModeration(canModerate: widget.canModerate);
    });
  }

  @override
  void didUpdateWidget(covariant LiveChatPanel oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.canModerate != widget.canModerate) {
      final ChatController controller = ref.read(
        chatProvider(widget.roomId).notifier,
      );
      controller.updateModeration(canModerate: widget.canModerate);
    }
  }

  @override
  void dispose() {
    _scrollController.dispose();
    super.dispose();
  }

  void _autoScroll(int count) {
    if (count <= _lastCount) {
      _lastCount = count;
      return;
    }
    _lastCount = count;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scrollController.hasClients) return;
      unawaited(
        _scrollController.animateTo(
          _scrollController.position.maxScrollExtent,
          duration: const Duration(milliseconds: 280),
          curve: Curves.easeOutCubic,
        ),
      );
    });
  }

  Future<void> _confirmDelete(LiveMessage message) async {
    final bool? confirmed = await showModalBottomSheet<bool>(
      context: context,
      backgroundColor: const Color(0xFF17171F),
      showDragHandle: true,
      builder: (BuildContext context) => SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const Text(
                'Moderator controls',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 16,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                'Delete message from ${message.user?.displayName ?? 'viewer'}?',
                style: TextStyle(color: Colors.white.withValues(alpha: 0.75)),
              ),
              const SizedBox(height: 16),
              FilledButton(
                style: FilledButton.styleFrom(
                  backgroundColor: Theme.of(context).colorScheme.error,
                ),
                onPressed: () => Navigator.of(context).pop(true),
                child: const Text('Delete message'),
              ),
            ],
          ),
        ),
      ),
    );
    if (confirmed == true && mounted) {
      final ChatController controller = ref.read(
        chatProvider(widget.roomId).notifier,
      );
      await controller.delete(message);
    }
  }

  @override
  Widget build(BuildContext context) {
    final String roomId = widget.roomId;
    final List<LiveMessage> messages = ref.watch(messagesProvider(roomId));
    final ChatConnectionStatus connection = ref.watch(
      connectionProvider(roomId),
    );
    final LiveChatState chat = ref.watch(chatProvider(roomId));
    _autoScroll(messages.length);

    final Size screen = MediaQuery.sizeOf(context);
    final double panelHeight = screen.height * 0.42;

    return SafeArea(
      child: Align(
        alignment: Alignment.bottomLeft,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(12, 0, 12, 92),
          child: SizedBox(
            width: screen.width * 0.82,
            height: panelHeight,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                _ConnectionBadge(status: connection),
                const SizedBox(height: 8),
                Expanded(
                  child: DecoratedBox(
                    decoration: BoxDecoration(
                      gradient: LinearGradient(
                        begin: Alignment.topCenter,
                        end: Alignment.bottomCenter,
                        colors: [
                          Colors.black.withValues(alpha: 0.05),
                          Colors.black.withValues(alpha: 0.35),
                        ],
                      ),
                    ),
                    child: ListView.builder(
                      controller: _scrollController,
                      padding: const EdgeInsets.only(bottom: 8),
                      itemCount: messages.length,
                      itemBuilder: (BuildContext context, int index) {
                        final LiveMessage message = messages[index];
                        return Padding(
                          padding: const EdgeInsets.only(bottom: 6),
                          child: LiveChatMessageTile(
                            key: ValueKey<String>(message.id),
                            message: message,
                            canModerate: chat.canModerate,
                            onDelete: () => unawaited(_confirmDelete(message)),
                          ),
                        );
                      },
                    ),
                  ),
                ),
                if (chat.errorMessage != null) ...[
                  const SizedBox(height: 6),
                  Text(
                    chat.errorMessage!,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontSize: 12,
                    ),
                  ),
                ],
                const SizedBox(height: 8),
                LiveChatInput(
                  enabled: connection == ChatConnectionStatus.connected,
                  isSending: chat.isSending,
                  onSend: (String value) {
                    final ChatController controller = ref.read(
                      chatProvider(roomId).notifier,
                    );
                    return controller.send(value);
                  },
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

final class _ConnectionBadge extends StatelessWidget {
  const _ConnectionBadge({required this.status});

  final ChatConnectionStatus status;

  @override
  Widget build(BuildContext context) {
    final (String label, Color color) = switch (status) {
      ChatConnectionStatus.connecting => ('Connecting', Colors.amberAccent),
      ChatConnectionStatus.connected => ('Live chat', const Color(0xFF7CFFB2)),
      ChatConnectionStatus.disconnected => ('Disconnected', Colors.white54),
      ChatConnectionStatus.error => ('Chat error', Colors.redAccent),
    };
    return Align(
      alignment: Alignment.centerLeft,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
        decoration: BoxDecoration(
          color: Colors.black.withValues(alpha: 0.45),
          borderRadius: BorderRadius.circular(999),
          border: Border.all(color: color.withValues(alpha: 0.55)),
        ),
        child: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            Container(
              width: 7,
              height: 7,
              decoration: BoxDecoration(color: color, shape: BoxShape.circle),
            ),
            const SizedBox(width: 6),
            Text(
              label,
              style: TextStyle(
                color: color,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
