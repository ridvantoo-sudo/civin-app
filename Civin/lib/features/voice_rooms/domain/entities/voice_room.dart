enum VoiceRoomStatus { live, ended, unknown }

enum VoiceSeatStatus { empty, pending, occupied, unknown }

enum VoiceRole { host, speaker, audience }

enum VoiceRealtimeEventType {
  roomStarted,
  seatUpdated,
  speakerJoined,
  speakerRemoved,
}

enum VoiceConnectionStatus {
  disconnected,
  connecting,
  connected,
  error,
}

final class VoiceUser {
  const VoiceUser({
    required this.id,
    required this.username,
    this.nickname,
    this.avatarUrl,
  });

  final String id;
  final String username;
  final String? nickname;
  final String? avatarUrl;

  String get displayName {
    final String? nick = nickname?.trim();
    if (nick != null && nick.isNotEmpty) return nick;
    return username;
  }
}

final class VoiceSeat {
  const VoiceSeat({
    required this.id,
    required this.seatIndex,
    required this.status,
    this.isMuted = false,
    this.user,
  });

  final String id;
  final int seatIndex;
  final VoiceSeatStatus status;
  final bool isMuted;
  final VoiceUser? user;

  bool get isEmpty => status == VoiceSeatStatus.empty;
  bool get isPending => status == VoiceSeatStatus.pending;
  bool get isOccupied => status == VoiceSeatStatus.occupied;
  bool get isHostSeat => seatIndex == 0;

  VoiceSeat copyWith({
    VoiceSeatStatus? status,
    bool? isMuted,
    VoiceUser? user,
    bool clearUser = false,
  }) => VoiceSeat(
    id: id,
    seatIndex: seatIndex,
    status: status ?? this.status,
    isMuted: isMuted ?? this.isMuted,
    user: clearUser ? null : user ?? this.user,
  );
}

