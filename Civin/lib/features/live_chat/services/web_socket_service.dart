import 'dart:async';
import 'dart:convert';

import 'package:civin/core/config/environment.dart';
import 'package:civin/core/constants/storage_keys.dart';
import 'package:civin/core/network/dio_client.dart';
import 'package:civin/core/storage/secure_storage.dart';
import 'package:civin/features/live_chat/domain/entities/live_message.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:web_socket_channel/web_socket_channel.dart';

final Provider<WebSocketService> webSocketServiceProvider =
    Provider<WebSocketService>(
      (Ref ref) => PusherWebSocketService(
        ref.watch(dioClientProvider),
        ref.watch(secureStorageProvider),
      ),
    );

abstract interface class WebSocketService {
  Stream<ChatConnectionStatus> get connectionStates;
  Stream<Map<String, dynamic>> get events;

  Future<void> connect();
  Future<void> subscribe(String channelName);
  Future<void> unsubscribe(String channelName);
  Future<void> disconnect();
}

/// Minimal Pusher-protocol client for Laravel private channels.
final class PusherWebSocketService implements WebSocketService {
  PusherWebSocketService(
    this._dioClient,
    this._secureStorage, {
    WebSocketChannel Function(Uri uri)? channelFactory,
  }) : _channelFactory = channelFactory ?? WebSocketChannel.connect;

  final DioClient _dioClient;
  final SecureStorage _secureStorage;
  final WebSocketChannel Function(Uri uri) _channelFactory;

  final StreamController<ChatConnectionStatus> _connectionController =
      StreamController<ChatConnectionStatus>.broadcast();
  final StreamController<Map<String, dynamic>> _eventController =
      StreamController<Map<String, dynamic>>.broadcast();

  WebSocketChannel? _channel;
  StreamSubscription<dynamic>? _subscription;
  String? _socketId;
  final Set<String> _subscribed = <String>{};

  @override
  Stream<ChatConnectionStatus> get connectionStates =>
      _connectionController.stream;

  @override
  Stream<Map<String, dynamic>> get events => _eventController.stream;

  @override
  Future<void> connect() async {
    if (!Environment.hasBroadcastConfig) {
      _connectionController.add(ChatConnectionStatus.connected);
      return;
    }

    await disconnect(emitDisconnected: false);
    _connectionController.add(ChatConnectionStatus.connecting);

    try {
      final Uri uri = Uri.parse(Environment.broadcastWsUrl);
      final WebSocketChannel channel = _channelFactory(uri);
      _channel = channel;
      _subscription = channel.stream.listen(
        _onFrame,
        onError: (Object error, StackTrace stackTrace) {
          _connectionController.add(ChatConnectionStatus.error);
        },
        onDone: () {
          _connectionController.add(ChatConnectionStatus.disconnected);
        },
        cancelOnError: false,
      );
    } on Object {
      _connectionController.add(ChatConnectionStatus.error);
      rethrow;
    }
  }

  @override
  Future<void> subscribe(String channelName) async {
    if (_subscribed.contains(channelName)) return;

    if (!Environment.hasBroadcastConfig) {
      _subscribed.add(channelName);
      return;
    }

    final String? socketId = _socketId;
    final WebSocketChannel? channel = _channel;
    if (socketId == null || channel == null) {
      throw StateError('WebSocket is not connected.');
    }

    final String? token = await _secureStorage.read(
      StorageKeys.authAccessToken,
    );
    final Response<dynamic> authResponse = await _dioClient.post<dynamic>(
      '/broadcasting/auth',
      data: <String, dynamic>{
        'socket_id': socketId,
        'channel_name': channelName,
      },
      options: Options(
        headers: token == null
            ? null
            : <String, dynamic>{'Authorization': 'Bearer $token'},
      ),
    );

    final Object? body = authResponse.data;
    if (body is! Map<String, dynamic>) {
      throw const FormatException('Invalid broadcasting auth response.');
    }

    channel.sink.add(
      jsonEncode(<String, dynamic>{
        'event': 'pusher:subscribe',
        'data': <String, dynamic>{
          'channel': channelName,
          'auth': body['auth'],
          if (body['channel_data'] != null)
            'channel_data': body['channel_data'],
        },
      }),
    );
    _subscribed.add(channelName);
  }

  @override
  Future<void> unsubscribe(String channelName) async {
    _subscribed.remove(channelName);
    final WebSocketChannel? channel = _channel;
    if (channel == null || !Environment.hasBroadcastConfig) return;
    channel.sink.add(
      jsonEncode(<String, dynamic>{
        'event': 'pusher:unsubscribe',
        'data': <String, dynamic>{'channel': channelName},
      }),
    );
  }

  @override
  Future<void> disconnect({bool emitDisconnected = true}) async {
    await _subscription?.cancel();
    _subscription = null;
    await _channel?.sink.close();
    _channel = null;
    _socketId = null;
    _subscribed.clear();
    if (emitDisconnected) {
      _connectionController.add(ChatConnectionStatus.disconnected);
    }
  }

  void _onFrame(dynamic raw) {
    if (raw is! String) return;
    final Object? decoded = jsonDecode(raw);
    if (decoded is! Map<String, dynamic>) return;

    final String event = decoded['event'] as String? ?? '';
    final Object? data = decoded['data'];
    final Map<String, dynamic> payload = switch (data) {
      final String encoded => _decodeMap(encoded),
      final Map<String, dynamic> map => map,
      _ => <String, dynamic>{},
    };

    if (event == 'pusher:connection_established') {
      _socketId = payload['socket_id'] as String?;
      _connectionController.add(ChatConnectionStatus.connected);
      return;
    }

    if (event.startsWith('pusher:')) return;

    _eventController.add(<String, dynamic>{
      'event': event.startsWith('.') ? event.substring(1) : event,
      'channel': decoded['channel'],
      'data': payload,
    });
  }

  Map<String, dynamic> _decodeMap(String value) {
    try {
      final Object? decoded = jsonDecode(value);
      if (decoded is Map<String, dynamic>) return decoded;
    } on Object {
      return <String, dynamic>{};
    }
    return <String, dynamic>{};
  }
}
