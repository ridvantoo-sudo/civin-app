import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/authentication/domain/entities/user.dart';
import 'package:civin/features/authentication/repository/auth_repository_impl.dart';
import 'package:civin/features/voice_rooms/data/datasources/voice_rtc_data_source.dart';
import 'package:civin/features/voice_rooms/data/repositories/voice_room_repository_impl.dart';
import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:civin/features/voice_rooms/domain/repositories/voice_room_repository.dart';
import 'package:civin/features/voice_rooms/domain/usecases/voice_room_usecases.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<CreateVoiceRoom> createVoiceRoomUseCaseProvider =
    Provider<CreateVoiceRoom>(
      (Ref ref) => CreateVoiceRoom(ref.watch(voiceRoomRepositoryProvider)),
    );

final Provider<JoinVoiceRoom> joinVoiceRoomUseCaseProvider =
    Provider<JoinVoiceRoom>(
      (Ref ref) => JoinVoiceRoom(ref.watch(voiceRoomRepositoryProvider)),
    );

final Provider<LeaveVoiceRoom> leaveVoiceRoomUseCaseProvider =
    Provider<LeaveVoiceRoom>(
      (Ref ref) => LeaveVoiceRoom(ref.watch(voiceRoomRepositoryProvider)),
    );

final Provider<RequestVoiceSeat> requestVoiceSeatProvider =
    Provider<RequestVoiceSeat>(
      (Ref ref) => RequestVoiceSeat(ref.watch(voiceRoomRepositoryProvider)),
    );

final Provider<ApproveVoiceSeat> approveVoiceSeatProvider =
    Provider<ApproveVoiceSeat>(
      (Ref ref) => ApproveVoiceSeat(ref.watch(voiceRoomRepositoryProvider)),
    );

final Provider<RejectVoiceSeat> rejectVoiceSeatProvider =
    Provider<RejectVoiceSeat>(
      (Ref ref) => RejectVoiceSeat(ref.watch(voiceRoomRepositoryProvider)),
    );

final Provider<RemoveVoiceSpeaker> removeVoiceSpeakerProvider =
    Provider<RemoveVoiceSpeaker>(
      (Ref ref) => RemoveVoiceSpeaker(ref.watch(voiceRoomRepositoryProvider)),
    );

final Provider<MuteVoiceSpeaker> muteVoiceSpeakerProvider =
    Provider<MuteVoiceSpeaker>(
      (Ref ref) => MuteVoiceSpeaker(ref.watch(voiceRoomRepositoryProvider)),
    );

final Provider<EndVoiceRoom> endVoiceRoomUseCaseProvider =
    Provider<EndVoiceRoom>(
      (Ref ref) => EndVoiceRoom(ref.watch(voiceRoomRepositoryProvider)),
    );

final Provider<ConnectVoiceRealtime> connectVoiceRealtimeProvider =
    Provider<ConnectVoiceRealtime>(
      (Ref ref) =>
          ConnectVoiceRealtime(ref.watch(voiceRoomRepositoryProvider)),
    );

final Provider<DisconnectVoiceRealtime> disconnectVoiceRealtimeProvider =
    Provider<DisconnectVoiceRealtime>(
      (Ref ref) =>
          DisconnectVoiceRealtime(ref.watch(voiceRoomRepositoryProvider)),
    );

/// Active voice room session + owner/audience actions + realtime lifecycle.
final voiceRoomProvider =
    NotifierProvider.family<VoiceRoomController, VoiceRoomSessionState, String>(
      VoiceRoomController.new,
    );

/// Mic seats derived from [voiceRoomProvider] with host management helpers.
final seatProvider = Provider.family<SeatViewState, String>((
  Ref ref,
  String roomId,
) {
  final VoiceRoom? room = ref.watch(voiceRoomProvider(roomId)).room;
  if (room == null) return const SeatViewState();
  final int pending = room.seats
      .where((VoiceSeat seat) => seat.isPending)
      .length;
  return SeatViewState(
    seats: room.seats,
    seatCount: room.seatCount,
    pendingRequests: pending,
  );
});

