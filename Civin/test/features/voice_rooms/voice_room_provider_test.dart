import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/voice_rooms/data/datasources/voice_rtc_data_source.dart';
import 'package:civin/features/voice_rooms/data/repositories/voice_room_repository_impl.dart';
import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:civin/features/voice_rooms/domain/repositories/voice_room_repository.dart';
import 'package:civin/features/voice_rooms/presentation/voice_room_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('voiceRoomProvider listens for VoiceRoomStarted and updates room', () async {
    final _FakeVoiceRepository repository = _FakeVoiceRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [
        voiceRoomRepositoryProvider.overrideWithValue(repository),
        voiceRtcDataSourceProvider.overrideWithValue(_FakeRtc()),
      ],
    );
    addTearDown(container.dispose);

    await container.read(voiceRoomProvider('room-1').notifier).startListening();
    expect(repository.connectedRoomId, 'room-1');
    expect(container.read(voiceRoomProvider('room-1')).isListening, isTrue);

    repository.emit(
      const VoiceRealtimeEvent(
        type: VoiceRealtimeEventType.roomStarted,
        roomId: 'room-1',
        hostId: 'host-1',
        seatCount: 8,
        participantCount: 1,
      ),
    );
    await Future<void>.delayed(Duration.zero);

    final VoiceRoomSessionState state = container.read(
      voiceRoomProvider('room-1'),
    );
    expect(state.room?.status, VoiceRoomStatus.live);
    expect(state.room?.seatCount, 8);
  });

  test('seatProvider reflects SeatUpdated realtime events', () async {
    final _FakeVoiceRepository repository = _FakeVoiceRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [
        voiceRoomRepositoryProvider.overrideWithValue(repository),
        voiceRtcDataSourceProvider.overrideWithValue(_FakeRtc()),
      ],
    );
    addTearDown(container.dispose);

    container
        .read(voiceRoomProvider('room-1').notifier)
        .seedRoom(_FakeVoiceRepository.liveRoom, role: VoiceRole.host);
    await container.read(voiceRoomProvider('room-1').notifier).startListening();

    repository.emit(
      const VoiceRealtimeEvent(
        type: VoiceRealtimeEventType.seatUpdated,
        roomId: 'room-1',
        seatIndex: 1,
        status: VoiceSeatStatus.pending,
        userId: 'user-2',
        isMuted: false,
      ),
    );
    await Future<void>.delayed(Duration.zero);

    final SeatViewState seats = container.read(seatProvider('room-1'));
    expect(seats.pendingRequests, 1);
    expect(seats.seats.where((VoiceSeat s) => s.isPending), hasLength(1));
  });

  test('voiceConnectionProvider connects after join', () async {
    final _FakeVoiceRepository repository = _FakeVoiceRepository();
    final _FakeRtc rtc = _FakeRtc();
    final ProviderContainer container = ProviderContainer(
      overrides: [
        voiceRoomRepositoryProvider.overrideWithValue(repository),
        voiceRtcDataSourceProvider.overrideWithValue(rtc),
      ],
    );
    addTearDown(container.dispose);

    final bool ok = await container
        .read(voiceRoomProvider('room-1').notifier)
        .join(asUserId: 'user-2');
    expect(ok, isTrue);
    await Future<void>.delayed(Duration.zero);

    expect(rtc.joined, isTrue);
    expect(
      container.read(voiceConnectionProvider('room-1')).status,
      VoiceConnectionStatus.connected,
    );
  });

  test('voiceRoomProvider requestMic and host mute/remove update seats', () async {
    final _FakeVoiceRepository repository = _FakeVoiceRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [
        voiceRoomRepositoryProvider.overrideWithValue(repository),
        voiceRtcDataSourceProvider.overrideWithValue(_FakeRtc()),
      ],
    );
    addTearDown(container.dispose);

    container
        .read(voiceRoomProvider('room-1').notifier)
        .seedRoom(
          _FakeVoiceRepository.liveRoom,
          role: VoiceRole.audience,
          userId: 'user-2',
        );

    final bool requested = await container
        .read(voiceRoomProvider('room-1').notifier)
        .requestMic(1);
    expect(requested, isTrue);
    expect(
      container.read(voiceRoomProvider('room-1')).room?.seatAt(1)?.isPending,
      isTrue,
    );

    container
        .read(voiceRoomProvider('room-1').notifier)
        .seedRoom(
          container.read(voiceRoomProvider('room-1')).room!,
          role: VoiceRole.host,
          userId: 'host-1',
        );

    final bool muted = await container
        .read(voiceRoomProvider('room-1').notifier)
        .muteSpeaker(1);
    // Mute fails while pending — approve first.
    expect(muted, isFalse);

    await container.read(voiceRoomProvider('room-1').notifier).approveSeat(1);
    final bool mutedOk = await container
        .read(voiceRoomProvider('room-1').notifier)
        .muteSpeaker(1);
    expect(mutedOk, isTrue);
    expect(
      container.read(voiceRoomProvider('room-1')).room?.seatAt(1)?.isMuted,
      isTrue,
    );

    final bool removed = await container
        .read(voiceRoomProvider('room-1').notifier)
        .removeSpeaker(1);
    expect(removed, isTrue);
    expect(
      container.read(voiceRoomProvider('room-1')).room?.seatAt(1)?.isEmpty,
      isTrue,
    );
  });

  test('voiceRoomProvider surfaces join failures', () async {
    final _FakeVoiceRepository repository = _FakeVoiceRepository()
      ..failJoin = true;
    final ProviderContainer container = ProviderContainer(
      overrides: [
        voiceRoomRepositoryProvider.overrideWithValue(repository),
        voiceRtcDataSourceProvider.overrideWithValue(_FakeRtc()),
      ],
    );
    addTearDown(container.dispose);

    final bool ok = await container
        .read(voiceRoomProvider('room-1').notifier)
        .join();
    expect(ok, isFalse);
    expect(
      container.read(voiceRoomProvider('room-1')).errorMessage,
      'Room ended',
    );
  });
}

