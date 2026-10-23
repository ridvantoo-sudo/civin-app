import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';

abstract final class VoiceRoomModel {
  static VoiceRoom roomFromJson(Map<String, dynamic> json) {
    final Object? hostJson = json['host'];
    final Object? seatsJson = json['seats'];

    return VoiceRoom(
      id: json['id']?.toString() ?? '',
      title: json['title'] as String? ?? 'Voice room',
      description: json['description'] as String?,
      thumbnailUrl: json['thumbnail'] as String?,
      status: _roomStatus(json['status'] as String?),
      seatCount: _int(json['seat_count'], fallback: 8),
      participantCount: _int(json['participant_count']),
      host: hostJson is Map<String, dynamic> ? userFromJson(hostJson) : null,
      seats: seatsJson is List<dynamic>
          ? seatsJson
                .whereType<Map<String, dynamic>>()
                .map(seatFromJson)
                .toList(growable: false)
          : const <VoiceSeat>[],
      startedAt: _date(json['started_at']),
      endedAt: _date(json['ended_at']),
    );
  }

  static VoiceSeat seatFromJson(Map<String, dynamic> json) {
    final Object? userJson = json['user'];
    return VoiceSeat(
      id: json['id']?.toString() ?? '',
      seatIndex: _int(json['seat_index']),
      status: _seatStatus(json['status'] as String?),
      isMuted: json['is_muted'] as bool? ?? false,
      user: userJson is Map<String, dynamic> ? userFromJson(userJson) : null,
    );
  }

  static VoiceUser userFromJson(Map<String, dynamic> json) => VoiceUser(
    id: json['id']?.toString() ?? json['user_id']?.toString() ?? '',
    username: json['username'] as String? ?? 'user',
    nickname: json['nickname'] as String?,
    avatarUrl: json['avatar_url'] as String?,
  );

  static VoiceRtcCredentials rtcFromJson(Map<String, dynamic> json) =>
      VoiceRtcCredentials(
        appId: json['app_id'] as String? ?? '',
        channel: json['channel'] as String? ?? '',
        uid: _int(json['uid']),
        token: json['token'] as String? ?? '',
        expiresAt: _date(json['expires_at']),
      );

  static VoiceRoomConnection connectionFromJson(Map<String, dynamic> json) {
    final Object? roomJson = json['room'];
    final Object? rtcJson = json['rtc'];
    if (roomJson is! Map<String, dynamic> || rtcJson is! Map<String, dynamic>) {
      throw const FormatException('Invalid voice room connection payload.');
    }
    return VoiceRoomConnection(
      room: roomFromJson(roomJson),
      rtc: rtcFromJson(rtcJson),
    );
  }

  static VoiceRealtimeEvent eventFromJson(
    VoiceRealtimeEventType type,
    Map<String, dynamic> json,
  ) => VoiceRealtimeEvent(
    type: type,
    roomId: json['room_id']?.toString() ?? '',
    seatIndex: json.containsKey('seat_index')
        ? _int(json['seat_index'])
        : null,
    userId: json['user_id']?.toString(),
    hostId: json['host_id']?.toString(),
    seatCount: json.containsKey('seat_count')
        ? _int(json['seat_count'])
        : null,
    participantCount: json.containsKey('participant_count')
        ? _int(json['participant_count'])
        : null,
    status: json['status'] is String
        ? _seatStatus(json['status'] as String)
        : null,
    isMuted: json['is_muted'] as bool?,
  );

  static VoiceRoomStatus _roomStatus(String? value) => switch (value
      ?.toLowerCase()) {
    'live' => VoiceRoomStatus.live,
    'ended' => VoiceRoomStatus.ended,
    _ => VoiceRoomStatus.unknown,
  };

  static VoiceSeatStatus _seatStatus(String? value) => switch (value
      ?.toLowerCase()) {
    'empty' => VoiceSeatStatus.empty,
    'pending' => VoiceSeatStatus.pending,
    'occupied' => VoiceSeatStatus.occupied,
    _ => VoiceSeatStatus.unknown,
  };

  static int _int(Object? value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }

  static DateTime? _date(Object? value) {
    if (value is! String || value.isEmpty) return null;
    return DateTime.tryParse(value);
  }
}