/// Agora voice connection state for the active room.
final voiceConnectionProvider =
    NotifierProvider.family<
      VoiceConnectionController,
      VoiceConnectionState,
      String
    >(VoiceConnectionController.new);

final class VoiceRoomController extends Notifier<VoiceRoomSessionState> {
  VoiceRoomController(this.roomId);

  final String roomId;

  StreamSubscription<VoiceRealtimeEvent>? _eventsSub;
  bool _started = false;

  @override
  VoiceRoomSessionState build() {
    final VoiceRoomRepository repository = ref.read(voiceRoomRepositoryProvider);
    ref.onDispose(() {
      unawaited(_eventsSub?.cancel());
      unawaited(repository.disconnectRealtime());
    });
    return const VoiceRoomSessionState();
  }

  Future<void> startListening() async {
    if (_started) return;
    _started = true;

    final VoiceRoomRepository repository = ref.read(voiceRoomRepositoryProvider);
    await _eventsSub?.cancel();
    _eventsSub = repository.watchEvents(roomId).listen(_onRealtimeEvent);

    try {
      await ref.read(connectVoiceRealtimeProvider)(roomId);
      state = state.copyWith(isListening: true, clearError: true);
    } on Object catch (error) {
      state = state.copyWith(
        isListening: false,
        errorMessage: error.toString(),
      );
    }
  }

  Future<VoiceRoom?> createRoom({
    required String title,
    String? description,
    String? thumbnail,
    int seatCount = 8,
  }) async {
    if (state.isBusy) return null;
    state = state.copyWith(isBusy: true, clearError: true);

    final RepositoryResult<VoiceRoomConnection> result = await ref.read(
      createVoiceRoomUseCaseProvider,
    )(
      title: title,
      description: description,
      thumbnail: thumbnail,
      seatCount: seatCount,
    );

    return result.fold(
      onSuccess: (VoiceRoomConnection connection) {
        final String userId = _currentUserId() ?? connection.room.host?.id ?? '';
        state = state.copyWith(
          isBusy: false,
          room: connection.room,
          role: VoiceRole.host,
          currentUserId: userId,
          clearError: true,
        );
        unawaited(
          ref
              .read(voiceConnectionProvider(roomId).notifier)
              .connect(connection.rtc, asSpeaker: true),
        );
        unawaited(startListening());
        return connection.room;
      },
      onFailure: (failure) {
        state = state.copyWith(isBusy: false, errorMessage: failure.message);
        return null;
      },
    );
  }

