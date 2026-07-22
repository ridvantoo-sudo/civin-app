import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/domain/entities/live_session_state.dart';
import 'package:civin/features/live/presentation/live_providers.dart';
import 'package:civin/features/live/presentation/screens/live_room_screen.dart';
import 'package:civin/features/live_chat/presentation/widgets/live_chat_panel.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Composes [LiveRoomScreen] with live chat without modifying the live module.
final class LiveRoomWithChatScreen extends ConsumerWidget {
  const LiveRoomWithChatScreen({required this.room, super.key});

  final LiveRoom room;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final LiveSessionState session = ref.watch(liveSessionProvider);
    final bool canModerate =
        session.role == LiveRole.host && session.room?.id == room.id;

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
          LiveRoomScreen(room: room),
          LiveChatPanel(roomId: room.id, canModerate: canModerate),
        ],
      ),
    );
  }
}
