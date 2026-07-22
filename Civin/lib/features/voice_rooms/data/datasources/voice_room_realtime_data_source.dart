import 'dart:async';

import 'package:civin/features/live_chat/services/web_socket_service.dart';
import 'package:civin/features/voice_rooms/data/models/voice_room_model.dart';
import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<VoiceRoomRealtimeDataSource> voiceRoomRealtimeDataSourceProvider =
    Provider<VoiceRoomRealtimeDataSource>(
      (Ref ref) => WebSocketVoiceRoomRealtimeDataSource(
        ref.watch(webSocketServiceProvider),
      ),
    );

abstract interface class VoiceRoomRealtimeDataSource {
  Stream<VoiceRealtimeEvent> get events;

  Future<void> connect(String roomId);
  Future<void> disconnect();
}

/// Listens for VoiceRoomStarted / SeatUpdated / SpeakerJoined / SpeakerRemoved.
final class WebSocketVoiceRoomRealtimeDataSource
    implements VoiceRoomRealtimeDataSource {
  WebSocketVoiceRoomRealtimeDataSource(this._socket);

  final WebSocketService _socket;

  final StreamController<VoiceRealtimeEvent> _eventsController =
      StreamController<VoiceRealtimeEvent>.broadcast();

  StreamSubscription<Map<String, dynamic>>? _eventsSubscription;
  String? _channelName;

  @override
  Stream<VoiceRealtimeEvent> get events => _eventsController.stream;

  @override
  Future<void> connect(String roomId) async {
    await disconnect();
    _channelName = 'private-voice.room.$roomId';
    _eventsSubscription = _socket.events.listen(_onEvent);

    try {
      await _socket.connect();
      await _socket.subscribe(_channelName!);
    } on Object {
      // Socket may already be up; event stream still receives voice events.
    }
  }

  @override
  Future<void> disconnect() async {
    final String? channel = _channelName;
    if (channel != null) {
      try {
        await _socket.unsubscribe(channel);
      } on Object {
        // Ignore unsubscribe failures during teardown.
      }
    }
    await _eventsSubscription?.cancel();
    _eventsSubscription = null;
    _channelName = null;
  }

  void _onEvent(Map<String, dynamic> event) {
    final String name = event['event'] as String? ?? '';
    final VoiceRealtimeEventType? type = switch (name) {
      'voice.room.started' => VoiceRealtimeEventType.roomStarted,
      'seat.updated' => VoiceRealtimeEventType.seatUpdated,
      'speaker.joined' => VoiceRealtimeEventType.speakerJoined,
      'speaker.removed' => VoiceRealtimeEventType.speakerRemoved,
      _ => null,
    };
    if (type == null) return;

    final Object? data = event['data'];
    if (data is! Map<String, dynamic>) return;

    try {
      _eventsController.add(VoiceRoomModel.eventFromJson(type, data));
    } on Object {
      // Ignore malformed voice payloads.
    }
  }
}
