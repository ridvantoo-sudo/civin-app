import 'dart:async';
import 'dart:collection';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/gifts/data/repositories/gift_repository_impl.dart';
import 'package:civin/features/gifts/domain/entities/gift.dart';
import 'package:civin/features/gifts/domain/repositories/gift_repository.dart';
import 'package:civin/features/gifts/domain/usecases/gift_usecases.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<GetGiftCatalog> getGiftCatalogProvider = Provider<GetGiftCatalog>(
  (Ref ref) => GetGiftCatalog(ref.watch(giftRepositoryProvider)),
);

final Provider<SendGift> sendGiftUseCaseProvider = Provider<SendGift>(
  (Ref ref) => SendGift(ref.watch(giftRepositoryProvider)),
);

final Provider<GetGiftHistory> getGiftHistoryProvider = Provider<GetGiftHistory>(
  (Ref ref) => GetGiftHistory(ref.watch(giftRepositoryProvider)),
);

final Provider<ConnectGiftRealtime> connectGiftRealtimeProvider =
    Provider<ConnectGiftRealtime>(
      (Ref ref) => ConnectGiftRealtime(ref.watch(giftRepositoryProvider)),
    );

final Provider<DisconnectGiftRealtime> disconnectGiftRealtimeProvider =
    Provider<DisconnectGiftRealtime>(
      (Ref ref) => DisconnectGiftRealtime(ref.watch(giftRepositoryProvider)),
    );

/// Gift catalog (categories + gifts).
final AsyncNotifierProvider<GiftCatalogController, GiftCatalog>
giftCatalogProvider =
    AsyncNotifierProvider<GiftCatalogController, GiftCatalog>(
      GiftCatalogController.new,
    );

/// Room gift state: realtime events, animation queue, panel, history.
final giftProvider =
    NotifierProvider.family<GiftController, GiftRoomState, String>(
      GiftController.new,
    );

/// Send-gift action state for a live room.
final giftSendingProvider =
    NotifierProvider.family<GiftSendingController, GiftSendingState, String>(
      GiftSendingController.new,
    );

final giftHistoryProvider =
    FutureProvider.family<List<GiftTransaction>, String>((
      Ref ref,
      String userId,
    ) async {
      final RepositoryResult<List<GiftTransaction>> result = await ref.read(
        getGiftHistoryProvider,
      )(userId);
      return result.fold(
        onSuccess: (List<GiftTransaction> items) => items,
        onFailure: (failure) => throw Exception(failure.message),
      );
    });

final class GiftCatalogController extends AsyncNotifier<GiftCatalog> {
  @override
  Future<GiftCatalog> build() => _load();

  Future<void> refresh() async {
    state = const AsyncLoading<GiftCatalog>();
    state = await AsyncValue.guard(_load);
  }

  Future<GiftCatalog> _load() async {
    final RepositoryResult<GiftCatalog> result = await ref.read(
      getGiftCatalogProvider,
    )();
    return result.fold(
      onSuccess: (GiftCatalog catalog) => catalog,
      onFailure: (failure) => throw Exception(failure.message),
    );
  }
}

final class GiftController extends Notifier<GiftRoomState> {
  GiftController(this.roomId);

  final String roomId;

  StreamSubscription<GiftSentEvent>? _giftSub;
  final Queue<GiftSentEvent> _animationQueue = Queue<GiftSentEvent>();
  bool _started = false;

  @override
  GiftRoomState build() {
    final GiftRepository repository = ref.read(giftRepositoryProvider);
    ref.onDispose(() {
      unawaited(_giftSub?.cancel());
      unawaited(repository.disconnect());
    });
    return GiftRoomState(roomId: roomId);
  }

  Future<void> startListening() async {
    if (_started) return;
    _started = true;

    final GiftRepository repository = ref.read(giftRepositoryProvider);
    await _giftSub?.cancel();
    _giftSub = repository.watchGiftSent(roomId).listen(_onGiftSent);

    try {
      await ref.read(connectGiftRealtimeProvider)(roomId);
      state = state.copyWith(isListening: true, clearError: true);
    } on Object catch (error) {
      state = state.copyWith(
        isListening: false,
        errorMessage: error.toString(),
      );
    }
  }

  void openPanel() => state = state.copyWith(panelOpen: true);

  void closePanel() => state = state.copyWith(panelOpen: false);

  void selectCategory(String? categoryId) {
    if (categoryId == null) {
      state = state.copyWith(clearSelectedCategory: true);
      return;
    }
    state = state.copyWith(selectedCategoryId: categoryId);
  }

  void dismissCurrentAnimation() {
    if (_animationQueue.isNotEmpty) {
      final GiftSentEvent next = _animationQueue.removeFirst();
      state = state.copyWith(currentAnimation: next);
      return;
    }
    state = state.copyWith(clearAnimation: true);
  }

  void _onGiftSent(GiftSentEvent event) {
    if (event.roomId.isNotEmpty && event.roomId != roomId) return;

    final List<GiftSentEvent> history = List<GiftSentEvent>.of(state.history)
      ..insert(0, event);
    if (history.length > 50) {
      history.removeRange(50, history.length);
    }

    if (state.currentAnimation == null) {
      state = state.copyWith(
        history: List<GiftSentEvent>.unmodifiable(history),
        currentAnimation: event,
        clearError: true,
      );
      return;
    }

    _animationQueue.add(event);
    state = state.copyWith(
      history: List<GiftSentEvent>.unmodifiable(history),
      clearError: true,
    );
  }
}

final class GiftSendingController extends Notifier<GiftSendingState> {
  GiftSendingController(this.roomId);

  final String roomId;

  @override
  GiftSendingState build() => const GiftSendingState();

  void selectGift(String giftId) {
    state = state.copyWith(selectedGiftId: giftId, clearError: true);
  }

  void setQuantity(int quantity) {
    state = state.copyWith(
      quantity: quantity.clamp(1, 999),
      clearError: true,
    );
  }

  Future<bool> send({String? giftId, int? quantity}) async {
    final String? resolvedGiftId = giftId ?? state.selectedGiftId;
    if (resolvedGiftId == null || resolvedGiftId.isEmpty || state.isSending) {
      return false;
    }

    final int resolvedQuantity = quantity ?? state.quantity;
    state = state.copyWith(
      isSending: true,
      selectedGiftId: resolvedGiftId,
      quantity: resolvedQuantity,
      clearError: true,
    );

    final RepositoryResult<GiftTransaction> result = await ref.read(
      sendGiftUseCaseProvider,
    )(
      roomId,
      giftId: resolvedGiftId,
      quantity: resolvedQuantity,
    );

    return result.fold(
      onSuccess: (GiftTransaction transaction) {
        state = state.copyWith(
          isSending: false,
          lastSent: transaction,
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(
          isSending: false,
          errorMessage: failure.message,
        );
        return false;
      },
    );
  }
}
