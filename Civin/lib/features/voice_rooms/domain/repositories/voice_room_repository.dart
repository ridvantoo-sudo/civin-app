import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';

abstract interface class VoiceRoomRepository {
  Future<RepositoryResult<VoiceRoomConnection>> createRoom({
    required String title,
    String? description,
    String? thumbnail,
    int? seatCount,
  });

  Future<RepositoryResult<VoiceRoomConnection>> joinRoom(String roomId);

  Future<RepositoryResult<VoiceRoom>> leaveRoom(String roomId);

  Future<RepositoryResult<VoiceRoom>> requestSeat(
    String roomId, {
    required int seatIndex,
  });

  Future<RepositoryResult<VoiceRoom>> approveSeat(
    String roomId, {
    required int seatIndex,
  });

  Future<RepositoryResult<VoiceRoom>> rejectSeat(
    String roomId, {
    required int seatIndex,
  });

  Future<RepositoryResult<VoiceRoom>> removeSpeaker(
    String roomId, {
    required int seatIndex,
  });

  Future<RepositoryResult<VoiceRoom>> muteSpeaker(
    String roomId, {
    required int seatIndex,
    bool muted = true,
  });

  Future<RepositoryResult<VoiceRoom>> endRoom(String roomId);

  Stream<VoiceRealtimeEvent> watchEvents(String roomId);

  Future<void> connectRealtime(String roomId);

  Future<void> disconnectRealtime();
}
