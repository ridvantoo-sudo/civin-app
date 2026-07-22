import 'dart:async';

import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:civin/features/voice_rooms/presentation/voice_room_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class VoiceChatArea extends ConsumerStatefulWidget {
  const VoiceChatArea({required this.roomId, super.key});

  final String roomId;

  @override
  ConsumerState<VoiceChatArea> createState() => _VoiceChatAreaState();
}

final class _VoiceChatAreaState extends ConsumerState<VoiceChatArea> {
  final TextEditingController _controller = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  int _lastCount = 0;

  @override
  void dispose() {
    _controller.dispose();
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
          duration: const Duration(milliseconds: 260),
          curve: Curves.easeOutCubic,
        ),
      );
    });
  }

  void _send() {
    final String text = _controller.text;
    ref.read(voiceRoomProvider(widget.roomId).notifier).sendChatMessage(text);
    _controller.clear();
  }

  @override
  Widget build(BuildContext context) {
    final List<VoiceChatMessage> messages = ref.watch(
      voiceRoomProvider(widget.roomId).select(
        (VoiceRoomSessionState s) => s.chatMessages,
      ),
    );
    _autoScroll(messages.length);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Expanded(
          child: messages.isEmpty
              ? Center(
                  child: Text(
                    'Say hello to the room',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: Colors.white54,
                    ),
                  ),
                )
              : ListView.builder(
                  controller: _scrollController,
                  padding: const EdgeInsets.symmetric(horizontal: 4),
                  itemCount: messages.length,
                  itemBuilder: (BuildContext context, int index) {
                    final VoiceChatMessage message = messages[index];
                    return Padding(
                      padding: const EdgeInsets.symmetric(vertical: 4),
                      child: TweenAnimationBuilder<double>(
                        tween: Tween<double>(begin: 0, end: 1),
                        duration: const Duration(milliseconds: 240),
                        curve: Curves.easeOutCubic,
                        builder:
                            (
                              BuildContext context,
                              double value,
                              Widget? child,
                            ) => Opacity(
                              opacity: value,
                              child: Transform.translate(
                                offset: Offset(0, 8 * (1 - value)),
                                child: child,
                              ),
                            ),
                        child: RichText(
                          text: TextSpan(
                            children: [
                              TextSpan(
                                text: '${message.userName} ',
                                style: const TextStyle(
                                  color: Color(0xFF2DD4BF),
                                  fontWeight: FontWeight.w700,
                                ),
                              ),
                              TextSpan(
                                text: message.text,
                                style: const TextStyle(color: Colors.white),
                              ),
                            ],
                          ),
                        ),
                      ),
                    );
                  },
                ),
        ),
        const SizedBox(height: 8),
        Row(
          children: [
            Expanded(
              child: TextField(
                controller: _controller,
                style: const TextStyle(color: Colors.white),
                decoration: InputDecoration(
                  hintText: 'Chat…',
                  hintStyle: const TextStyle(color: Colors.white38),
                  filled: true,
                  fillColor: Colors.white.withValues(alpha: 0.06),
                  border: OutlineInputBorder(
                    borderRadius: BorderRadius.circular(24),
                    borderSide: BorderSide.none,
                  ),
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 12,
                  ),
                ),
                onSubmitted: (_) => _send(),
              ),
            ),
            const SizedBox(width: 8),
            IconButton.filled(
              onPressed: _send,
              icon: const Icon(Icons.send_rounded),
            ),
          ],
        ),
      ],
    );
  }
}
