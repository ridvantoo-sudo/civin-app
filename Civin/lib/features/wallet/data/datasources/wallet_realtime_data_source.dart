import 'dart:async';

import 'package:civin/features/live_chat/domain/entities/live_message.dart';
import 'package:civin/features/live_chat/services/web_socket_service.dart';
import 'package:civin/features/wallet/data/models/wallet_model.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<WalletRealtimeDataSource> walletRealtimeDataSourceProvider =
    Provider<WalletRealtimeDataSource>(
      (Ref ref) => WebSocketWalletRealtimeDataSource(
        ref.watch(webSocketServiceProvider),
      ),
    );

abstract interface class WalletRealtimeDataSource {
  Stream<WalletUpdatedEvent> get walletUpdated;

  Future<void> connect(String userId);
  Future<void> disconnect();
}

/// Listens for `wallet.updated` on `private-user.wallet.{userId}`.
final class WebSocketWalletRealtimeDataSource
    implements WalletRealtimeDataSource {
  WebSocketWalletRealtimeDataSource(this._socket);

  final WebSocketService _socket;

  final StreamController<WalletUpdatedEvent> _updatedController =
      StreamController<WalletUpdatedEvent>.broadcast();

  StreamSubscription<Map<String, dynamic>>? _eventsSubscription;
  String? _channelName;

  @override
  Stream<WalletUpdatedEvent> get walletUpdated => _updatedController.stream;

  @override
  Future<void> connect(String userId) async {
    await disconnect(unsubscribeOnly: true);
    _channelName = 'private-user.wallet.$userId';
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
      throw StateError('Unable to connect wallet realtime channel.');
    }
    await _socket.subscribe(_channelName!);
  }

  @override
  Future<void> disconnect({bool unsubscribeOnly = false}) async {
    final String? channel = _channelName;
    if (channel != null) {
      await _socket.unsubscribe(channel);
    }
    await _eventsSubscription?.cancel();
    _eventsSubscription = null;
    _channelName = null;
    if (!unsubscribeOnly) {
      // Keep shared socket alive for concurrent live features.
    }
  }

  void _onEvent(Map<String, dynamic> event) {
    final String name = event['event'] as String? ?? '';
    if (name != 'wallet.updated') return;

    final Object? data = event['data'];
    if (data is! Map<String, dynamic>) return;

    try {
      _updatedController.add(WalletModel.updatedEventFromJson(data));
    } on Object {
      // Ignore malformed wallet payloads.
    }
  }
}
