import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/pk/domain/entities/pk_battle.dart';

abstract interface class PkRepository {
  Future<RepositoryResult<PkBattle>> requestPk(
    String roomId, {
    required String opponentRoomId,
    int? durationSeconds,
  });

  Future<RepositoryResult<PkBattle>> acceptPk(String roomId);

  Future<RepositoryResult<PkBattle>> startPk(String battleId);

  Future<RepositoryResult<PkBattle>> endPk(String battleId);

  Future<RepositoryResult<PkBattle>> getPk(String battleId);

  Stream<PkRealtimeEvent> watchPkEvents(String roomId);

  Future<void> connect(String roomId);

  Future<void> disconnect();
}
