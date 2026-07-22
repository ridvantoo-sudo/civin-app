import 'package:civin/features/live/domain/entities/live_room.dart';

final class LiveRoomModel {
  const LiveRoomModel._();

  static LiveCategory categoryFromJson(Map<String, dynamic> json) =>
      LiveCategory(
        id: _integer(json['id']),
        name: _string(json['name'], fallback: 'Live'),
        icon: _nullableString(json['icon']),
      );

  static LiveRtcCredentials credentialsFromJson(Map<String, dynamic> json) =>
      LiveRtcCredentials(
        appId: _string(json['app_id']),
        channel: _string(json['channel']),
        uid: _integer(json['uid']),
        token: _string(json['token']),
        expiresAt: DateTime.tryParse(_string(json['expires_at'])),
      );

  static LiveConnection connectionFromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> roomJson = _map(json['room']);
    final Map<String, dynamic> rtcJson = _map(json['rtc']);
    if (roomJson.isEmpty || rtcJson.isEmpty) {
      throw const FormatException('Invalid live connection response.');
    }
    final LiveRtcCredentials rtc = credentialsFromJson(rtcJson);
    return LiveConnection(
      room: fromJson(roomJson).withCredentials(rtc),
      rtc: rtc,
    );
  }

  static LiveRoom fromJson(Map<String, dynamic> json) {
    final Map<String, dynamic> host = _map(json['host']);
    final Map<String, dynamic> category = _map(json['category']);
    return LiveRoom(
      id: _string(json['id']),
      title: _string(json['title'], fallback: 'Live stream'),
      description: _nullableString(json['description']),
      channelName: _nullableString(
        json['channel_name'] ?? json['channel'] ?? json['agora_channel_name'],
      ),
      token: _nullableString(json['token'] ?? json['rtc_token']),
      rtcUid: _integer(json['rtc_uid'] ?? json['uid'] ?? json['stream_uid']),
      hostName: _string(
        json['host_name'] ?? host['nickname'] ?? host['username'],
        fallback: 'Creator',
      ),
      hostAvatarUrl: _nullableString(
        json['host_avatar_url'] ?? host['avatar_url'],
      ),
      thumbnailUrl: _nullableString(json['thumbnail_url'] ?? json['thumbnail']),
      categoryId: category.isEmpty
          ? _nullableInteger(json['category_id'])
          : _integer(category['id']),
      viewerCount: _integer(json['viewer_count'] ?? json['viewers']),
      isLive: _boolean(json['is_live'] ?? json['status']),
      startedAt: DateTime.tryParse(_string(json['started_at'])),
    );
  }

  static Map<String, dynamic> _map(Object? value) =>
      value is Map<String, dynamic> ? value : const <String, dynamic>{};

  static String _string(Object? value, {String fallback = ''}) {
    final String text = value?.toString().trim() ?? '';
    return text.isEmpty ? fallback : text;
  }

  static String? _nullableString(Object? value) {
    final String text = _string(value);
    return text.isEmpty ? null : text;
  }

  static int _integer(Object? value) => switch (value) {
    final int number => number,
    final num number => number.toInt(),
    final String text => int.tryParse(text) ?? 0,
    _ => 0,
  };

  static int? _nullableInteger(Object? value) {
    if (value == null) return null;
    final int number = _integer(value);
    return number == 0 && value.toString().trim().isEmpty ? null : number;
  }

  static bool _boolean(Object? value) => switch (value) {
    final bool flag => flag,
    final num number => number != 0,
    final String text => text == 'live' || text == 'true' || text == '1',
    _ => false,
  };
}
