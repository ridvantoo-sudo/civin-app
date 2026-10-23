import 'dart:async';

import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:civin/features/voice_rooms/presentation/voice_room_providers.dart';
import 'package:civin/features/voice_rooms/presentation/widgets/voice_audience_count.dart';
import 'package:civin/features/voice_rooms/presentation/widgets/voice_chat_area.dart';
import 'package:civin/features/voice_rooms/presentation/widgets/voice_mic_seats.dart';
import 'package:civin/features/voice_rooms/presentation/widgets/voice_room_animations.dart';
import 'package:civin/features/voice_rooms/presentation/widgets/voice_room_header.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class VoiceRoomScreen extends ConsumerStatefulWidget {
  const VoiceRoomScreen({
    required this.roomId,
    this.connection,
    this.initialRoom,
    super.key,
  });

  final String roomId;
  final VoiceRoomConnection? connection;
  final VoiceRoom? initialRoom;

  @override
  ConsumerState<VoiceRoomScreen> createState() => _VoiceRoomScreenState();
}

final class _VoiceRoomScreenState extends ConsumerState<VoiceRoomScreen> {
  bool _bootstrapped = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(_bootstrap());
    });
  }

  Future<void> _bootstrap() async {
    if (_bootstrapped) return;
    _bootstrapped = true;

    final VoiceRoomController controller = ref.read(
      voiceRoomProvider(widget.roomId).notifier,
    );
    final VoiceRoomSessionState existing = ref.read(
      voiceRoomProvider(widget.roomId),
    );
    final VoiceRoomConnection? connection = widget.connection;
    final VoiceRoom? initial = widget.initialRoom ?? connection?.room;

    if (connection != null) {
      if (existing.room == null) {
        final bool asHost =
            existing.role == VoiceRole.host || connection.room.host != null;
        controller.seedRoom(
          connection.room,
          role: asHost ? VoiceRole.host : VoiceRole.audience,
          userId: connection.room.host?.id,
        );
      }
      await controller.startListening();
      if (ref.read(voiceConnectionProvider(widget.roomId)).status ==
          VoiceConnectionStatus.disconnected) {
        await ref
            .read(voiceConnectionProvider(widget.roomId).notifier)
            .connect(
              connection.rtc,
              asSpeaker: ref.read(voiceRoomProvider(widget.roomId)).isSpeaker,
            );
      }
      return;
    }

    if (initial != null && existing.room == null) {
      controller.seedRoom(initial, role: VoiceRole.audience);
    }

    final VoiceRoomSessionState next = ref.read(
      voiceRoomProvider(widget.roomId),
    );
    if (next.room == null) {
      await controller.join();
    } else {
      await controller.startListening();
    }
  }

  Future<void> _leave() async {
    final VoiceRoomSessionState session = ref.read(
      voiceRoomProvider(widget.roomId),
    );
    final bool ok = await ref
        .read(voiceRoomProvider(widget.roomId).notifier)
        .leave();
    if (!mounted) return;
    if (!ok && session.errorMessage != null) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(session.errorMessage!)));
      return;
    }
    context.pop();
  }

  @override
  Widget build(BuildContext context) {
    final VoiceRoomSessionState session = ref.watch(
      voiceRoomProvider(widget.roomId),
    );
    final VoiceConnectionState connection = ref.watch(
      voiceConnectionProvider(widget.roomId),
    );
    final ThemeData dark = ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: ColorScheme.fromSeed(
        seedColor: const Color(0xFF2DD4BF),
        brightness: Brightness.dark,
      ),
    );

    return Theme(
      data: dark,
      child: Scaffold(
        body: VoiceRoomAmbientBackground(
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: Column(
                children: [
                  VoiceRoomHeader(
                    roomId: widget.roomId,
                    onLeave: () => unawaited(_leave()),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      VoiceAudienceCount(roomId: widget.roomId),
                      const Spacer(),
                      if (session.isSpeaker)
                        IconButton.filledTonal(
                          tooltip: connection.isMicMuted ? 'Unmute' : 'Mute',
                          onPressed: () => ref
                              .read(
                                voiceConnectionProvider(widget.roomId).notifier,
                              )
                              .toggleMute(),
                          icon: Icon(
                            connection.isMicMuted
                                ? Icons.mic_off_rounded
                                : Icons.mic_rounded,
                          ),
                        ),
                      const SizedBox(width: 4),
                      _ConnectionChip(status: connection.status),
                    ],
                  ),
                  if (session.errorMessage != null) ...[
                    const SizedBox(height: 8),
                    Text(
                      session.errorMessage!,
                      style: const TextStyle(color: Color(0xFFF87171)),
                    ),
                  ],
                  const SizedBox(height: 16),
                  Expanded(
                    flex: 3,
                    child: VoiceMicSeats(roomId: widget.roomId),
                  ),
                  const SizedBox(height: 12),
                  Expanded(
                    flex: 2,
                    child: VoiceChatArea(roomId: widget.roomId),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }
}

final class _ConnectionChip extends StatelessWidget {
  const _ConnectionChip({required this.status});

  final VoiceConnectionStatus status;

  @override
  Widget build(BuildContext context) {
    final String label = switch (status) {
      VoiceConnectionStatus.connected => 'Live',
      VoiceConnectionStatus.connecting => 'Connecting',
      VoiceConnectionStatus.error => 'Error',
      VoiceConnectionStatus.disconnected => 'Offline',
    };
    final Color color = switch (status) {
      VoiceConnectionStatus.connected => const Color(0xFF2DD4BF),
      VoiceConnectionStatus.connecting => const Color(0xFFFBBF24),
      VoiceConnectionStatus.error => const Color(0xFFF87171),
      VoiceConnectionStatus.disconnected => Colors.white38,
    };

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
      decoration: BoxDecoration(
        color: color.withValues(alpha: 0.16),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: color.withValues(alpha: 0.5)),
      ),
      child: Text(
        label,
        style: TextStyle(
          color: color,
          fontWeight: FontWeight.w700,
          fontSize: 12,
        ),
      ),
    );
  }
}