final class _FakeRtc implements VoiceRtcDataSource {
  // ignore: close_sinks
  final StreamController<VoiceRtcEvent> _events =
      StreamController<VoiceRtcEvent>.broadcast();

  bool joined = false;

  @override
  Stream<VoiceRtcEvent> get events => _events.stream;

  @override
  Future<void> join(
    VoiceRtcCredentials credentials, {
    required bool asSpeaker,
  }) async {
    joined = true;
    _events.add(const VoiceRtcConnected());
  }

  @override
  Future<void> muteMicrophone(bool muted) async {}

  @override
  Future<void> leave() async {
    joined = false;
  }

  @override
  Future<void> dispose() async {}
}

final class _FakeVoiceRepository implements VoiceRoomRepository {
  // ignore: close_sinks
  final StreamController<VoiceRealtimeEvent> _events =
      StreamController<VoiceRealtimeEvent>.broadcast();

  String? connectedRoomId;
  bool failJoin = false;

  static const VoiceUser host = VoiceUser(
    id: 'host-1',
    username: 'host',
    nickname: 'Host',
  );
  static const VoiceUser speaker = VoiceUser(
    id: 'user-2',
    username: 'speaker',
    nickname: 'Speaker',
  );

  static final VoiceRoom liveRoom = VoiceRoom(
    id: 'room-1',
    title: 'Open mic',
    status: VoiceRoomStatus.live,
    seatCount: 8,
    participantCount: 1,
    host: host,
    seats: const <VoiceSeat>[
      VoiceSeat(
        id: 's0',
        seatIndex: 0,
        status: VoiceSeatStatus.occupied,
        user: host,
      ),
      VoiceSeat(id: 's1', seatIndex: 1, status: VoiceSeatStatus.empty),
      VoiceSeat(id: 's2', seatIndex: 2, status: VoiceSeatStatus.empty),
    ],
  );

