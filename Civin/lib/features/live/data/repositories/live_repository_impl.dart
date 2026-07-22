import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/live/data/datasources/live_remote_data_source.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/domain/repositories/live_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<LiveRepository> liveRepositoryProvider =
    Provider<LiveRepository>(
      (Ref ref) => LiveRepositoryImpl(ref.watch(liveRemoteDataSourceProvider)),
    );

final class LiveRepositoryImpl extends BaseRepository
    implements LiveRepository {
  const LiveRepositoryImpl(this._remote);

  final LiveRemoteDataSource _remote;

  @override
  Future<RepositoryResult<List<LiveRoom>>> getLiveRooms() =>
      execute(_remote.getLiveRooms);

  @override
  Future<RepositoryResult<List<LiveCategory>>> getCategories() =>
      execute(_remote.getCategories);

  @override
  Future<RepositoryResult<LiveRoom>> createLiveRoom({
    required String title,
    required int categoryId,
    String? description,
  }) => execute(
    () => _remote.createLiveRoom(
      title: title,
      categoryId: categoryId,
      description: description,
    ),
  );

  @override
  Future<RepositoryResult<LiveConnection>> startStream(String roomId) =>
      execute(() => _remote.startStream(roomId));

  @override
  Future<RepositoryResult<LiveConnection>> joinRoom(String roomId) =>
      execute(() => _remote.joinRoom(roomId));

  @override
  Future<RepositoryResult<void>> leaveRoom(String roomId) =>
      execute(() => _remote.leaveRoom(roomId));

  @override
  Future<RepositoryResult<void>> endStream(String roomId) =>
      execute(() => _remote.endStream(roomId));
}
