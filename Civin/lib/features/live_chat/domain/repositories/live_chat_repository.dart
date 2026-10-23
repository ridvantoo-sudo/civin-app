import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/live_chat/domain/entities/live_message.dart';

abstract interface class LiveChatRepository {
  Future<RepositoryResult<List<LiveMessage>>> getMessages(
    String roomId, {
    int perPage = 50,
  });

  Future<RepositoryResult<LiveMessage>> sendMessage(
    String roomId, {
    required String message,
    Map<String, dynamic>? metadata,
  });

  Future<RepositoryResult<void>> deleteMessage(String roomId, String messageId);

  Stream<LiveMessage> watchMessages(String roomId);

  Stream<String> watchDeletedMessageIds(String roomId);

  Stream<ChatConnectionStatus> watchConnection(String roomId);

  Future<void> connect(String roomId, {required bool canModerate});

  Future<void> disconnect();
}
