import 'dart:async';

import 'package:civin/core/router/router.dart';
import 'package:civin/features/voice_rooms/presentation/voice_room_providers.dart';
import 'package:civin/features/voice_rooms/presentation/widgets/voice_room_animations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class VoiceRoomHome extends ConsumerStatefulWidget {
  const VoiceRoomHome({super.key});

  @override
  ConsumerState<VoiceRoomHome> createState() => _VoiceRoomHomeState();
}

final class _VoiceRoomHomeState extends ConsumerState<VoiceRoomHome> {
  final TextEditingController _roomIdController = TextEditingController();
  bool _joining = false;

  @override
  void dispose() {
    _roomIdController.dispose();
    super.dispose();
  }

  Future<void> _join() async {
    final String roomId = _roomIdController.text.trim();
    if (roomId.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Enter a room ID to join.')),
      );
      return;
    }
    setState(() => _joining = true);
    final bool ok = await ref.read(voiceRoomProvider(roomId).notifier).join();
    if (!mounted) return;
    setState(() => _joining = false);
    if (!ok) {
      final String? error = ref.read(voiceRoomProvider(roomId)).errorMessage;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(error ?? 'Could not join room.')),
      );
      return;
    }
    unawaited(context.push(AppRoutes.voiceRoomPath(roomId)));
  }

  @override
  Widget build(BuildContext context) {
    final ThemeData theme = Theme.of(context);

    return Theme(
      data: theme.copyWith(
        brightness: Brightness.dark,
        colorScheme: ColorScheme.fromSeed(
          seedColor: const Color(0xFF2DD4BF),
          brightness: Brightness.dark,
        ),
      ),
      child: Scaffold(
        body: VoiceRoomAmbientBackground(
          child: SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      Text(
                        'Voice',
                        style: theme.textTheme.headlineMedium?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const Spacer(),
                      FilledButton.icon(
                        onPressed: () => context.push(AppRoutes.createVoiceRoom),
                        icon: const Icon(Icons.add_rounded),
                        label: const Text('Create'),
                      ),
                    ],
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Join an open mic or host your own room.',
                    style: theme.textTheme.bodyMedium?.copyWith(
                      color: Colors.white70,
                    ),
                  ),
                  const SizedBox(height: 28),
                  Text(
                    'Join with room ID',
                    style: theme.textTheme.titleSmall?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 10),
                  TextField(
                    controller: _roomIdController,
                    style: const TextStyle(color: Colors.white),
                    decoration: InputDecoration(
                      hintText: 'Paste room ID',
                      hintStyle: const TextStyle(color: Colors.white38),
                      filled: true,
                      fillColor: Colors.white.withValues(alpha: 0.06),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(16),
                        borderSide: BorderSide.none,
                      ),
                    ),
                  ),
                  const SizedBox(height: 12),
                  FilledButton.tonal(
                    onPressed: _joining ? null : () => unawaited(_join()),
                    child: _joining
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Join room'),
                  ),
                  const SizedBox(height: 36),
                  Expanded(
                    child: Center(
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          VoiceSpeakingPulse(
                            active: true,
                            child: Container(
                              width: 88,
                              height: 88,
                              decoration: BoxDecoration(
                                shape: BoxShape.circle,
                                color: const Color(
                                  0xFF2DD4BF,
                                ).withValues(alpha: 0.16),
                                border: Border.all(
                                  color: const Color(0xFF2DD4BF),
                                ),
                              ),
                              child: const Icon(
                                Icons.mic_none_rounded,
                                size: 40,
                                color: Colors.white,
                              ),
                            ),
                          ),
                          const SizedBox(height: 18),
                          Text(
                            'Create a room to open seats\nand invite speakers.',
                            textAlign: TextAlign.center,
                            style: theme.textTheme.bodyLarge?.copyWith(
                              color: Colors.white70,
                            ),
                          ),
                        ],
                      ),
                    ),
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