  Future<bool> join({String? asUserId}) async {
    if (state.isBusy) return false;
    state = state.copyWith(isBusy: true, clearError: true);

    final RepositoryResult<VoiceRoomConnection> result = await ref.read(
      joinVoiceRoomUseCaseProvider,
    )(roomId);

    return result.fold(
      onSuccess: (VoiceRoomConnection connection) {
        final String userId =
            asUserId ?? _currentUserId() ?? connection.room.host?.id ?? '';
        final VoiceRole role = _roleFor(connection.room, userId);
        state = state.copyWith(
          isBusy: false,
          room: connection.room,
          role: role,
          currentUserId: userId,
          clearError: true,
        );
        unawaited(
          ref
              .read(voiceConnectionProvider(roomId).notifier)
              .connect(connection.rtc, asSpeaker: role != VoiceRole.audience),
        );
        unawaited(startListening());
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isBusy: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<bool> leave() async {
    if (state.isBusy || state.room == null) return false;
    if (state.isHost) {
      return endRoom();
    }

    state = state.copyWith(isBusy: true, clearError: true);
    final RepositoryResult<VoiceRoom> result = await ref.read(
      leaveVoiceRoomUseCaseProvider,
    )(roomId);

    return result.fold(
      onSuccess: (VoiceRoom room) {
        unawaited(
          ref.read(voiceConnectionProvider(roomId).notifier).disconnect(),
        );
        state = state.copyWith(
          isBusy: false,
          room: room,
          role: VoiceRole.audience,
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isBusy: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<bool> requestMic(int seatIndex) async {
    if (state.isBusy || state.isHost) return false;
    state = state.copyWith(isBusy: true, clearError: true);

    final RepositoryResult<VoiceRoom> result = await ref.read(
      requestVoiceSeatProvider,
    )(roomId, seatIndex: seatIndex);

    return result.fold(
      onSuccess: (VoiceRoom room) {
        state = state.copyWith(isBusy: false, room: room, clearError: true);
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isBusy: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<bool> approveSeat(int seatIndex) async {
    if (!state.isHost || state.isBusy) return false;
    state = state.copyWith(isBusy: true, clearError: true);

    final RepositoryResult<VoiceRoom> result = await ref.read(
      approveVoiceSeatProvider,
    )(roomId, seatIndex: seatIndex);

    return result.fold(
      onSuccess: (VoiceRoom room) {
        state = state.copyWith(isBusy: false, room: room, clearError: true);
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isBusy: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<bool> rejectSeat(int seatIndex) async {
    if (!state.isHost || state.isBusy) return false;
    state = state.copyWith(isBusy: true, clearError: true);

    final RepositoryResult<VoiceRoom> result = await ref.read(
      rejectVoiceSeatProvider,
    )(roomId, seatIndex: seatIndex);

    return result.fold(
      onSuccess: (VoiceRoom room) {
        state = state.copyWith(isBusy: false, room: room, clearError: true);
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isBusy: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<bool> removeSpeaker(int seatIndex) async {
    if (!state.isHost || state.isBusy) return false;
    state = state.copyWith(isBusy: true, clearError: true);

    final RepositoryResult<VoiceRoom> result = await ref.read(
      removeVoiceSpeakerProvider,
    )(roomId, seatIndex: seatIndex);

    return result.fold(
      onSuccess: (VoiceRoom room) {
        state = state.copyWith(isBusy: false, room: room, clearError: true);
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isBusy: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<bool> muteSpeaker(int seatIndex, {bool muted = true}) async {
    if (!state.isHost || state.isBusy) return false;
    state = state.copyWith(isBusy: true, clearError: true);

    final RepositoryResult<VoiceRoom> result = await ref.read(
      muteVoiceSpeakerProvider,
    )(roomId, seatIndex: seatIndex, muted: muted);

    return result.fold(
      onSuccess: (VoiceRoom room) {
        state = state.copyWith(isBusy: false, room: room, clearError: true);
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isBusy: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<bool> endRoom() async {
    if (!state.isHost || state.isBusy) return false;
    state = state.copyWith(isBusy: true, clearError: true);

    final RepositoryResult<VoiceRoom> result = await ref.read(
      endVoiceRoomUseCaseProvider,
    )(roomId);

    return result.fold(
      onSuccess: (VoiceRoom room) {
        unawaited(
          ref.read(voiceConnectionProvider(roomId).notifier).disconnect(),
        );
        state = state.copyWith(isBusy: false, room: room, clearError: true);
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isBusy: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  void sendChatMessage(String text) {
    final String trimmed = text.trim();
    if (trimmed.isEmpty) return;
    final String userId = state.currentUserId ?? 'me';
    final String userName = state.isHost
        ? (state.room?.hostName ?? 'Host')
        : 'You';
    final VoiceChatMessage message = VoiceChatMessage(
      id: '${DateTime.now().microsecondsSinceEpoch}',
      userId: userId,
      userName: userName,
      text: trimmed,
      createdAt: DateTime.now(),
    );
    state = state.copyWith(
      chatMessages: <VoiceChatMessage>[...state.chatMessages, message],
    );
  }

  void seedRoom(VoiceRoom room, {required VoiceRole role, String? userId}) {
    state = state.copyWith(
      room: room,
      role: role,
      currentUserId: userId ?? state.currentUserId ?? room.host?.id,
      clearError: true,
    );
  }

  VoiceRole _roleFor(VoiceRoom room, String userId) {
    if (room.isHost(userId)) return VoiceRole.host;
    final VoiceSeat? seat = room.seatForUser(userId);
    if (seat != null && seat.isOccupied) return VoiceRole.speaker;
    return VoiceRole.audience;
  }

  String? _currentUserId() {
    final User? user = ref.read(authRepositoryProvider).currentUser;
    return user?.id;
  }

  void _onRealtimeEvent(VoiceRealtimeEvent event) {
    if (event.roomId != roomId) return;
    final VoiceRoom? current = state.room;

    switch (event.type) {
      case VoiceRealtimeEventType.roomStarted:
        if (current == null) {
          state = state.copyWith(
            room: VoiceRoom(
              id: event.roomId,
              title: 'Voice room',
              status: VoiceRoomStatus.live,
              seatCount: event.seatCount ?? 8,
              participantCount: event.participantCount ?? 1,
              host: event.hostId == null
                  ? null
                  : VoiceUser(id: event.hostId!, username: 'host'),
            ),
            clearError: true,
          );
          return;
        }
        state = state.copyWith(
          room: current.copyWith(
            status: VoiceRoomStatus.live,
            participantCount:
                event.participantCount ?? current.participantCount,
          ),
          clearError: true,
        );
      case VoiceRealtimeEventType.seatUpdated:
        if (current == null || event.seatIndex == null) return;
        final VoiceSeat existing =
            current.seatAt(event.seatIndex!) ??
            VoiceSeat(
              id: 'seat-${event.seatIndex}',
              seatIndex: event.seatIndex!,
              status: VoiceSeatStatus.empty,
            );
        final VoiceSeat updated = existing.copyWith(
          status: event.status ?? existing.status,
          isMuted: event.isMuted ?? existing.isMuted,
          user: event.userId == null
              ? null
              : VoiceUser(
                  id: event.userId!,
                  username: existing.user?.username ?? 'user',
                  nickname: existing.user?.nickname,
                  avatarUrl: existing.user?.avatarUrl,
                ),
          clearUser: event.userId == null,
        );
        final VoiceRoom nextRoom = current.withSeat(updated);
        final String? me = state.currentUserId;
        state = state.copyWith(
          room: nextRoom,
          role: me == null ? state.role : _roleFor(nextRoom, me),
          clearError: true,
        );
      case VoiceRealtimeEventType.speakerJoined:
        if (current == null || event.seatIndex == null) return;
        final VoiceSeat existing =
            current.seatAt(event.seatIndex!) ??
            VoiceSeat(
              id: 'seat-${event.seatIndex}',
              seatIndex: event.seatIndex!,
              status: VoiceSeatStatus.empty,
            );
        final VoiceSeat updated = existing.copyWith(
          status: VoiceSeatStatus.occupied,
          user: event.userId == null
              ? existing.user
              : VoiceUser(
                  id: event.userId!,
                  username: existing.user?.username ?? 'speaker',
                  nickname: existing.user?.nickname,
                  avatarUrl: existing.user?.avatarUrl,
                ),
        );
        final VoiceRoom nextRoom = current.withSeat(updated);
        final String? me = state.currentUserId;
        VoiceRole role = state.role;
        if (me != null && event.userId == me) {
          role = VoiceRole.speaker;
          unawaited(_refreshSpeakerToken());
        } else if (me != null) {
          role = _roleFor(nextRoom, me);
        }
        state = state.copyWith(room: nextRoom, role: role, clearError: true);
      case VoiceRealtimeEventType.speakerRemoved:
        if (current == null || event.seatIndex == null) return;
        final VoiceSeat existing =
            current.seatAt(event.seatIndex!) ??
            VoiceSeat(
              id: 'seat-${event.seatIndex}',
              seatIndex: event.seatIndex!,
              status: VoiceSeatStatus.occupied,
            );
        final VoiceSeat updated = existing.copyWith(
          status: VoiceSeatStatus.empty,
          isMuted: false,
          clearUser: true,
        );
        final VoiceRoom nextRoom = current.withSeat(updated);
        final String? me = state.currentUserId;
        VoiceRole role = state.role;
        if (me != null && event.userId == me) {
          role = VoiceRole.audience;
          unawaited(
            ref
                .read(voiceConnectionProvider(roomId).notifier)
                .downgradeToAudience(),
          );
        } else if (me != null) {
          role = _roleFor(nextRoom, me);
        }
        state = state.copyWith(room: nextRoom, role: role, clearError: true);
    }
  }

  Future<void> _refreshSpeakerToken() async {
    final RepositoryResult<VoiceRoomConnection> result = await ref.read(
      joinVoiceRoomUseCaseProvider,
    )(roomId);
    result.fold(
      onSuccess: (VoiceRoomConnection connection) {
        state = state.copyWith(room: connection.room, role: VoiceRole.speaker);
        unawaited(
          ref
              .read(voiceConnectionProvider(roomId).notifier)
              .connect(connection.rtc, asSpeaker: true),
        );
      },
      onFailure: (_) {},
    );
  }
}

final class VoiceConnectionController extends Notifier<VoiceConnectionState> {
  VoiceConnectionController(this.roomId);

  final String roomId;

  StreamSubscription<VoiceRtcEvent>? _eventsSub;

  @override
  VoiceConnectionState build() {
    final VoiceRtcDataSource rtc = ref.read(voiceRtcDataSourceProvider);
    _eventsSub = rtc.events.listen(_onRtcEvent);
    ref.onDispose(() {
      unawaited(_eventsSub?.cancel());
    });
    return const VoiceConnectionState();
  }

  Future<void> connect(
    VoiceRtcCredentials credentials, {
    required bool asSpeaker,
  }) async {
    state = state.copyWith(
      status: VoiceConnectionStatus.connecting,
      channel: credentials.channel,
      uid: credentials.uid,
      clearError: true,
    );
    try {
      await ref
          .read(voiceRtcDataSourceProvider)
          .join(credentials, asSpeaker: asSpeaker);
    } on Object catch (error) {
      state = state.copyWith(
        status: VoiceConnectionStatus.error,
        errorMessage: error.toString(),
      );
    }
  }

  Future<void> toggleMute() async {
    final bool muted = !state.isMicMuted;
    try {
      await ref.read(voiceRtcDataSourceProvider).muteMicrophone(muted);
      state = state.copyWith(isMicMuted: muted);
    } on Object catch (error) {
      state = state.copyWith(errorMessage: error.toString());
    }
  }

  Future<void> downgradeToAudience() async {
    state = state.copyWith(isMicMuted: true);
    try {
      await ref.read(voiceRtcDataSourceProvider).muteMicrophone(true);
    } on Object {
      // Best-effort mute when demoted from speaker.
    }
  }

  Future<void> disconnect() async {
    try {
      await ref.read(voiceRtcDataSourceProvider).leave();
    } on Object {
      // Ignore leave failures during teardown.
    }
    state = const VoiceConnectionState();
  }

  void _onRtcEvent(VoiceRtcEvent event) {
    switch (event) {
      case VoiceRtcConnected():
        state = state.copyWith(
          status: VoiceConnectionStatus.connected,
          clearError: true,
        );
      case VoiceRtcFailure(:final message):
        state = state.copyWith(
          status: VoiceConnectionStatus.error,
          errorMessage: message,
        );
    }
  }
}
