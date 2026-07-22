import 'dart:async';

import 'package:civin/features/live_chat/data/models/live_message_model.dart';
import 'package:civin/features/live_chat/domain/entities/live_message.dart';
import 'package:civin/features/live_chat/services/web_socket_service.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<LiveChatRealtimeDataSource> liveChatRealtimeDataSourceProvider =
    Provider<LiveChatRealtimeDataSource>(
      (Ref ref) =>
          WebSocketLiveChatRealtimeDataSource(ref.watch(webSocketServiceProvider)),
    );

abstract interface class LiveChatRealtimeDataSource {
  Stream<ChatConnectionStatus> get connectionStates;
  Stream<LiveMessage> get messages;
  Stream<String> get deletedMessageIds;

  Future<void> connect(String roomId);
  Future<void> disconnect();
}

final class WebSocketLiveChatRealtimeDataSource
    implements LiveChatRealtimeDataSource {
  WebSocketLiveChatRealtimeDataSource(this._socket);

  final WebSocketService _socket;

  final StreamController<LiveMessage> _messagesController =
      StreamController<LiveMessage>.broadcast();
  final StreamController<String> _deletedController =
      StreamController<String>.broadcast();

  StreamSubscription<Map<String, dynamic>>? _eventsSubscription;
  String? _channelName;

  @override
  Stream<ChatConnectionStatus> get connectionStates => _socket.connectionStates;

  @override
  Stream<LiveMessage> get messages => _messagesController.stream;

  @override
  Stream<String> get deletedMessageIds => _deletedController.stream;

  @override
  Future<void> connect(String roomId) async {
    await disconnect(emit: false);
    _channelName = 'private-live.room.$roomId';
    _eventsSubscription = _socket.events.listen(_onEvent);

    final Future<ChatConnectionStatus> ready = _socket.connectionStates
        .firstWhere(
          (ChatConnectionStatus status) =>
              status == ChatConnectionStatus.connected ||
              status == ChatConnectionStatus.error,
        );
    await _socket.connect();
    final ChatConnectionStatus status = await ready.timeout(
      const Duration(seconds: 12),
      onTimeout: () => ChatConnectionStatus.error,
    );
    if (status == ChatConnectionStatus.error) {
      throw StateError('Unable to connect live chat realtime channel.');
    }
    await _socket.subscribe(_channelName!);
  }

  @override
  Future<void> disconnect({bool emit = true}) async {
    final String? channel = _channelName;
    if (channel != null) {
      await _socket.unsubscribe(channel);
    }
    await _eventsSubscription?.cancel();
    _eventsSubscription = null;
    _channelName = null;
    if (emit) {
      await _socket.disconnect();
    }
  }

  void _onEvent(Map<String, dynamic> event) {
    final String name = event['event'] as String? ?? '';
    final Object? data = event['data'];
    if (data is! Map<String, dynamic>) return;

    if (name == 'message.sent') {
      final Object? message = data['message'];
      if (message is Map<String, dynamic>) {
        _messagesController.add(LiveMessageModel.fromJson(message));
      }
      return;
    }

    if (name == 'message.deleted') {
      final String? messageId = data['message_id'] as String?;
      if (messageId != null) {
        _deletedController.add(messageId);
      }
    }
  }
}
