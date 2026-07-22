import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/live_chat/data/datasources/live_chat_realtime_data_source.dart';
import 'package:civin/features/live_chat/data/datasources/live_chat_remote_data_source.dart';
import 'package:civin/features/live_chat/data/repositories/live_chat_repository_impl.dart';
import 'package:civin/features/live_chat/domain/entities/live_message.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late _FakeRemote remote;
  late _FakeRealtime realtime;
  late LiveChatRepositoryImpl repository;

  setUp(() {
    remote = _FakeRemote();
    realtime = _FakeRealtime();
    repository = LiveChatRepositoryImpl(remote, realtime);
  });

  test('loads and sends messages through remote datasource', () async {
    final RepositoryResult<List<LiveMessage>> history = await repository
        .getMessages('room-1');
    final RepositoryResult<LiveMessage> sent = await repository.sendMessage(
      'room-1',
      message: 'Hello',
    );

    expect(history, isA<RepositorySuccess<List<LiveMessage>>>());
    expect(
      (history as RepositorySuccess<List<LiveMessage>>).data.single.message,
      'Welcome',
    );
    expect(sent, isA<RepositorySuccess<LiveMessage>>());
    expect(remote.sentMessage, 'Hello');
  });

  test('deletes messages and maps remote failures', () async {
    await repository.deleteMessage('room-1', 'msg-1');
    expect(remote.deletedMessageId, 'msg-1');

    remote.error = DioException(
      requestOptions: RequestOptions(path: '/api/v1/live/room-1/messages'),
      response: Response<dynamic>(
        requestOptions: RequestOptions(path: '/api/v1/live/room-1/messages'),
        statusCode: 422,
        data: <String, dynamic>{'message': 'Slow mode'},
      ),
    );

    final RepositoryResult<LiveMessage> failed = await repository.sendMessage(
      'room-1',
      message: 'Nope',
    );
    expect(failed, isA<RepositoryFailure<LiveMessage>>());
    expect(
      (failed as RepositoryFailure<LiveMessage>).failure,
      isA<NetworkFailure>(),
    );
    expect(failed.failure.message, 'Slow mode');
  });

  test('connects realtime channel and exposes streams', () async {
    await repository.connect('room-1', canModerate: true);
    expect(realtime.connectedRoomId, 'room-1');

    final Future<void> expectation = expectLater(
      repository.watchMessages('room-1'),
      emits(
        isA<LiveMessage>().having(
          (LiveMessage m) => m.message,
          'message',
          'Realtime',
        ),
      ),
    );
    realtime.emitMessage(
      LiveMessage(
        id: 'm2',
        roomId: 'room-1',
        message: 'Realtime',
        type: LiveMessageType.text,
        createdAt: DateTime.utc(2026, 7, 22, 12),
      ),
    );
    await expectation;
  });
}

final class _FakeRemote implements LiveChatRemoteDataSource {
  Object? error;
  String? sentMessage;
  String? deletedMessageId;

  static final LiveMessage sample = LiveMessage(
    id: 'msg-1',
    roomId: 'room-1',
    message: 'Welcome',
    type: LiveMessageType.text,
    createdAt: DateTime.utc(2026, 7, 22),
    user: const LiveChatUser(id: 'u1', username: 'host'),
  );

  @override
  Future<List<LiveMessage>> getMessages(String roomId, {int perPage = 50}) async {
    _throwIfNeeded();
    return <LiveMessage>[sample];
  }

  @override
  Future<LiveMessage> sendMessage(
    String roomId, {
    required String message,
    Map<String, dynamic>? metadata,
  }) async {
    _throwIfNeeded();
    sentMessage = message;
    return LiveMessage(
      id: 'msg-new',
      roomId: roomId,
      message: message,
      type: LiveMessageType.text,
      createdAt: DateTime.utc(2026, 7, 22),
    );
  }

  @override
  Future<void> deleteMessage(String roomId, String messageId) async {
    _throwIfNeeded();
    deletedMessageId = messageId;
  }

  void _throwIfNeeded() {
    final Object? current = error;
    if (current != null) throw current;
  }
}

final class _FakeRealtime implements LiveChatRealtimeDataSource {
  // ignore: close_sinks
  final StreamController<LiveMessage> _messages =
      StreamController<LiveMessage>.broadcast();
  // ignore: close_sinks
  final StreamController<String> _deleted = StreamController<String>.broadcast();
  // ignore: close_sinks
  final StreamController<ChatConnectionStatus> _connection =
      StreamController<ChatConnectionStatus>.broadcast();

  String? connectedRoomId;

  @override
  Stream<ChatConnectionStatus> get connectionStates => _connection.stream;

  @override
  Stream<LiveMessage> get messages => _messages.stream;

  @override
  Stream<String> get deletedMessageIds => _deleted.stream;

  @override
  Future<void> connect(String roomId) async {
    connectedRoomId = roomId;
    _connection.add(ChatConnectionStatus.connected);
  }

  @override
  Future<void> disconnect({bool emit = true}) async {
    connectedRoomId = null;
    if (emit) _connection.add(ChatConnectionStatus.disconnected);
  }

  void emitMessage(LiveMessage message) => _messages.add(message);
}
