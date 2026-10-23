import 'package:civin/features/gifts/presentation/screens/live_room_with_chat_and_gifts_screen.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/pk/presentation/pk_providers.dart';
import 'package:civin/features/pk/presentation/screens/pk_battle_screen.dart';
import 'package:civin/features/pk/presentation/screens/pk_result_screen.dart';
import 'package:civin/features/pk/presentation/widgets/pk_action_button.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Live room composition with chat, gifts, and PK battle overlays.
///
/// Wraps [LiveRoomWithChatAndGiftsScreen] without modifying live, chat, gifts,
/// or wallet modules.
final class LiveRoomWithPkScreen extends ConsumerStatefulWidget {
  const LiveRoomWithPkScreen({required this.room, super.key});

  final LiveRoom room;

  @override
  ConsumerState<LiveRoomWithPkScreen> createState() =>
      _LiveRoomWithPkScreenState();
}

final class _LiveRoomWithPkScreenState
    extends ConsumerState<LiveRoomWithPkScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(pkProvider(widget.room.id).notifier).startListening();
    });
  }

  @override
  Widget build(BuildContext context) {
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
          LiveRoomWithChatAndGiftsScreen(room: widget.room),
          PkBattleScreen(roomId: widget.room.id),
          PkResultScreen(roomId: widget.room.id),
          PkActionButton(roomId: widget.room.id),
        ],
      ),
    );
  }
}
