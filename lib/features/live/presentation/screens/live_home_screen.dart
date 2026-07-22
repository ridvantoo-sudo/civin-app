import 'package:civin/core/router/router.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/presentation/live_providers.dart';
import 'package:civin/features/live/presentation/widgets/live_thumbnail.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class LiveHomeScreen extends ConsumerWidget {
  const LiveHomeScreen({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<List<LiveRoom>> rooms = ref.watch(liveRoomsProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Live'),
        actions: [
          Padding(
            padding: const EdgeInsets.only(right: 12),
            child: FilledButton.icon(
              onPressed: () => context.push(AppRoutes.createLive),
              icon: const Icon(Icons.videocam_rounded),
              label: const Text('Go live'),
            ),
          ),
        ],
      ),
      body: rooms.when(
        loading: () => const Center(child: CircularProgressIndicator()),
        error: (Object error, StackTrace stackTrace) => _LiveError(
          message: error.toString(),
          onRetry: () => ref.read(liveRoomsProvider.notifier).refresh(),
        ),
        data: (List<LiveRoom> items) {
          if (items.isEmpty) {
            return RefreshIndicator(
              onRefresh: ref.read(liveRoomsProvider.notifier).refresh,
              child: ListView(
                physics: const AlwaysScrollableScrollPhysics(),
                children: const [
                  SizedBox(height: 180),
                  Icon(Icons.live_tv_outlined, size: 64),
                  SizedBox(height: 16),
                  Center(child: Text('No one is live right now.')),
                ],
              ),
            );
          }
          return LayoutBuilder(
            builder: (BuildContext context, BoxConstraints constraints) {
              final int columns = constraints.maxWidth >= 1000
                  ? 4
                  : constraints.maxWidth >= 650
                  ? 3
                  : 2;
              return RefreshIndicator(
                onRefresh: ref.read(liveRoomsProvider.notifier).refresh,
                child: GridView.builder(
                  padding: const EdgeInsets.all(12),
                  gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: columns,
                    crossAxisSpacing: 10,
                    mainAxisSpacing: 10,
                    childAspectRatio: .72,
                  ),
                  itemCount: items.length,
                  itemBuilder: (BuildContext context, int index) {
                    final LiveRoom room = items[index];
                    return LiveThumbnail(
                      room: room,
                      onTap: () => context.push(
                        AppRoutes.liveRoomPath(room.id),
                        extra: room,
                      ),
                    );
                  },
                ),
              );
            },
          );
        },
      ),
    );
  }
}

final class _LiveError extends StatelessWidget {
  const _LiveError({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(24),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.wifi_off_rounded, size: 52),
          const SizedBox(height: 12),
          Text(message, textAlign: TextAlign.center),
          const SizedBox(height: 16),
          FilledButton(onPressed: onRetry, child: const Text('Try again')),
        ],
      ),
    ),
  );
}
