import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/domain/repositories/live_repository.dart';

final class BrowseLiveRooms {
  const BrowseLiveRooms(this._repository);
  final LiveRepository _repository;

  Future<RepositoryResult<List<LiveRoom>>> call() => _repository.getLiveRooms();
}

final class BrowseLiveCategories {
  const BrowseLiveCategories(this._repository);
  final LiveRepository _repository;

  Future<RepositoryResult<List<LiveCategory>>> call() =>
      _repository.getCategories();
}

final class CreateLiveRoom {
  const CreateLiveRoom(this._repository);
  final LiveRepository _repository;

  Future<RepositoryResult<LiveRoom>> call({
    required String title,
    required int categoryId,
    String? description,
  }) => _repository.createLiveRoom(
    title: title.trim(),
    categoryId: categoryId,
    description: description?.trim(),
  );
}

final class StartLiveStream {
  const StartLiveStream(this._repository);
  final LiveRepository _repository;

  Future<RepositoryResult<LiveConnection>> call(String roomId) =>
      _repository.startStream(roomId);
}

final class JoinLiveRoom {
  const JoinLiveRoom(this._repository);
  final LiveRepository _repository;

  Future<RepositoryResult<LiveConnection>> call(String roomId) =>
      _repository.joinRoom(roomId);
}

final class LeaveLiveRoom {
  const LeaveLiveRoom(this._repository);
  final LiveRepository _repository;

  Future<RepositoryResult<void>> call(String roomId) =>
      _repository.leaveRoom(roomId);
}

final class EndLiveStream {
  const EndLiveStream(this._repository);
  final LiveRepository _repository;

  Future<RepositoryResult<void>> call(String roomId) =>
      _repository.endStream(roomId);
}
