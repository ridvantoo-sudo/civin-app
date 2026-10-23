import 'package:civin/features/gifts/presentation/gift_providers.dart';
import 'package:civin/features/gifts/presentation/widgets/gift_action_button.dart';
import 'package:civin/features/gifts/presentation/widgets/gift_animation_overlay.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/domain/entities/live_session_state.dart';
import 'package:civin/features/live/presentation/live_providers.dart';
import 'package:civin/features/live/presentation/screens/live_room_screen.dart';
import 'package:civin/features/live_chat/presentation/widgets/live_chat_panel.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Live room composition with chat + gifts.
///
/// Mirrors [LiveRoomWithChatScreen] and adds gift panel / animation overlay
/// without modifying the live or live_chat modules.
final class LiveRoomWithChatAndGiftsScreen extends ConsumerStatefulWidget {
  const LiveRoomWithChatAndGiftsScreen({required this.room, super.key});

  final LiveRoom room;

  @override
  ConsumerState<LiveRoomWithChatAndGiftsScreen> createState() =>
      _LiveRoomWithChatAndGiftsScreenState();
}

final class _LiveRoomWithChatAndGiftsScreenState
    extends ConsumerState<LiveRoomWithChatAndGiftsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(giftProvider(widget.room.id).notifier).startListening();
    });
  }

  @override
  Widget build(BuildContext context) {
    final LiveSessionState session = ref.watch(liveSessionProvider);
    final bool canModerate =
        session.role == LiveRole.host && session.room?.id == widget.room.id;

    return Theme(
      data: ThemeData(
        useMaterial3: true,
        brightness: Brightness.dark,
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF6C4DFF),
          brightness: Brightness.dark,
        ),
      ),
      child: Stack(
        fit: StackFit.expand,
        children: [
          LiveRoomScreen(room: widget.room),
          LiveChatPanel(roomId: widget.room.id, canModerate: canModerate),
          GiftAnimationOverlay(roomId: widget.room.id),
          GiftActionButton(roomId: widget.room.id),
        ],
      ),
    );
  }
}
