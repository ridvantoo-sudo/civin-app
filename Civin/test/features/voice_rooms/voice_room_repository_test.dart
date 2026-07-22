import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/voice_rooms/data/datasources/voice_room_realtime_data_source.dart';
import 'package:civin/features/voice_rooms/data/datasources/voice_room_remote_data_source.dart';
import 'package:civin/features/voice_rooms/data/repositories/voice_room_repository_impl.dart';
import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late _FakeRemote remote;
  late _FakeRealtime realtime;
  late VoiceRoomRepositoryImpl repository;

  setUp(() {
    remote = _FakeRemote();
    realtime = _FakeRealtime();
    repository = VoiceRoomRepositoryImpl(remote, realtime);
  });

  test('creates, joins, and leaves voice rooms through remote datasource', () async {
    final RepositoryResult<VoiceRoomConnection> created = await repository
        .createRoom(title: 'Late night', seatCount: 6);
    expect(created, isA<RepositorySuccess<VoiceRoomConnection>>());
    expect(
      (created as RepositorySuccess<VoiceRoomConnection>).data.room.seatCount,
      6,
    );
    expect(created.data.rtc.token, isNotEmpty);
    expect(remote.lastTitle, 'Late night');

    final RepositoryResult<VoiceRoomConnection> joined = await repository
        .joinRoom('room-1');
    expect(joined, isA<RepositorySuccess<VoiceRoomConnection>>());
    expect(
      (joined as RepositorySuccess<VoiceRoomConnection>)
          .data
          .room
          .participantCount,
      2,
    );

    final RepositoryResult<VoiceRoom> left = await repository.leaveRoom(
      'room-1',
    );
    expect(left, isA<RepositorySuccess<VoiceRoom>>());
    expect(
      (left as RepositorySuccess<VoiceRoom>).data.participantCount,
      1,
    );
  });

  test('manages seats and maps remote failures', () async {
    final RepositoryResult<VoiceRoom> requested = await repository.requestSeat(
      'room-1',
      seatIndex: 1,
    );
    expect(requested, isA<RepositorySuccess<VoiceRoom>>());
    expect(
      (requested as RepositorySuccess<VoiceRoom>).data.seatAt(1)?.status,
      VoiceSeatStatus.pending,
    );

    final RepositoryResult<VoiceRoom> approved = await repository.approveSeat(
      'room-1',
      seatIndex: 1,
    );
    expect(approved, isA<RepositorySuccess<VoiceRoom>>());
    expect(
      (approved as RepositorySuccess<VoiceRoom>).data.seatAt(1)?.status,
      VoiceSeatStatus.occupied,
    );

    final RepositoryResult<VoiceRoom> muted = await repository.muteSpeaker(
      'room-1',
      seatIndex: 1,
    );
    expect(muted, isA<RepositorySuccess<VoiceRoom>>());
    expect((muted as RepositorySuccess<VoiceRoom>).data.seatAt(1)?.isMuted, isTrue);

    final RepositoryResult<VoiceRoom> removed = await repository.removeSpeaker(
      'room-1',
      seatIndex: 1,
    );
    expect(removed, isA<RepositorySuccess<VoiceRoom>>());
    expect(
      (removed as RepositorySuccess<VoiceRoom>).data.seatAt(1)?.status,
      VoiceSeatStatus.empty,
    );

    remote.error = DioException(
      requestOptions: RequestOptions(path: '/api/v1/voice/room-1/seat/request'),
      response: Response<dynamic>(
        requestOptions: RequestOptions(
          path: '/api/v1/voice/room-1/seat/request',
        ),
        statusCode: 422,
        data: <String, dynamic>{'message': 'Seat unavailable'},
      ),
    );

    final RepositoryResult<VoiceRoom> failed = await repository.requestSeat(
      'room-1',
      seatIndex: 2,
    );
    expect(failed, isA<RepositoryFailure<VoiceRoom>>());
    expect(
      (failed as RepositoryFailure<VoiceRoom>).failure,
      isA<NetworkFailure>(),
    );
    expect(failed.failure.message, 'Seat unavailable');
  });

  test('connects realtime channel and exposes VoiceRoomStarted stream', () async {
    await repository.connectRealtime('room-1');
    expect(realtime.connectedRoomId, 'room-1');

    final Future<void> expectation = expectLater(
      repository.watchEvents('room-1'),
      emits(
        isA<VoiceRealtimeEvent>().having(
          (VoiceRealtimeEvent e) => e.type,
          'type',
          VoiceRealtimeEventType.roomStarted,
        ),
      ),
    );
    realtime.emit(
      const VoiceRealtimeEvent(
        type: VoiceRealtimeEventType.roomStarted,
        roomId: 'room-1',
        hostId: 'host-1',
        seatCount: 8,
        participantCount: 1,
      ),
    );
    await expectation;
  });
}

