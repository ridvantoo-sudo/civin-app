import 'package:civin/features/live_chat/domain/entities/live_message.dart';

abstract final class LiveMessageModel {
  static LiveMessage fromJson(Map<String, dynamic> json) {
    final Object? userJson = json['user'];
    return LiveMessage(
      id: json['id'] as String,
      roomId: json['room_id'] as String,
      message: json['message'] as String? ?? '',
      type: _typeFrom(json['type'] as String?),
      user: userJson is Map<String, dynamic> ? _userFrom(userJson) : null,
      metadata: json['metadata'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(json['metadata'] as Map<String, dynamic>)
          : null,
      createdAt: DateTime.tryParse(json['created_at'] as String? ?? '') ??
          DateTime.now().toUtc(),
      updatedAt: DateTime.tryParse(json['updated_at'] as String? ?? ''),
    );
  }

  static LiveChatUser _userFrom(Map<String, dynamic> json) => LiveChatUser(
    id: json['id'] as String,
    username: json['username'] as String? ?? 'user',
    nickname: json['nickname'] as String?,
    avatarUrl: json['avatar_url'] as String?,
  );

  static LiveMessageType _typeFrom(String? value) => switch (value) {
    'JOIN' => LiveMessageType.join,
    'LEAVE' => LiveMessageType.leave,
    'SYSTEM' => LiveMessageType.system,
    'ADMIN' => LiveMessageType.admin,
    _ => LiveMessageType.text,
  };
}
