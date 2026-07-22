import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';

abstract interface class LiveRepository {
  Future<RepositoryResult<List<LiveRoom>>> getLiveRooms();
  Future<RepositoryResult<List<LiveCategory>>> getCategories();
  Future<RepositoryResult<LiveRoom>> createLiveRoom({
    required String title,
    required int categoryId,
    String? description,
  });
  Future<RepositoryResult<LiveConnection>> startStream(String roomId);
  Future<RepositoryResult<LiveConnection>> joinRoom(String roomId);
  Future<RepositoryResult<void>> leaveRoom(String roomId);
  Future<RepositoryResult<void>> endStream(String roomId);
}