final class VoiceRtcCredentials {
  const VoiceRtcCredentials({
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

final class VoiceRoomConnection {
  const VoiceRoomConnection({required this.room, required this.rtc});

  final VoiceRoom room;
  final VoiceRtcCredentials rtc;
}

final class VoiceRoom {
  const VoiceRoom({
    required this.id,
    required this.title,
    required this.status,
    required this.seatCount,
    required this.participantCount,
    this.description,
    this.thumbnailUrl,
    this.host,
    this.seats = const <VoiceSeat>[],
    this.startedAt,
    this.endedAt,
  });

  final String id;
  final String title;
  final String? description;
  final String? thumbnailUrl;
  final VoiceRoomStatus status;
  final int seatCount;
  final int participantCount;
  final VoiceUser? host;
  final List<VoiceSeat> seats;
  final DateTime? startedAt;
  final DateTime? endedAt;

  bool get isLive => status == VoiceRoomStatus.live;
  bool get isEnded => status == VoiceRoomStatus.ended;

  String get hostName => host?.displayName ?? 'Host';

  VoiceSeat? seatAt(int index) {
    for (final VoiceSeat seat in seats) {
      if (seat.seatIndex == index) return seat;
    }
    return null;
  }

  VoiceSeat? seatForUser(String userId) {
    for (final VoiceSeat seat in seats) {
      if (seat.user?.id == userId) return seat;
    }
    return null;
  }

  bool isHost(String userId) => host?.id == userId;

  VoiceRoom copyWith({
    VoiceRoomStatus? status,
    int? participantCount,
    List<VoiceSeat>? seats,
    VoiceUser? host,
    DateTime? endedAt,
  }) => VoiceRoom(
    id: id,
    title: title,
    description: description,
    thumbnailUrl: thumbnailUrl,
    status: status ?? this.status,
    seatCount: seatCount,
    participantCount: participantCount ?? this.participantCount,
    host: host ?? this.host,
    seats: seats ?? this.seats,
    startedAt: startedAt,
    endedAt: endedAt ?? this.endedAt,
  );

  VoiceRoom withSeat(VoiceSeat updated) {
    final List<VoiceSeat> next = seats
        .map(
          (VoiceSeat seat) =>
              seat.seatIndex == updated.seatIndex ? updated : seat,
        )
        .toList(growable: false);
    final bool found = next.any(
      (VoiceSeat seat) => seat.seatIndex == updated.seatIndex,
    );
    return copyWith(
      seats: found
          ? next
          : <VoiceSeat>[...seats, updated]
              ..sort(
                (VoiceSeat a, VoiceSeat b) => a.seatIndex.compareTo(b.seatIndex),
              ),
    );
  }
}

/// Realtime envelope for VoiceRoomStarted / SeatUpdated / SpeakerJoined / SpeakerRemoved.
final class VoiceRealtimeEvent {
  const VoiceRealtimeEvent({
    required this.type,
    required this.roomId,
    this.seatIndex,
    this.userId,
    this.hostId,
    this.seatCount,
    this.participantCount,
    this.status,
    this.isMuted,
  });

  final VoiceRealtimeEventType type;
  final String roomId;
  final int? seatIndex;
  final String? userId;
  final String? hostId;
  final int? seatCount;
  final int? participantCount;
  final VoiceSeatStatus? status;
  final bool? isMuted;
}

final class VoiceChatMessage {
  const VoiceChatMessage({
    required this.id,
    required this.userId,
    required this.userName,
    required this.text,
    required this.createdAt,
  });

  final String id;
  final String userId;
  final String userName;
  final String text;
  final DateTime createdAt;
}

final class VoiceRoomSessionState {
  const VoiceRoomSessionState({
    this.room,
    this.role = VoiceRole.audience,
    this.currentUserId,
    this.isBusy = false,
    this.isListening = false,
    this.errorMessage,
    this.chatMessages = const <VoiceChatMessage>[],
  });

  final VoiceRoom? room;
  final VoiceRole role;
  final String? currentUserId;
  final bool isBusy;
  final bool isListening;
  final String? errorMessage;
  final List<VoiceChatMessage> chatMessages;

  bool get isHost => role == VoiceRole.host;
  bool get isSpeaker => role == VoiceRole.speaker || role == VoiceRole.host;
  bool get hasRoom => room != null;

  VoiceRoomSessionState copyWith({
    VoiceRoom? room,
    VoiceRole? role,
    String? currentUserId,
    bool? isBusy,
    bool? isListening,
    String? errorMessage,
    List<VoiceChatMessage>? chatMessages,
    bool clearRoom = false,
    bool clearError = false,
  }) => VoiceRoomSessionState(
    room: clearRoom ? null : room ?? this.room,
    role: role ?? this.role,
    currentUserId: currentUserId ?? this.currentUserId,
    isBusy: isBusy ?? this.isBusy,
    isListening: isListening ?? this.isListening,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
    chatMessages: chatMessages ?? this.chatMessages,
  );
}

final class SeatViewState {
  const SeatViewState({
    this.seats = const <VoiceSeat>[],
    this.seatCount = 0,
    this.pendingRequests = 0,
  });

  final List<VoiceSeat> seats;
  final int seatCount;
  final int pendingRequests;

  bool get hasPending => pendingRequests > 0;
}

final class VoiceConnectionState {
  const VoiceConnectionState({
    this.status = VoiceConnectionStatus.disconnected,
    this.isMicMuted = false,
    this.channel,
    this.uid = 0,
    this.errorMessage,
  });

  final VoiceConnectionStatus status;
  final bool isMicMuted;
  final String? channel;
  final int uid;
  final String? errorMessage;

  bool get isConnected => status == VoiceConnectionStatus.connected;

  VoiceConnectionState copyWith({
    VoiceConnectionStatus? status,
    bool? isMicMuted,
    String? channel,
    int? uid,
    String? errorMessage,
    bool clearError = false,
  }) => VoiceConnectionState(
    status: status ?? this.status,
    isMicMuted: isMicMuted ?? this.isMicMuted,
    channel: channel ?? this.channel,
    uid: uid ?? this.uid,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
  );
}
