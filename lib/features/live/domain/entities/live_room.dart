enum LiveRole { host, viewer }

final class LiveCategory {
  const LiveCategory({required this.id, required this.name, this.icon});

  final int id;
  final String name;
  final String? icon;
}

final class LiveRtcCredentials {
  const LiveRtcCredentials({
    required this.appId,
    required this.channel,
    required this.uid,
    required this.token,
    this.expiresAt,
  });

  final String appId;
  final String channel;
  final int uid;
  final String token;
  final DateTime? expiresAt;
}

final class LiveConnection {
  const LiveConnection({required this.room, required this.rtc});

  final LiveRoom room;
  final LiveRtcCredentials rtc;
}

final class LiveRoom {
  const LiveRoom({
    required this.id,
    required this.title,
    required this.hostName,
    required this.viewerCount,
    required this.isLive,
    this.description,
    this.channelName,
    this.token,
    this.rtcUid = 0,
    this.hostAvatarUrl,
    this.thumbnailUrl,
    this.categoryId,
    this.startedAt,
  });

  final String id;
  final String title;
  final String? description;
  final String? channelName;
  final String? token;
  final int rtcUid;
  final String hostName;
  final String? hostAvatarUrl;
  final String? thumbnailUrl;
  final int? categoryId;
  final int viewerCount;
  final bool isLive;
  final DateTime? startedAt;

  LiveRoom copyWith({
    String? channelName,
    String? token,
    int? rtcUid,
    int? viewerCount,
    bool? isLive,
  }) => LiveRoom(
    id: id,
    title: title,
    description: description,
    channelName: channelName ?? this.channelName,
    token: token ?? this.token,
    rtcUid: rtcUid ?? this.rtcUid,
    hostName: hostName,
    hostAvatarUrl: hostAvatarUrl,
    thumbnailUrl: thumbnailUrl,
    categoryId: categoryId,
    viewerCount: viewerCount ?? this.viewerCount,
    isLive: isLive ?? this.isLive,
    startedAt: startedAt,
  );

  LiveRoom withCredentials(LiveRtcCredentials credentials) => copyWith(
    channelName: credentials.channel,
    token: credentials.token,
    rtcUid: credentials.uid,
  );
}
