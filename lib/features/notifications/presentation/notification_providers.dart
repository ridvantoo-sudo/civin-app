import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/notifications/data/datasources/notification_remote_data_source.dart';
import 'package:civin/features/notifications/domain/entities/app_notification.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final NotifierProvider<NotificationController, NotificationViewState>
notificationProvider =
    NotifierProvider<NotificationController, NotificationViewState>(
      NotificationController.new,
    );

final class NotificationViewState {
  const NotificationViewState({
    this.items = const <AppNotification>[],
    this.isLoading = false,
    this.errorMessage,
  });

  final List<AppNotification> items;
  final bool isLoading;
  final String? errorMessage;

  int get unreadCount =>
      items.where((AppNotification item) => item.isUnread).length;

  NotificationViewState copyWith({
    List<AppNotification>? items,
    bool? isLoading,
    String? errorMessage,
    bool clearError = false,
  }) => NotificationViewState(
    items: items ?? this.items,
    isLoading: isLoading ?? this.isLoading,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
  );
}

final class NotificationController extends Notifier<NotificationViewState> {
  @override
  NotificationViewState build() => const NotificationViewState();

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    final RepositoryResult<List<AppNotification>> result = await _run(
      () => ref.read(notificationRemoteDataSourceProvider).list(),
    );
    if (!ref.mounted) return;
    result.fold(
      onSuccess: (List<AppNotification> value) {
        state = state.copyWith(
          isLoading: false,
          items: value,
          clearError: true,
        );
      },
      onFailure: (failure) {
        state = state.copyWith(isLoading: false, errorMessage: failure.message);
      },
    );
  }

  Future<void> markRead(String id) async {
    final RepositoryResult<void> result = await _run(
      () => ref.read(notificationRemoteDataSourceProvider).markRead(id),
    );
    if (!ref.mounted) return;
    result.fold(
      onSuccess: (_) {},
      onFailure: (failure) {
        state = state.copyWith(errorMessage: failure.message);
      },
    );
    await load();
  }

  Future<void> markAllRead() async {
    final RepositoryResult<void> result = await _run(
      () => ref.read(notificationRemoteDataSourceProvider).markAllRead(),
    );
    if (!ref.mounted) return;
    result.fold(
      onSuccess: (_) {},
      onFailure: (failure) {
        state = state.copyWith(errorMessage: failure.message);
      },
    );
    await load();
  }

  Future<RepositoryResult<T>> _run<T>(Future<T> Function() request) {
    return const _NotificationRepository().execute(request);
  }
}

final class _NotificationRepository extends BaseRepository {
  const _NotificationRepository();
}
