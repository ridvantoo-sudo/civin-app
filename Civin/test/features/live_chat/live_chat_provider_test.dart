import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/live_chat/data/repositories/live_chat_repository_impl.dart';
import 'package:civin/features/live_chat/domain/entities/live_message.dart';
import 'package:civin/features/live_chat/domain/repositories/live_chat_repository.dart';
import 'package:civin/features/live_chat/presentation/live_chat_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('chatProvider loads history and exposes connection/messages providers', () async {
    final _FakeChatRepository repository = _FakeChatRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [
        liveChatRepositoryProvider.overrideWithValue(repository),
      ],
    );
    addTearDown(container.dispose);

    await container
        .read(chatProvider('room-1').notifier)
        .connect(canModerate: true);

    expect(
      container.read(connectionProvider('room-1')),
      ChatConnectionStatus.connected,
    );
    expect(container.read(messagesProvider('room-1')).single.message, 'Hi');
    expect(repository.connectedRoomId, 'room-1');
  });

  test('send and delete update messagesProvider', () async {
    final _FakeChatRepository repository = _FakeChatRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [
        liveChatRepositoryProvider.overrideWithValue(repository),
      ],
    );
    addTearDown(container.dispose);

    await container
        .read(chatProvider('room-1').notifier)
        .connect(canModerate: true);
    final bool sent = await container
        .read(chatProvider('room-1').notifier)
        .send('New chat');
    expect(sent, isTrue);
    expect(
      container
          .read(messagesProvider('room-1'))
          .map((LiveMessage m) => m.message),
      containsAll(<String>['Hi', 'New chat']),
    );

    final LiveMessage toDelete = container
        .read(messagesProvider('room-1'))
        .first;
    final bool deleted = await container
        .read(chatProvider('room-1').notifier)
        .delete(toDelete);
    expect(deleted, isTrue);
    expect(
      container
          .read(messagesProvider('room-1'))
          .any((LiveMessage m) => m.id == toDelete.id),
      isFalse,
    );
  });

  test('connectionProvider reports error when history fails', () async {
    final _FakeChatRepository repository = _FakeChatRepository()
      ..failHistory = true;
    final ProviderContainer container = ProviderContainer(
      overrides: [
        liveChatRepositoryProvider.overrideWithValue(repository),
      ],
    );
    addTearDown(container.dispose);

    await container
        .read(chatProvider('room-1').notifier)
        .connect(canModerate: false);
    expect(
      container.read(connectionProvider('room-1')),
      ChatConnectionStatus.error,
    );
  });
}

final class _FakeChatRepository implements LiveChatRepository {
  // ignore: close_sinks
  final StreamController<LiveMessage> _messages =
      StreamController<LiveMessage>.broadcast();
  // ignore: close_sinks
  final StreamController<String> _deleted = StreamController<String>.broadcast();
  // ignore: close_sinks
  final StreamController<ChatConnectionStatus> _connection =
      StreamController<ChatConnectionStatus>.broadcast();

  String? connectedRoomId;
  bool failHistory = false;

  static final LiveMessage historyMessage = LiveMessage(
    id: 'msg-1',
    roomId: 'room-1',
    message: 'Hi',
    type: LiveMessageType.text,
    createdAt: DateTime.utc(2026, 7, 22),
    user: const LiveChatUser(id: 'u1', username: 'host'),
  );

  @override
  Future<RepositoryResult<List<LiveMessage>>> getMessages(
    String roomId, {
    int perPage = 50,
  }) async {
    if (failHistory) {
      return const RepositoryFailure<List<LiveMessage>>(
        AppFailure.network(message: 'Unavailable'),
      );
    }
    return RepositorySuccess<List<LiveMessage>>(<LiveMessage>[historyMessage]);
  }

  @override
  Future<RepositoryResult<LiveMessage>> sendMessage(
    String roomId, {
    required String message,
    Map<String, dynamic>? metadata,
  }) async => RepositorySuccess<LiveMessage>(
    LiveMessage(
      id: 'msg-${message.hashCode}',
      roomId: roomId,
      message: message,
      type: LiveMessageType.text,
      createdAt: DateTime.utc(2026, 7, 22, 1),
    ),
  );

  @override
  Future<RepositoryResult<void>> deleteMessage(
    String roomId,
    String messageId,
  ) async => const RepositorySuccess<void>(null);

  @override
  Stream<LiveMessage> watchMessages(String roomId) => _messages.stream;

  @override
  Stream<String> watchDeletedMessageIds(String roomId) => _deleted.stream;

  @override
  Stream<ChatConnectionStatus> watchConnection(String roomId) =>
      _connection.stream;

  @override
  Future<void> connect(String roomId, {required bool canModerate}) async {
    connectedRoomId = roomId;
    _connection.add(ChatConnectionStatus.connected);
  }

  @override
  Future<void> disconnect() async {
    connectedRoomId = null;
    _connection.add(ChatConnectionStatus.disconnected);
  }
}
