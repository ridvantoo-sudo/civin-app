import 'dart:async';

import 'package:civin/core/network/dio_client.dart';
import 'package:civin/core/router/router.dart';
import 'package:civin/features/voice_rooms/data/models/voice_room_model.dart';
import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:civin/features/voice_rooms/presentation/voice_room_providers.dart';
import 'package:civin/features/voice_rooms/presentation/widgets/voice_room_animations.dart';
import 'package:dio/dio.dart';
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
  bool _loadingRooms = true;
  List<VoiceRoom> _rooms = const <VoiceRoom>[];

  @override
  void initState() {
    super.initState();
    _loadRooms();
  }

  @override
  void dispose() {
    _roomIdController.dispose();
    super.dispose();
  }

  Future<void> _loadRooms() async {
    try {
      final Response<dynamic> response = await ref
          .read(dioClientProvider)
          .get<dynamic>('/api/v1/voice');
      final Object? data = response.data is Map
          ? (response.data as Map)['data']
          : null;
      final List<VoiceRoom> rooms = data is List
          ? data
                .whereType<Map>()
                .map(
                  (Map item) => VoiceRoomModel.roomFromJson(
                    Map<String, dynamic>.from(item),
                  ),
                )
                .toList(growable: false)
          : const <VoiceRoom>[];
      if (!mounted) return;
      setState(() {
        _rooms = rooms;
        _loadingRooms = false;
      });
    } on Object {
      if (!mounted) return;
      setState(() => _loadingRooms = false);
    }
  }

  Future<void> _join() async {
    await _joinRoom(_roomIdController.text.trim());
  }

  Future<void> _joinRoom(String roomId) async {
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
                        style: FilledButton.styleFrom(
                          minimumSize: const Size(0, 40),
                          tapTargetSize: MaterialTapTargetSize.shrinkWrap,
                        ),
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
                  const SizedBox(height: 28),
                  Text(
                    'Live rooms',
                    style: theme.textTheme.titleSmall?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 10),
                  Expanded(child: _roomList(theme)),
                ],
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _roomList(ThemeData theme) {
    if (_loadingRooms) {
      return const Center(child: CircularProgressIndicator());
    }
    if (_rooms.isEmpty) {
      return Center(
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
                  color: const Color(0xFF2DD4BF).withValues(alpha: 0.16),
                  border: Border.all(color: const Color(0xFF2DD4BF)),
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
              'No live voice rooms yet.\nCreate one to open seats.',
              textAlign: TextAlign.center,
              style: theme.textTheme.bodyLarge?.copyWith(color: Colors.white70),
            ),
          ],
        ),
      );
    }
    return RefreshIndicator(
      onRefresh: _loadRooms,
      child: ListView.separated(
        itemCount: _rooms.length,
        separatorBuilder: (BuildContext context, int index) =>
            const SizedBox(height: 8),
        itemBuilder: (BuildContext context, int index) {
          final VoiceRoom room = _rooms[index];
          final String host = room.host?.displayName ?? 'Host';
          return ListTile(
            shape: RoundedRectangleBorder(
              borderRadius: BorderRadius.circular(16),
            ),
            tileColor: Colors.white.withValues(alpha: 0.06),
            leading: const Icon(Icons.mic_rounded, color: Color(0xFF2DD4BF)),
            title: Text(room.title, style: const TextStyle(color: Colors.white)),
            subtitle: Text(
              '$host · ${room.participantCount} listening',
              style: const TextStyle(color: Colors.white70),
            ),
            onTap: _joining ? null : () => unawaited(_joinRoom(room.id)),
          );
        },
      ),
    );
  }
}
