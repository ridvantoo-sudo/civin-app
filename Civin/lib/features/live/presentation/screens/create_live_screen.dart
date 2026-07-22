import 'dart:async';

import 'package:civin/core/router/router.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/domain/entities/live_session_state.dart';
import 'package:civin/features/live/presentation/live_providers.dart';
import 'package:civin/features/live/presentation/widgets/live_player.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class CreateLiveScreen extends ConsumerStatefulWidget {
  const CreateLiveScreen({super.key});

  @override
  ConsumerState<CreateLiveScreen> createState() => _CreateLiveScreenState();
}

final class _CreateLiveScreenState extends ConsumerState<CreateLiveScreen> {
  final TextEditingController _titleController = TextEditingController();
  int? _categoryId;
  bool _started = false;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(ref.read(liveSessionProvider.notifier).prepareHost());
    });
  }

  @override
  void dispose() {
    _titleController.dispose();
    super.dispose();
  }

  Future<void> _close() async {
    if (_started) {
      if (mounted) context.pop();
      return;
    }
    await ref.read(liveSessionProvider.notifier).cancelPreview();
    if (mounted) context.pop();
  }

  Future<void> _start() async {
    final String title = _titleController.text.trim();
    final int? categoryId = _categoryId;
    if (title.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Add a title before going live.')),
      );
      return;
    }
    if (categoryId == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Choose a category before going live.')),
      );
      return;
    }
    final LiveRoom? room = await ref
        .read(liveSessionProvider.notifier)
        .start(title: title, categoryId: categoryId);
    if (!mounted || room == null) return;
    _started = true;
    context.pushReplacement(AppRoutes.liveRoomPath(room.id), extra: room);
  }

  @override
  Widget build(BuildContext context) {
    final LiveSessionState session = ref.watch(liveSessionProvider);
    final AsyncValue<List<LiveCategory>> categories = ref.watch(
      liveCategoriesProvider,
    );
    final bool busy =
        session.status == LiveConnectionStatus.loading ||
        session.status == LiveConnectionStatus.connecting;

    categories.whenData((List<LiveCategory> items) {
      if (_categoryId == null && items.isNotEmpty) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted && _categoryId == null) {
            setState(() => _categoryId = items.first.id);
          }
        });
      }
    });

    return Scaffold(
      backgroundColor: Colors.black,
      body: Stack(
        fit: StackFit.expand,
        children: [
          AnimatedSwitcher(
            duration: const Duration(milliseconds: 280),
            child: LivePlayer(
              key: ValueKey<bool>(session.previewReady),
              role: LiveRole.host,
              useCameraPreview: true,
              cameraController: ref.watch(liveCameraControllerProvider),
            ),
          ),
          const DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [Colors.black45, Colors.transparent, Colors.black87],
              ),
            ),
          ),
          SafeArea(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                children: [
                  Row(
                    children: [
                      IconButton.filledTonal(
                        tooltip: 'Close',
                        onPressed: busy ? null : _close,
                        icon: const Icon(Icons.close_rounded),
                      ),
                      const SizedBox(width: 12),
                      const Expanded(
                        child: Text(
                          'Create live stream',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 20,
                            fontWeight: FontWeight.w800,
                          ),
                        ),
                      ),
                      IconButton.filledTonal(
                        tooltip: 'Switch camera',
                        onPressed: busy
                            ? null
                            : () => ref
                                  .read(liveSessionProvider.notifier)
                                  .switchCamera(),
                        icon: const Icon(Icons.cameraswitch_rounded),
                      ),
                    ],
                  ),
                  const Spacer(),
                  if (session.status == LiveConnectionStatus.error)
                    Container(
                      width: double.infinity,
                      margin: const EdgeInsets.only(bottom: 12),
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Theme.of(context).colorScheme.errorContainer,
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Text(session.message ?? 'Unable to prepare live.'),
                    ),
                  TextField(
                    controller: _titleController,
                    enabled: !busy,
                    maxLength: 80,
                    style: const TextStyle(color: Colors.white),
                    decoration: const InputDecoration(
                      labelText: 'Live title',
                      hintText: 'What are you streaming?',
                    ),
                  ),
                  const SizedBox(height: 8),
                  categories.when(
                    loading: () => const LinearProgressIndicator(),
                    error: (Object error, StackTrace stackTrace) => Text(
                      error.toString(),
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                    data: (List<LiveCategory> items) {
                      final int? selectedId = items.any(
                        (LiveCategory category) => category.id == _categoryId,
                      )
                          ? _categoryId
                          : null;
                      return DropdownButtonFormField<int>(
                        key: ValueKey<int?>(selectedId),
                        initialValue: selectedId,
                        dropdownColor: const Color(0xFF1B1B1F),
                        decoration: const InputDecoration(
                          labelText: 'Category',
                        ),
                        items: items
                            .map(
                              (LiveCategory category) => DropdownMenuItem<int>(
                                value: category.id,
                                child: Text(category.name),
                              ),
                            )
                            .toList(growable: false),
                        onChanged: busy
                            ? null
                            : (int? value) =>
                                  setState(() => _categoryId = value),
                      );
                    },
                  ),
                  const SizedBox(height: 12),
                  FilledButton.icon(
                    onPressed: busy ? null : _start,
                    icon: busy
                        ? const SizedBox.square(
                            dimension: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.sensors_rounded),
                    label: Text(busy ? 'Preparing…' : 'Start stream'),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
