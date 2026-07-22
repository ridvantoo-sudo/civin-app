import 'package:civin/features/authentication/domain/entities/user.dart';
import 'package:civin/features/authentication/presentation/auth_controller.dart';
import 'package:civin/features/gifts/domain/entities/gift.dart';
import 'package:civin/features/gifts/presentation/gift_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

Future<void> showGiftHistorySheet(BuildContext context, String roomId) {
  return showModalBottomSheet<void>(
    context: context,
    backgroundColor: const Color(0xFF12121A),
    showDragHandle: true,
    isScrollControlled: true,
    builder: (BuildContext context) => GiftHistorySheet(roomId: roomId),
  );
}

final class GiftHistorySheet extends ConsumerWidget {
  const GiftHistorySheet({required this.roomId, super.key});

  final String roomId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final GiftRoomState roomState = ref.watch(giftProvider(roomId));
    final User? user = ref.watch(authControllerProvider).session?.user;
    final AsyncValue<List<GiftTransaction>>? remoteHistory = user == null
        ? null
        : ref.watch(giftHistoryProvider(user.id));

    return SafeArea(
      child: SizedBox(
        height: MediaQuery.sizeOf(context).height * 0.55,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 12),
              child: Text(
                'Gift history',
                style: Theme.of(
                  context,
                ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w700),
              ),
            ),
            Expanded(
              child: DefaultTabController(
                length: 2,
                child: Column(
                  children: [
                    const TabBar(
                      tabs: [
                        Tab(text: 'This room'),
                        Tab(text: 'My gifts'),
                      ],
                    ),
                    Expanded(
                      child: TabBarView(
                        children: [
                          _RoomHistoryList(events: roomState.history),
                          if (remoteHistory == null)
                            const Center(
                              child: Text('Sign in to view gift history'),
                            )
                          else
                            remoteHistory.when(
                              loading: () => const Center(
                                child: CircularProgressIndicator(),
                              ),
                              error: (Object error, StackTrace stack) =>
                                  Center(child: Text('$error')),
                              data: (List<GiftTransaction> items) =>
                                  _TransactionHistoryList(items: items),
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final class _RoomHistoryList extends StatelessWidget {
  const _RoomHistoryList({required this.events});

  final List<GiftSentEvent> events;

  @override
  Widget build(BuildContext context) {
    if (events.isEmpty) {
      return const Center(child: Text('No gifts in this room yet'));
    }
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: events.length,
      separatorBuilder: (_, _) => const Divider(height: 1),
      itemBuilder: (BuildContext context, int index) {
        final GiftSentEvent event = events[index];
        return ListTile(
          contentPadding: EdgeInsets.zero,
          leading: _GiftLeading(iconUrl: event.gift.icon),
          title: Text('${event.sender.displayName} → ${event.gift.name}'),
          subtitle: Text('×${event.quantity} · ${event.coins} coins'),
        );
      },
    );
  }
}

final class _TransactionHistoryList extends StatelessWidget {
  const _TransactionHistoryList({required this.items});

  final List<GiftTransaction> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const Center(child: Text('No gift history yet'));
    }
    return ListView.separated(
      padding: const EdgeInsets.all(16),
      itemCount: items.length,
      separatorBuilder: (_, _) => const Divider(height: 1),
      itemBuilder: (BuildContext context, int index) {
        final GiftTransaction item = items[index];
        final String giftName = item.gift?.name ?? 'Gift';
        final String sender = item.sender?.displayName ?? 'Someone';
        return ListTile(
          contentPadding: EdgeInsets.zero,
          leading: _GiftLeading(iconUrl: item.gift?.icon),
          title: Text('$sender → $giftName'),
          subtitle: Text('×${item.quantity} · ${item.coins} coins'),
        );
      },
    );
  }
}

final class _GiftLeading extends StatelessWidget {
  const _GiftLeading({this.iconUrl});

  final String? iconUrl;

  @override
  Widget build(BuildContext context) {
    if (iconUrl == null || iconUrl!.isEmpty) {
      return CircleAvatar(
        backgroundColor: Theme.of(context).colorScheme.primaryContainer,
        child: Icon(
          Icons.card_giftcard_rounded,
          color: Theme.of(context).colorScheme.onPrimaryContainer,
        ),
      );
    }
    return CircleAvatar(
      backgroundColor: Theme.of(context).colorScheme.surfaceContainerHighest,
      backgroundImage: NetworkImage(iconUrl!),
    );
  }
}