final class _FakeRemote implements VoiceRoomRemoteDataSource {
  Object? error;
  String? lastTitle;

  static const VoiceUser host = VoiceUser(id: 'host-1', username: 'host');
  static const VoiceUser speaker = VoiceUser(id: 'user-2', username: 'speaker');

  static final VoiceRoom baseRoom = VoiceRoom(
    id: 'room-1',
    title: 'Late night',
    status: VoiceRoomStatus.live,
    seatCount: 6,
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

  static final VoiceRtcCredentials rtc = VoiceRtcCredentials(
    appId: 'a' * 32,
    channel: 'voice-room-1',
    uid: 101,
    token: '007token',
  );

  VoiceRoom _room = baseRoom;

  @override
  Future<VoiceRoomConnection> createRoom({
    required String title,
    String? description,
    String? thumbnail,
    int? seatCount,
  }) async {
    _throwIfNeeded();
    lastTitle = title;
    _room = baseRoom.copyWith(
      seats: baseRoom.seats,
      participantCount: 1,
    );
    return VoiceRoomConnection(room: _room, rtc: rtc);
  }

  @override
  Future<VoiceRoomConnection> joinRoom(String roomId) async {
    _throwIfNeeded();
    _room = _room.copyWith(participantCount: 2);
    return VoiceRoomConnection(room: _room, rtc: rtc);
  }

  @override
  Future<VoiceRoom> leaveRoom(String roomId) async {
    _throwIfNeeded();
    _room = _room.copyWith(participantCount: 1);
    return _room;
  }

  @override
  Future<VoiceRoom> requestSeat(
    String roomId, {
    required int seatIndex,
  }) async {
    _throwIfNeeded();
    _room = _room.withSeat(
      VoiceSeat(
        id: 's$seatIndex',
        seatIndex: seatIndex,
        status: VoiceSeatStatus.pending,
        user: speaker,
      ),
    );
    return _room;
  }

  @override
  Future<VoiceRoom> approveSeat(
    String roomId, {
    required int seatIndex,
  }) async {
    _throwIfNeeded();
    _room = _room.withSeat(
      VoiceSeat(
        id: 's$seatIndex',
        seatIndex: seatIndex,
        status: VoiceSeatStatus.occupied,
        user: speaker,
      ),
    );
    return _room;
  }

  @override
  Future<VoiceRoom> rejectSeat(
    String roomId, {
    required int seatIndex,
  }) async {
    _throwIfNeeded();
    _room = _room.withSeat(
      VoiceSeat(
        id: 's$seatIndex',
        seatIndex: seatIndex,
        status: VoiceSeatStatus.empty,
      ),
    );
    return _room;
  }

  @override
  Future<VoiceRoom> removeSpeaker(
    String roomId, {
    required int seatIndex,
  }) async {
    _throwIfNeeded();
    _room = _room.withSeat(
      VoiceSeat(
        id: 's$seatIndex',
        seatIndex: seatIndex,
        status: VoiceSeatStatus.empty,
      ),
    );
    return _room;
  }

  @override
  Future<VoiceRoom> muteSpeaker(
    String roomId, {
    required int seatIndex,
    bool muted = true,
  }) async {
    _throwIfNeeded();
    final VoiceSeat? current = _room.seatAt(seatIndex);
    _room = _room.withSeat(
      VoiceSeat(
        id: current?.id ?? 's$seatIndex',
        seatIndex: seatIndex,
        status: VoiceSeatStatus.occupied,
        isMuted: muted,
        user: current?.user ?? speaker,
      ),
    );
    return _room;
  }

  @override
  Future<VoiceRoom> endRoom(String roomId) async {
    _throwIfNeeded();
    _room = _room.copyWith(status: VoiceRoomStatus.ended);
    return _room;
  }

  void _throwIfNeeded() {
    final Object? current = error;
    if (current != null) throw current;
  }
}

final class _FakeRealtime implements VoiceRoomRealtimeDataSource {
  // ignore: close_sinks
  final StreamController<VoiceRealtimeEvent> _events =
      StreamController<VoiceRealtimeEvent>.broadcast();

  String? connectedRoomId;

  @override
  Stream<VoiceRealtimeEvent> get events => _events.stream;

  @override
  Future<void> connect(String roomId) async {
    connectedRoomId = roomId;
  }

  @override
  Future<void> disconnect() async {
    connectedRoomId = null;
  }

  void emit(VoiceRealtimeEvent event) => _events.add(event);
}
