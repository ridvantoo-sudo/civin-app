import 'dart:async';

import 'package:civin/features/gifts/data/models/gift_model.dart';
import 'package:civin/features/gifts/domain/entities/gift.dart';
import 'package:civin/features/live_chat/services/web_socket_service.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<GiftRealtimeDataSource> giftRealtimeDataSourceProvider =
    Provider<GiftRealtimeDataSource>(
      (Ref ref) =>
          WebSocketGiftRealtimeDataSource(ref.watch(webSocketServiceProvider)),
    );

abstract interface class GiftRealtimeDataSource {
  Stream<GiftSentEvent> get giftSent;

  Future<void> connect(String roomId);
  Future<void> disconnect();
}

/// Listens for `gift.sent` on the shared live-room websocket channel.
///
/// Does not own the socket lifecycle — live chat typically connects first.
/// This datasource only filters events and ensures channel subscription.
final class WebSocketGiftRealtimeDataSource implements GiftRealtimeDataSource {
  WebSocketGiftRealtimeDataSource(this._socket);

  final WebSocketService _socket;

  final StreamController<GiftSentEvent> _giftSentController =
      StreamController<GiftSentEvent>.broadcast();

  StreamSubscription<Map<String, dynamic>>? _eventsSubscription;
  String? _channelName;

  @override
  Stream<GiftSentEvent> get giftSent => _giftSentController.stream;

  @override
  Future<void> connect(String roomId) async {
    await disconnect();
    _channelName = 'private-live.room.$roomId';
    _eventsSubscription = _socket.events.listen(_onEvent);

    try {
      await _socket.subscribe(_channelName!);
    } on Object {
      // Chat may connect moments later; event stream still receives gift.sent.
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
    if (name != 'gift.sent') return;

    final Object? data = event['data'];
    if (data is! Map<String, dynamic>) return;

    try {
      _giftSentController.add(GiftModel.eventFromJson(data));
    } on Object {
      // Ignore malformed gift payloads.
    }
  }
}
