import 'dart:async';

import 'package:civin/features/live_chat/services/web_socket_service.dart';
import 'package:civin/features/pk/data/models/pk_model.dart';
import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<PkRealtimeDataSource> pkRealtimeDataSourceProvider =
    Provider<PkRealtimeDataSource>(
      (Ref ref) =>
          WebSocketPkRealtimeDataSource(ref.watch(webSocketServiceProvider)),
    );

abstract interface class PkRealtimeDataSource {
  Stream<PkRealtimeEvent> get events;

  Future<void> connect(String roomId);
  Future<void> disconnect();
}

/// Listens for PKStarted / PKScoreUpdated / PKFinished on the shared room channel.
///
/// Does not own the socket lifecycle — live chat typically connects first.
final class WebSocketPkRealtimeDataSource implements PkRealtimeDataSource {
  WebSocketPkRealtimeDataSource(this._socket);

  final WebSocketService _socket;

  final StreamController<PkRealtimeEvent> _eventsController =
      StreamController<PkRealtimeEvent>.broadcast();

  StreamSubscription<Map<String, dynamic>>? _eventsSubscription;
  String? _channelName;

  @override
  Stream<PkRealtimeEvent> get events => _eventsController.stream;

  @override
  Future<void> connect(String roomId) async {
    await disconnect();
    _channelName = 'private-live.room.$roomId';
    _eventsSubscription = _socket.events.listen(_onEvent);

    try {
      await _socket.subscribe(_channelName!);
    } on Object {
      // Chat may connect moments later; event stream still receives PK events.
    }
  }

  @override
  Future<void> disconnect() async {
    await _eventsSubscription?.cancel();
    _eventsSubscription = null;
    _channelName = null;
  }

  void _onEvent(Map<String, dynamic> event) {
    final String name = event['event'] as String? ?? '';
    final PkRealtimeEventType? type = switch (name) {
      'pk.started' => PkRealtimeEventType.started,
      'pk.score.updated' => PkRealtimeEventType.scoreUpdated,
      'pk.finished' => PkRealtimeEventType.finished,
      _ => null,
    };
    if (type == null) return;

    final Object? data = event['data'];
    if (data is! Map<String, dynamic>) return;

    try {
      _eventsController.add(PkModel.eventFromJson(type, data));
    } on Object {
      // Ignore malformed PK payloads.
    }
  }
}
