final class AppNotification {
  const AppNotification({
    required this.id,
    required this.type,
    required this.title,
    this.body,
    this.readAt,
    this.createdAt,
  });

  final String id;
  final String type;
  final String title;
  final String? body;
  final DateTime? readAt;
  final DateTime? createdAt;

  bool get isUnread => readAt == null;
}
