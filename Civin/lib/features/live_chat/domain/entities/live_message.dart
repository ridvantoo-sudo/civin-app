enum LiveMessageType { text, join, leave, system, admin }

enum ChatConnectionStatus { connecting, connected, disconnected, error }

final class LiveChatUser {
  const LiveChatUser({
    required this.id,
    required this.username,
    this.nickname,
    this.avatarUrl,
  });

  final String id;
  final String username;
  final String? nickname;
  final String? avatarUrl;

  String get displayName {
    final String? nick = nickname?.trim();
    if (nick != null && nick.isNotEmpty) return nick;
    return username;
  }
}

final class LiveMessage {
  const LiveMessage({
    required this.id,
    required this.roomId,
    required this.message,
    required this.type,
    required this.createdAt,
    this.user,
    this.metadata,
    this.updatedAt,
  });

  final String id;
  final String roomId;
  final String message;
  final LiveMessageType type;
  final LiveChatUser? user;
  final Map<String, dynamic>? metadata;
  final DateTime createdAt;
  final DateTime? updatedAt;

  bool get isJoin => type == LiveMessageType.join;
  bool get isSystem =>
      type == LiveMessageType.system || type == LiveMessageType.admin;
  bool get isLeave => type == LiveMessageType.leave;
}

final class LiveChatState {
  const LiveChatState({
    this.roomId,
    this.connection = ChatConnectionStatus.disconnected,
    this.messages = const <LiveMessage>[],
    this.errorMessage,
    this.isSending = false,
    this.canModerate = false,
  });

  final String? roomId;
  final ChatConnectionStatus connection;
  final List<LiveMessage> messages;
  final String? errorMessage;
  final bool isSending;
  final bool canModerate;

  LiveChatState copyWith({
    String? roomId,
    ChatConnectionStatus? connection,
    List<LiveMessage>? messages,
    String? errorMessage,
    bool? isSending,
    bool? canModerate,
    bool clearError = false,
  }) => LiveChatState(
    roomId: roomId ?? this.roomId,
    connection: connection ?? this.connection,
    messages: messages ?? this.messages,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
    isSending: isSending ?? this.isSending,
    canModerate: canModerate ?? this.canModerate,
  );
}
