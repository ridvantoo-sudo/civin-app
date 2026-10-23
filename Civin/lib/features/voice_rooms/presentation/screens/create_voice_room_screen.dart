import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/router/router.dart';
import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:civin/features/voice_rooms/presentation/voice_room_providers.dart';
import 'package:civin/features/voice_rooms/presentation/widgets/voice_room_animations.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class CreateVoiceRoom extends ConsumerStatefulWidget {
  const CreateVoiceRoom({super.key});

  @override
  ConsumerState<CreateVoiceRoom> createState() => _CreateVoiceRoomState();
}

final class _CreateVoiceRoomState extends ConsumerState<CreateVoiceRoom> {
  final TextEditingController _titleController = TextEditingController();
  final TextEditingController _descriptionController = TextEditingController();
  double _seatCount = 8;
  bool _busy = false;

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    super.dispose();
  }

  Future<void> _create() async {
    final String title = _titleController.text.trim();
    if (title.length < 2) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Add a title of at least 2 characters.')),
      );
      return;
    }

    setState(() => _busy = true);
    final RepositoryResult<VoiceRoomConnection> result = await ref.read(
      createVoiceRoomUseCaseProvider,
    )(
      title: title,
      description: _descriptionController.text.trim().isEmpty
          ? null
          : _descriptionController.text.trim(),
      seatCount: _seatCount.round(),
    );

    if (!mounted) return;

    await result.fold(
      onSuccess: (VoiceRoomConnection connection) async {
        final String roomId = connection.room.id;
        final VoiceRoomController controller = ref.read(
          voiceRoomProvider(roomId).notifier,
        );
        controller.seedRoom(
          connection.room,
          role: VoiceRole.host,
          userId: connection.room.host?.id,
        );
        unawaited(controller.startListening());
        unawaited(
          ref
              .read(voiceConnectionProvider(roomId).notifier)
              .connect(connection.rtc, asSpeaker: true),
        );
        setState(() => _busy = false);
        context.pushReplacement(
          AppRoutes.voiceRoomPath(roomId),
          extra: connection,
        );
      },
      onFailure: (failure) async {
        setState(() => _busy = false);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(failure.message)));
      },
    );
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
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  Row(
                    children: [
                      IconButton.filledTonal(
                        onPressed: () => context.pop(),
                        icon: const Icon(Icons.arrow_back_rounded),
                      ),
                      const SizedBox(width: 12),
                      Text(
                        'Create voice room',
                        style: theme.textTheme.titleLarge?.copyWith(
                          color: Colors.white,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 28),
                  TextField(
                    controller: _titleController,
                    style: const TextStyle(color: Colors.white),
                    decoration: InputDecoration(
                      labelText: 'Title',
                      labelStyle: const TextStyle(color: Colors.white70),
                      filled: true,
                      fillColor: Colors.white.withValues(alpha: 0.06),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                  ),
                  const SizedBox(height: 16),
                  TextField(
                    controller: _descriptionController,
                    maxLines: 3,
                    style: const TextStyle(color: Colors.white),
                    decoration: InputDecoration(
                      labelText: 'Description (optional)',
                      labelStyle: const TextStyle(color: Colors.white70),
                      filled: true,
                      fillColor: Colors.white.withValues(alpha: 0.06),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                    ),
                  ),
                  const SizedBox(height: 24),
                  Text(
                    'Seats: ${_seatCount.round()}',
                    style: theme.textTheme.titleSmall?.copyWith(
                      color: Colors.white,
                    ),
                  ),
                  Slider(
                    value: _seatCount,
                    min: 2,
                    max: 20,
                    divisions: 18,
                    label: '${_seatCount.round()}',
                    onChanged: _busy
                        ? null
                        : (double value) => setState(() => _seatCount = value),
                  ),
                  const Spacer(),
                  FilledButton(
                    onPressed: _busy ? null : () => unawaited(_create()),
                    child: _busy
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Text('Start room'),
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
