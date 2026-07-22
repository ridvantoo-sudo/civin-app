import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:civin/features/pk/domain/repositories/pk_repository.dart';

final class RequestPk {
  const RequestPk(this._repository);

  final PkRepository _repository;

  Future<RepositoryResult<PkBattle>> call(
    String roomId, {
    required String opponentRoomId,
    int? durationSeconds,
  }) => _repository.requestPk(
    roomId,
    opponentRoomId: opponentRoomId,
    durationSeconds: durationSeconds,
  );
}

final class AcceptPk {
  const AcceptPk(this._repository);

  final PkRepository _repository;

  Future<RepositoryResult<PkBattle>> call(String roomId) =>
      _repository.acceptPk(roomId);
}

final class StartPk {
  const StartPk(this._repository);

  final PkRepository _repository;

  Future<RepositoryResult<PkBattle>> call(String battleId) =>
      _repository.startPk(battleId);
}

final class EndPk {
  const EndPk(this._repository);

  final PkRepository _repository;

  Future<RepositoryResult<PkBattle>> call(String battleId) =>
      _repository.endPk(battleId);
}

final class GetPk {
  const GetPk(this._repository);

  final PkRepository _repository;

  Future<RepositoryResult<PkBattle>> call(String battleId) =>
      _repository.getPk(battleId);
}

final class ConnectPkRealtime {
  const ConnectPkRealtime(this._repository);

  final PkRepository _repository;

  Future<void> call(String roomId) => _repository.connect(roomId);
}

final class DisconnectPkRealtime {
  const DisconnectPkRealtime(this._repository);

  final PkRepository _repository;

  Future<void> call() => _repository.disconnect();
}
