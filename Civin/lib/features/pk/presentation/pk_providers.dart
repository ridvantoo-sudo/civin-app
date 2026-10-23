import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/pk/data/repositories/pk_repository_impl.dart';
import 'package:civin/features/pk/domain/entities/pk_battle.dart';
import 'package:civin/features/pk/domain/repositories/pk_repository.dart';
import 'package:civin/features/pk/domain/usecases/pk_usecases.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<RequestPk> requestPkProvider = Provider<RequestPk>(
  (Ref ref) => RequestPk(ref.watch(pkRepositoryProvider)),
);

final Provider<AcceptPk> acceptPkProvider = Provider<AcceptPk>(
  (Ref ref) => AcceptPk(ref.watch(pkRepositoryProvider)),
);

final Provider<StartPk> startPkProvider = Provider<StartPk>(
  (Ref ref) => StartPk(ref.watch(pkRepositoryProvider)),
);

final Provider<EndPk> endPkProvider = Provider<EndPk>(
  (Ref ref) => EndPk(ref.watch(pkRepositoryProvider)),
);

final Provider<GetPk> getPkProvider = Provider<GetPk>(
  (Ref ref) => GetPk(ref.watch(pkRepositoryProvider)),
);

final Provider<ConnectPkRealtime> connectPkRealtimeProvider =
    Provider<ConnectPkRealtime>(
      (Ref ref) => ConnectPkRealtime(ref.watch(pkRepositoryProvider)),
    );

final Provider<DisconnectPkRealtime> disconnectPkRealtimeProvider =
    Provider<DisconnectPkRealtime>(
      (Ref ref) => DisconnectPkRealtime(ref.watch(pkRepositoryProvider)),
    );

/// Room PK battle state + host actions + realtime lifecycle.
final pkProvider = NotifierProvider.family<PkController, PkRoomState, String>(
  PkController.new,
);

/// Live score snapshot derived from [pkProvider].
final pkScoreProvider = Provider.family<PkScoreView, String>((
  Ref ref,
  String roomId,
) {
  final PkBattle? battle = ref.watch(pkProvider(roomId)).battle;
  if (battle == null) return const PkScoreView();
  return PkScoreView(
    scoreA: battle.scoreA,
    scoreB: battle.scoreB,
    hostNameA: battle.hostNameA,
    hostNameB: battle.hostNameB,
    hostAvatarA: battle.hostA?.avatarUrl,
    hostAvatarB: battle.hostB?.avatarUrl,
    battleId: battle.id,
  );
});

/// Countdown timer synced to a running PK battle.
final pkTimerProvider =
    NotifierProvider.family<PkTimerController, PkTimerState, String>(
      PkTimerController.new,
    );

final class PkController extends Notifier<PkRoomState> {
  PkController(this.roomId);

  final String roomId;

  StreamSubscription<PkRealtimeEvent>? _eventsSub;
  bool _started = false;

  @override
  PkRoomState build() {
    final PkRepository repository = ref.read(pkRepositoryProvider);
    ref.onDispose(() {
      unawaited(_eventsSub?.cancel());
      unawaited(repository.disconnect());
    });
    return PkRoomState(roomId: roomId);
  }

  Future<void> startListening() async {
    if (_started) return;
    _started = true;

    final PkRepository repository = ref.read(pkRepositoryProvider);
    await _eventsSub?.cancel();
    _eventsSub = repository.watchPkEvents(roomId).listen(_onRealtimeEvent);

    try {
      await ref.read(connectPkRealtimeProvider)(roomId);
      state = state.copyWith(isListening: true, clearError: true);
    } on Object catch (error) {
      state = state.copyWith(
        isListening: false,
        errorMessage: error.toString(),
      );
    }
  }

