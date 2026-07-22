import 'dart:async';

import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/domain/entities/live_session_state.dart';
import 'package:civin/features/live/presentation/live_providers.dart';
import 'package:civin/features/live/presentation/widgets/live_controls.dart';
import 'package:civin/features/live/presentation/widgets/live_header.dart';
import 'package:civin/features/live/presentation/widgets/live_player.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class LiveRoomScreen extends ConsumerStatefulWidget {
  const LiveRoomScreen({required this.room, super.key});

  final LiveRoom room;

  @override
  ConsumerState<LiveRoomScreen> createState() => _LiveRoomScreenState();
}

final class _LiveRoomScreenState extends ConsumerState<LiveRoomScreen> {
  bool _leaving = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final LiveSessionState session = ref.read(liveSessionProvider);
      final bool isCurrentHost =
          session.role == LiveRole.host && session.room?.id == widget.room.id;
      if (!isCurrentHost) {
        unawaited(ref.read(liveSessionProvider.notifier).join(widget.room));
      }
    });
  }

  Future<void> _leave() async {
    if (_leaving) return;
    setState(() => _leaving = true);
    await ref.read(liveSessionProvider.notifier).leave();
    if (mounted) context.pop();
  }

  @override
  Widget build(BuildContext context) {
    final LiveSessionState session = ref.watch(liveSessionProvider);
    final LiveRoom room = session.room ?? widget.room;
    final LiveRole role = session.role ?? LiveRole.viewer;
    return PopScope(
      canPop: false,
      onPopInvokedWithResult: (bool didPop, Object? result) {
        if (!didPop) unawaited(_leave());
      },
      child: Scaffold(
        backgroundColor: Colors.black,
        body: Stack(
          fit: StackFit.expand,
          children: [
            LivePlayer(
              room: room,
              role: role,
              engine: ref.watch(liveRtcEngineProvider),
              remoteUid: session.remoteUid,
            ),
            const DecoratedBox(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [Colors.black54, Colors.transparent, Colors.black54],
                ),
              ),
            ),
            Align(
              alignment: Alignment.topCenter,
              child: LiveHeader(room: room, onClose: _leave),
            ),
            Align(
              alignment: Alignment.bottomCenter,
              child: LiveControls(
                role: role,
                isMicMuted: session.isMicMuted,
                onToggleMute: role == LiveRole.host
                    ? ref.read(liveSessionProvider.notifier).toggleMute
                    : null,
                onSwitchCamera: role == LiveRole.host
                    ? ref.read(liveSessionProvider.notifier).switchCamera
                    : null,
                onLeave: _leave,
              ),
            ),
            Center(
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 250),
                child: switch (session.status) {
                  LiveConnectionStatus.loading ||
                  LiveConnectionStatus.connecting => const Card(
                    key: ValueKey<String>('connecting'),
                    color: Colors.black54,
                    child: Padding(
                      padding: EdgeInsets.all(16),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          ),
                          SizedBox(width: 12),
                          Text(
                            'Connecting…',
                            style: TextStyle(color: Colors.white),
                          ),
                        ],
                      ),
                    ),
                  ),
                  LiveConnectionStatus.error => Card(
                    key: const ValueKey<String>('error'),
                    color: Theme.of(context).colorScheme.errorContainer,
                    child: Padding(
                      padding: const EdgeInsets.all(16),
                      child: Text(session.message ?? 'Stream error'),
                    ),
                  ),
                  _ => const SizedBox.shrink(key: ValueKey<String>('ready')),
                },
              ),
            ),
          ],
        ),
      ),
    );
  }
}
