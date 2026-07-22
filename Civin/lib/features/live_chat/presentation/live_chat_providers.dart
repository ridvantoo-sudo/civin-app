import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/live_chat/data/repositories/live_chat_repository_impl.dart';
import 'package:civin/features/live_chat/domain/entities/live_message.dart';
import 'package:civin/features/live_chat/domain/repositories/live_chat_repository.dart';
import 'package:civin/features/live_chat/domain/usecases/live_chat_usecases.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<GetLiveChatMessages> getLiveChatMessagesProvider =
    Provider<GetLiveChatMessages>(
      (Ref ref) => GetLiveChatMessages(ref.watch(liveChatRepositoryProvider)),
    );

final Provider<SendLiveChatMessage> sendLiveChatMessageProvider =
    Provider<SendLiveChatMessage>(
      (Ref ref) => SendLiveChatMessage(ref.watch(liveChatRepositoryProvider)),
    );

final Provider<DeleteLiveChatMessage> deleteLiveChatMessageProvider =
    Provider<DeleteLiveChatMessage>(
      (Ref ref) => DeleteLiveChatMessage(ref.watch(liveChatRepositoryProvider)),
    );

final Provider<ConnectLiveChat> connectLiveChatProvider =
    Provider<ConnectLiveChat>(
      (Ref ref) => ConnectLiveChat(ref.watch(liveChatRepositoryProvider)),
    );

final Provider<DisconnectLiveChat> disconnectLiveChatProvider =
    Provider<DisconnectLiveChat>(
      (Ref ref) => DisconnectLiveChat(ref.watch(liveChatRepositoryProvider)),
    );

final chatProvider =
    NotifierProvider.family<ChatController, LiveChatState, String>(
      ChatController.new,
    );

final messagesProvider = Provider.family<List<LiveMessage>, String>((
  Ref ref,
  String roomId,
) {
  final LiveChatState state = ref.watch(chatProvider(roomId));
  return state.messages;
});

final connectionProvider = Provider.family<ChatConnectionStatus, String>((
  Ref ref,
  String roomId,
) {
  final LiveChatState state = ref.watch(chatProvider(roomId));
  return state.connection;
});

final class ChatController extends Notifier<LiveChatState> {
  ChatController(this.roomId);

  final String roomId;

  StreamSubscription<LiveMessage>? _messagesSub;
  StreamSubscription<String>? _deletedSub;
  StreamSubscription<ChatConnectionStatus>? _connectionSub;
  bool _started = false;

  @override
  LiveChatState build() {
    final LiveChatRepository repository = ref.read(liveChatRepositoryProvider);
    ref.onDispose(() {
      unawaited(_messagesSub?.cancel());
      unawaited(_deletedSub?.cancel());
      unawaited(_connectionSub?.cancel());
      unawaited(repository.disconnect());
    });

    return LiveChatState(roomId: roomId);
  }

  Future<void> connect({required bool canModerate}) async {
    _started = true;
    state = state.copyWith(
      connection: ChatConnectionStatus.connecting,
      canModerate: canModerate,
      clearError: true,
    );

    final LiveChatRepository repository = ref.read(liveChatRepositoryProvider);
    await _messagesSub?.cancel();
    await _deletedSub?.cancel();
    await _connectionSub?.cancel();

    _connectionSub = repository.watchConnection(roomId).listen((
      ChatConnectionStatus status,
    ) {
      state = state.copyWith(connection: status);
    });
    _messagesSub = repository.watchMessages(roomId).listen(_upsertMessage);
    _deletedSub = repository
        .watchDeletedMessageIds(roomId)
        .listen(_removeMessage);

    try {
      await ref.read(connectLiveChatProvider)(
        roomId,
        canModerate: canModerate,
      );

      final RepositoryResult<List<LiveMessage>> history = await ref.read(
        getLiveChatMessagesProvider,
      )(roomId);
      history.fold(
        onSuccess: (List<LiveMessage> messages) {
          state = state.copyWith(
            messages: messages,
            connection: ChatConnectionStatus.connected,
            clearError: true,
          );
        },
        onFailure: (failure) {
          state = state.copyWith(
            connection: ChatConnectionStatus.error,
            errorMessage: failure.message,
          );
        },
      );
    } on Object catch (error) {
      state = state.copyWith(
        connection: ChatConnectionStatus.error,
        errorMessage: error.toString(),
      );
    }
  }

  void updateModeration({required bool canModerate}) {
    if (state.canModerate == canModerate && _started) return;
    state = state.copyWith(canModerate: canModerate);
    if (!_started) {
      unawaited(connect(canModerate: canModerate));
    }
  }

  Future<bool> send(String message) async {
    final String trimmed = message.trim();
    if (trimmed.isEmpty || state.isSending) return false;

    state = state.copyWith(isSending: true, clearError: true);
    final RepositoryResult<LiveMessage> result = await ref.read(
      sendLiveChatMessageProvider,
    )(roomId, message: trimmed);
    state = state.copyWith(isSending: false);

    return result.fold(
      onSuccess: (LiveMessage sent) {
        _upsertMessage(sent);
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<bool> delete(LiveMessage message) async {
    if (!state.canModerate) return false;
    final RepositoryResult<void> result = await ref.read(
      deleteLiveChatMessageProvider,
    )(roomId, message.id);
    return result.fold(
      onSuccess: (_) {
        _removeMessage(message.id);
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(errorMessage: failure.message);
        return false;
      },
    );
  }

  void _upsertMessage(LiveMessage message) {
    if (message.roomId != roomId) return;
    final List<LiveMessage> next = List<LiveMessage>.of(state.messages);
    final int index = next.indexWhere(
      (LiveMessage item) => item.id == message.id,
    );
    if (index >= 0) {
      next[index] = message;
    } else {
      next.add(message);
    }
    state = state.copyWith(messages: List<LiveMessage>.unmodifiable(next));
  }

  void _removeMessage(String messageId) {
    state = state.copyWith(
      messages: state.messages
          .where((LiveMessage item) => item.id != messageId)
          .toList(growable: false),
    );
  }
}
