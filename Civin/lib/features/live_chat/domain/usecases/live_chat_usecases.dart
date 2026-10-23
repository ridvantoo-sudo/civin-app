import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/live_chat/domain/entities/live_message.dart';
import 'package:civin/features/live_chat/domain/repositories/live_chat_repository.dart';

final class GetLiveChatMessages {
  const GetLiveChatMessages(this._repository);

  final LiveChatRepository _repository;

  Future<RepositoryResult<List<LiveMessage>>> call(
    String roomId, {
    int perPage = 50,
  }) => _repository.getMessages(roomId, perPage: perPage);
}

final class SendLiveChatMessage {
  const SendLiveChatMessage(this._repository);

  final LiveChatRepository _repository;

  Future<RepositoryResult<LiveMessage>> call(
    String roomId, {
    required String message,
    Map<String, dynamic>? metadata,
  }) => _repository.sendMessage(
    roomId,
    message: message,
    metadata: metadata,
  );
}

final class DeleteLiveChatMessage {
  const DeleteLiveChatMessage(this._repository);

  final LiveChatRepository _repository;

  Future<RepositoryResult<void>> call(String roomId, String messageId) =>
      _repository.deleteMessage(roomId, messageId);
}

final class ConnectLiveChat {
  const ConnectLiveChat(this._repository);

  final LiveChatRepository _repository;

  Future<void> call(String roomId, {required bool canModerate}) =>
      _repository.connect(roomId, canModerate: canModerate);
}

final class DisconnectLiveChat {
  const DisconnectLiveChat(this._repository);

  final LiveChatRepository _repository;

  Future<void> call() => _repository.disconnect();
}