  Future<bool> requestPk({
    required String opponentRoomId,
    int durationSeconds = 180,
  }) async {
    if (state.isBusy) return false;
    state = state.copyWith(isBusy: true, clearError: true);

    final RepositoryResult<PkBattle> result = await ref.read(
      requestPkProvider,
    )(
      roomId,
      opponentRoomId: opponentRoomId,
      durationSeconds: durationSeconds,
    );

    return result.fold(
      onSuccess: (PkBattle battle) {
        state = state.copyWith(isBusy: false, battle: battle, clearError: true);
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isBusy: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<bool> acceptPk() async {
    if (state.isBusy) return false;
    state = state.copyWith(isBusy: true, clearError: true);

    final RepositoryResult<PkBattle> result = await ref.read(
      acceptPkProvider,
    )(roomId);

    return result.fold(
      onSuccess: (PkBattle battle) {
        state = state.copyWith(
          isBusy: false,
          battle: battle,
          incomingRequest: false,
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isBusy: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<bool> startPk() async {
    final String? battleId = state.battle?.id;
    if (battleId == null || state.isBusy) return false;
    state = state.copyWith(isBusy: true, clearError: true);

    final RepositoryResult<PkBattle> result = await ref.read(
      startPkProvider,
    )(battleId);

    return result.fold(
      onSuccess: (PkBattle battle) {
        state = state.copyWith(
          isBusy: false,
          battle: battle,
          showResult: false,
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isBusy: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<bool> endPk() async {
    final String? battleId = state.battle?.id;
    if (battleId == null || state.isBusy) return false;
    state = state.copyWith(isBusy: true, clearError: true);

    final RepositoryResult<PkBattle> result = await ref.read(
      endPkProvider,
    )(battleId);

    return result.fold(
      onSuccess: (PkBattle battle) {
        state = state.copyWith(
          isBusy: false,
          battle: battle,
          showResult: true,
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isBusy: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  void dismissResult() {
    state = state.copyWith(showResult: false);
  }

  void clearBattle() {
    state = state.copyWith(clearBattle: true, showResult: false);
  }

  void _onRealtimeEvent(PkRealtimeEvent event) {
    final PkBattle battle = event.battle;
    final bool belongsToRoom =
        battle.roomAId == roomId || battle.roomBId == roomId;
    if (!belongsToRoom) return;

    switch (event.type) {
      case PkRealtimeEventType.started:
        state = state.copyWith(
          battle: battle,
          showResult: false,
          clearError: true,
        );
      case PkRealtimeEventType.scoreUpdated:
        state = state.copyWith(battle: battle, clearError: true);
      case PkRealtimeEventType.finished:
        state = state.copyWith(
          battle: battle,
          showResult: true,
          clearError: true,
        );
    }
  }
}

final class PkTimerController extends Notifier<PkTimerState> {
  PkTimerController(this.roomId);

  final String roomId;

  Timer? _ticker;
  String? _syncedBattleKey;

  @override
  PkTimerState build() {
    ref.onDispose(_cancelTicker);
    ref.listen<PkRoomState>(
      pkProvider(roomId),
      (PkRoomState? previous, PkRoomState next) {
        final PkBattle? battle = next.battle;
        if (battle == null) {
          _syncedBattleKey = null;
          reset();
          return;
        }

        final String key =
            '${battle.id}:${battle.status}:${battle.startedAt?.toIso8601String()}';
        if (key == _syncedBattleKey && battle.isRunning) {
          return;
        }
        _syncedBattleKey = key;

        if (battle.isRunning) {
          syncFromBattle(battle);
        } else if (battle.isFinished) {
          stop();
        } else {
          _cancelTicker();
          state = PkTimerState(
            remainingSeconds: battle.durationSeconds,
            durationSeconds: battle.durationSeconds,
          );
        }
      },
      fireImmediately: true,
    );
    return const PkTimerState();
  }

  void syncFromBattle(PkBattle battle) {
    state = _snapshotFor(battle);
    if (state.isRunning) {
      _startTicker();
    } else {
      _cancelTicker();
    }
  }

  void stop() {
    _cancelTicker();
    state = state.copyWith(
      remainingSeconds: 0,
      isRunning: false,
      isFinished: true,
    );
  }

  void reset() {
    _cancelTicker();
    state = const PkTimerState();
  }

  PkTimerState _snapshotFor(PkBattle battle) {
    if (!battle.isRunning || battle.startedAt == null) {
      return PkTimerState(
        remainingSeconds: battle.isFinished ? 0 : battle.durationSeconds,
        durationSeconds: battle.durationSeconds,
        isRunning: false,
        isFinished: battle.isFinished,
      );
    }

    final DateTime started = battle.startedAt!.toUtc();
    final DateTime ends = started.add(
      Duration(seconds: battle.durationSeconds),
    );
    final int remaining = ends.difference(DateTime.now().toUtc()).inSeconds;
    return PkTimerState(
      remainingSeconds: remaining < 0 ? 0 : remaining,
      durationSeconds: battle.durationSeconds,
      isRunning: remaining > 0,
      isFinished: remaining <= 0,
    );
  }

  void _startTicker() {
    _cancelTicker();
    _ticker = Timer.periodic(const Duration(seconds: 1), (_) {
      final int next = state.remainingSeconds - 1;
      if (next <= 0) {
        state = state.copyWith(
          remainingSeconds: 0,
          isRunning: false,
          isFinished: true,
        );
        _cancelTicker();
        return;
      }
      state = state.copyWith(remainingSeconds: next, isRunning: true);
    });
  }

  void _cancelTicker() {
    _ticker?.cancel();
    _ticker = null;
  }
}
