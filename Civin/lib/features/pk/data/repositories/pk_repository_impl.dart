import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/pk/data/datasources/pk_realtime_data_source.dart';
import 'package:civin/features/pk/data/datasources/pk_remote_data_source.dart';
import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:civin/features/pk/domain/repositories/pk_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<PkRepository> pkRepositoryProvider = Provider<PkRepository>(
  (Ref ref) => PkRepositoryImpl(
    ref.watch(pkRemoteDataSourceProvider),
    ref.watch(pkRealtimeDataSourceProvider),
  ),
);

final class PkRepositoryImpl extends BaseRepository implements PkRepository {
  PkRepositoryImpl(this._remote, this._realtime);

  final PkRemoteDataSource _remote;
  final PkRealtimeDataSource _realtime;

  @override
  Future<RepositoryResult<PkBattle>> requestPk(
    String roomId, {
    required String opponentRoomId,
    int? durationSeconds,
  }) => execute(
    () => _remote.requestPk(
      roomId,
      opponentRoomId: opponentRoomId,
      durationSeconds: durationSeconds,
    ),
  );

  @override
  Future<RepositoryResult<PkBattle>> acceptPk(String roomId) =>
      execute(() => _remote.acceptPk(roomId));

  @override
  Future<RepositoryResult<PkBattle>> startPk(String battleId) =>
      execute(() => _remote.startPk(battleId));

  @override
  Future<RepositoryResult<PkBattle>> endPk(String battleId) =>
      execute(() => _remote.endPk(battleId));

  @override
  Future<RepositoryResult<PkBattle>> getPk(String battleId) =>
      execute(() => _remote.getPk(battleId));

  @override
  Stream<PkRealtimeEvent> watchPkEvents(String roomId) => _realtime.events;

  @override
  Future<void> connect(String roomId) => _realtime.connect(roomId);

  @override
  Future<void> disconnect() => _realtime.disconnect();
}
