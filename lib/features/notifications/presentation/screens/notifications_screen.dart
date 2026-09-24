import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/notifications/domain/entities/app_notification.dart';
import 'package:civin/features/notifications/presentation/notification_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class NotificationsScreen extends ConsumerStatefulWidget {
  const NotificationsScreen({super.key});

  @override
  ConsumerState<NotificationsScreen> createState() =>
      _NotificationsScreenState();
}

final class _NotificationsScreenState
    extends ConsumerState<NotificationsScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(notificationProvider.notifier).load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final NotificationViewState view = ref.watch(notificationProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: <Widget>[
          TextButton(
            onPressed: view.unreadCount == 0
                ? null
                : () => ref.read(notificationProvider.notifier).markAllRead(),
            child: const Text('Read all'),
          ),
        ],
      ),
      body: view.isLoading && view.items.isEmpty
          ? const AppLoadingWidget(message: 'Loading notifications')
          : view.errorMessage != null && view.items.isEmpty
          ? AppErrorWidget(
              message: view.errorMessage!,
              onRetry: () => ref.read(notificationProvider.notifier).load(),
            )
          : view.items.isEmpty
          ? const Center(child: Text('You are all caught up.'))
          : RefreshIndicator.adaptive(
              onRefresh: () => ref.read(notificationProvider.notifier).load(),
              child: ListView.separated(
                physics: const AlwaysScrollableScrollPhysics(),
                itemCount: view.items.length,
                separatorBuilder: (BuildContext context, int index) =>
                    const Divider(height: 1),
                itemBuilder: (BuildContext context, int index) {
                  final AppNotification item = view.items[index];
                  return ListTile(
                    leading: Icon(
                      item.isUnread
                          ? Icons.notifications_active_rounded
                          : Icons.notifications_none_rounded,
                    ),
                    title: Text(item.title),
                    subtitle: Text(item.body ?? item.type),
                    trailing: item.isUnread
                        ? const Icon(Icons.circle, size: 10)
                        : null,
                    onTap: item.isUnread
                        ? () => ref
                              .read(notificationProvider.notifier)
                              .markRead(item.id)
                        : null,
                  );
                },
              ),
            ),
    );
  }
}
