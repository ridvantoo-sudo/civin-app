import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/voice_rooms/data/datasources/voice_room_realtime_data_source.dart';
import 'package:civin/features/voice_rooms/data/datasources/voice_room_remote_data_source.dart';
import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:civin/features/voice_rooms/domain/repositories/voice_room_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<VoiceRoomRepository> voiceRoomRepositoryProvider =
    Provider<VoiceRoomRepository>(
      (Ref ref) => VoiceRoomRepositoryImpl(
        ref.watch(voiceRoomRemoteDataSourceProvider),
        ref.watch(voiceRoomRealtimeDataSourceProvider),
      ),
    );

final class VoiceRoomRepositoryImpl extends BaseRepository
    implements VoiceRoomRepository {
  VoiceRoomRepositoryImpl(this._remote, this._realtime);

  final VoiceRoomRemoteDataSource _remote;
  final VoiceRoomRealtimeDataSource _realtime;

  @override
  Future<RepositoryResult<VoiceRoomConnection>> createRoom({
    required String title,
    String? description,
    String? thumbnail,
    int? seatCount,
  }) => execute(
    () => _remote.createRoom(
      title: title,
      description: description,
      thumbnail: thumbnail,
      seatCount: seatCount,
    ),
  );

  @override
  Future<RepositoryResult<VoiceRoomConnection>> joinRoom(String roomId) =>
      execute(() => _remote.joinRoom(roomId));

  @override
  Future<RepositoryResult<VoiceRoom>> leaveRoom(String roomId) =>
      execute(() => _remote.leaveRoom(roomId));

  @override
  Future<RepositoryResult<VoiceRoom>> requestSeat(
    String roomId, {
    required int seatIndex,
  }) => execute(() => _remote.requestSeat(roomId, seatIndex: seatIndex));

  @override
  Future<RepositoryResult<VoiceRoom>> approveSeat(
    String roomId, {
    required int seatIndex,
  }) => execute(() => _remote.approveSeat(roomId, seatIndex: seatIndex));

  @override
  Future<RepositoryResult<VoiceRoom>> rejectSeat(
    String roomId, {
    required int seatIndex,
  }) => execute(() => _remote.rejectSeat(roomId, seatIndex: seatIndex));

  @override
  Future<RepositoryResult<VoiceRoom>> removeSpeaker(
    String roomId, {
    required int seatIndex,
  }) => execute(() => _remote.removeSpeaker(roomId, seatIndex: seatIndex));

  @override
  Future<RepositoryResult<VoiceRoom>> muteSpeaker(
    String roomId, {
    required int seatIndex,
    bool muted = true,
  }) => execute(
    () => _remote.muteSpeaker(roomId, seatIndex: seatIndex, muted: muted),
  );

  @override
  Future<RepositoryResult<VoiceRoom>> endRoom(String roomId) =>
      execute(() => _remote.endRoom(roomId));

  @override
  Stream<VoiceRealtimeEvent> watchEvents(String roomId) => _realtime.events;

  @override
  Future<void> connectRealtime(String roomId) => _realtime.connect(roomId);

  @override
  Future<void> disconnectRealtime() => _realtime.disconnect();
}