  static const VoiceRtcCredentials rtc = VoiceRtcCredentials(
    appId: 'app',
    channel: 'voice-room-1',
    uid: 42,
    token: 'token',
  );

  VoiceRoom _room = liveRoom;

  @override
  Future<RepositoryResult<VoiceRoomConnection>> createRoom({
    required String title,
    String? description,
    String? thumbnail,
    int? seatCount,
  }) async => RepositorySuccess<VoiceRoomConnection>(
    VoiceRoomConnection(room: liveRoom, rtc: rtc),
  );

  @override
  Future<RepositoryResult<VoiceRoomConnection>> joinRoom(String roomId) async {
    if (failJoin) {
      return const RepositoryFailure<VoiceRoomConnection>(
        AppFailure.network(message: 'Room ended'),
      );
    }
    _room = _room.copyWith(participantCount: 2);
    return RepositorySuccess<VoiceRoomConnection>(
      VoiceRoomConnection(room: _room, rtc: rtc),
    );
  }

  @override
  Future<RepositoryResult<VoiceRoom>> leaveRoom(String roomId) async =>
      RepositorySuccess<VoiceRoom>(_room.copyWith(participantCount: 1));

  @override
  Future<RepositoryResult<VoiceRoom>> requestSeat(
    String roomId, {
    required int seatIndex,
  }) async {
    _room = _room.withSeat(
      VoiceSeat(
        id: 's$seatIndex',
        seatIndex: seatIndex,
        status: VoiceSeatStatus.pending,
        user: speaker,
      ),
    );
    return RepositorySuccess<VoiceRoom>(_room);
  }

  @override
  Future<RepositoryResult<VoiceRoom>> approveSeat(
    String roomId, {
    required int seatIndex,
  }) async {
    _room = _room.withSeat(
      VoiceSeat(
        id: 's$seatIndex',
        seatIndex: seatIndex,
        status: VoiceSeatStatus.occupied,
        user: speaker,
      ),
    );
    return RepositorySuccess<VoiceRoom>(_room);
  }

  @override
  Future<RepositoryResult<VoiceRoom>> rejectSeat(
    String roomId, {
    required int seatIndex,
  }) async {
    _room = _room.withSeat(
      VoiceSeat(
        id: 's$seatIndex',
        seatIndex: seatIndex,
        status: VoiceSeatStatus.empty,
      ),
    );
    return RepositorySuccess<VoiceRoom>(_room);
  }

  @override
  Future<RepositoryResult<VoiceRoom>> removeSpeaker(
    String roomId, {
    required int seatIndex,
  }) async {
    _room = _room.withSeat(
      VoiceSeat(
        id: 's$seatIndex',
        seatIndex: seatIndex,
        status: VoiceSeatStatus.empty,
      ),
    );
    return RepositorySuccess<VoiceRoom>(_room);
  }

  @override
  Future<RepositoryResult<VoiceRoom>> muteSpeaker(
    String roomId, {
    required int seatIndex,
    bool muted = true,
  }) async {
    final VoiceSeat? current = _room.seatAt(seatIndex);
    if (current == null || !current.isOccupied) {
      return const RepositoryFailure<VoiceRoom>(
        AppFailure.network(message: 'Seat not occupied'),
      );
    }
    _room = _room.withSeat(current.copyWith(isMuted: muted));
    return RepositorySuccess<VoiceRoom>(_room);
  }

  @override
  Future<RepositoryResult<VoiceRoom>> endRoom(String roomId) async =>
      RepositorySuccess<VoiceRoom>(
        _room.copyWith(status: VoiceRoomStatus.ended),
      );

  @override
  Stream<VoiceRealtimeEvent> watchEvents(String roomId) => _events.stream;

  @override
  Future<void> connectRealtime(String roomId) async {
    connectedRoomId = roomId;
  }

  @override
  Future<void> disconnectRealtime() async {
    connectedRoomId = null;
  }

  void emit(VoiceRealtimeEvent event) => _events.add(event);
}
