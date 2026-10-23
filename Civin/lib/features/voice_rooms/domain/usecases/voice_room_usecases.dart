import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:civin/features/voice_rooms/domain/repositories/voice_room_repository.dart';

final class CreateVoiceRoom {
  const CreateVoiceRoom(this._repository);

  final VoiceRoomRepository _repository;

  Future<RepositoryResult<VoiceRoomConnection>> call({
    required String title,
    String? description,
    String? thumbnail,
    int? seatCount,
  }) => _repository.createRoom(
    title: title,
    description: description,
    thumbnail: thumbnail,
    seatCount: seatCount,
  );
}

final class JoinVoiceRoom {
  const JoinVoiceRoom(this._repository);

  final VoiceRoomRepository _repository;

  Future<RepositoryResult<VoiceRoomConnection>> call(String roomId) =>
      _repository.joinRoom(roomId);
}

final class LeaveVoiceRoom {
  const LeaveVoiceRoom(this._repository);

  final VoiceRoomRepository _repository;

  Future<RepositoryResult<VoiceRoom>> call(String roomId) =>
      _repository.leaveRoom(roomId);
}

final class RequestVoiceSeat {
  const RequestVoiceSeat(this._repository);

  final VoiceRoomRepository _repository;

  Future<RepositoryResult<VoiceRoom>> call(
    String roomId, {
    required int seatIndex,
  }) => _repository.requestSeat(roomId, seatIndex: seatIndex);
}

final class ApproveVoiceSeat {
  const ApproveVoiceSeat(this._repository);

  final VoiceRoomRepository _repository;

  Future<RepositoryResult<VoiceRoom>> call(
    String roomId, {
    required int seatIndex,
  }) => _repository.approveSeat(roomId, seatIndex: seatIndex);
}

final class RejectVoiceSeat {
  const RejectVoiceSeat(this._repository);

  final VoiceRoomRepository _repository;

  Future<RepositoryResult<VoiceRoom>> call(
    String roomId, {
    required int seatIndex,
  }) => _repository.rejectSeat(roomId, seatIndex: seatIndex);
}

final class RemoveVoiceSpeaker {
  const RemoveVoiceSpeaker(this._repository);

  final VoiceRoomRepository _repository;

  Future<RepositoryResult<VoiceRoom>> call(
    String roomId, {
    required int seatIndex,
  }) => _repository.removeSpeaker(roomId, seatIndex: seatIndex);
}

final class MuteVoiceSpeaker {
  const MuteVoiceSpeaker(this._repository);

  final VoiceRoomRepository _repository;

  Future<RepositoryResult<VoiceRoom>> call(
    String roomId, {
    required int seatIndex,
    bool muted = true,
  }) => _repository.muteSpeaker(roomId, seatIndex: seatIndex, muted: muted);
}

final class EndVoiceRoom {
  const EndVoiceRoom(this._repository);

  final VoiceRoomRepository _repository;

  Future<RepositoryResult<VoiceRoom>> call(String roomId) =>
      _repository.endRoom(roomId);
}

final class ConnectVoiceRealtime {
  const ConnectVoiceRealtime(this._repository);

  final VoiceRoomRepository _repository;

  Future<void> call(String roomId) => _repository.connectRealtime(roomId);
}

final class DisconnectVoiceRealtime {
  const DisconnectVoiceRealtime(this._repository);

  final VoiceRoomRepository _repository;

  Future<void> call() => _repository.disconnectRealtime();
}
