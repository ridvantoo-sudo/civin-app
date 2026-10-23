import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/vip/data/repositories/vip_repository_impl.dart';
import 'package:civin/features/vip/domain/entities/vip.dart';
import 'package:civin/features/vip/domain/usecases/vip_usecases.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<GetVipLevels> getVipLevelsUseCaseProvider =
    Provider<GetVipLevels>(
      (Ref ref) => GetVipLevels(ref.watch(vipRepositoryProvider)),
    );

final Provider<GetMyVip> getMyVipUseCaseProvider = Provider<GetMyVip>(
  (Ref ref) => GetMyVip(ref.watch(vipRepositoryProvider)),
);

final Provider<PurchaseVip> purchaseVipUseCaseProvider = Provider<PurchaseVip>(
  (Ref ref) => PurchaseVip(ref.watch(vipRepositoryProvider)),
);

final Provider<UpgradeVip> upgradeVipUseCaseProvider = Provider<UpgradeVip>(
  (Ref ref) => UpgradeVip(ref.watch(vipRepositoryProvider)),
);

/// VIP catalog + current subscription state.
final NotifierProvider<VipController, VipViewState> vipProvider =
    NotifierProvider<VipController, VipViewState>(VipController.new);

final class VipController extends Notifier<VipViewState> {
  @override
  VipViewState build() => const VipViewState();

  Future<void> load() async {
    state = state.copyWith(
      isLoading: true,
      clearError: true,
      clearAction: true,
    );

    final RepositoryResult<List<VipLevel>> levelsResult = await ref.read(
      getVipLevelsUseCaseProvider,
    )();
    final RepositoryResult<VipSubscription> meResult = await ref.read(
      getMyVipUseCaseProvider,
    )();

    if (!ref.mounted) return;

    String? error;
    List<VipLevel> levels = state.levels;
    VipSubscription subscription = state.subscription;

    levelsResult.fold(
      onSuccess: (List<VipLevel> data) {
        levels = List<VipLevel>.unmodifiable(data);
      },
      onFailure: (failure) {
        error = failure.message;
      },
    );

    meResult.fold(
      onSuccess: (VipSubscription data) {
        subscription = data;
      },
      onFailure: (failure) {
        error ??= failure.message;
      },
    );

    String? selected = state.selectedLevelId;
    if (selected != null &&
        levels.every((VipLevel level) => level.id != selected)) {
      selected = null;
    }
    selected ??= levels.isEmpty ? null : levels.first.id;

    state = state.copyWith(
      levels: levels,
      subscription: subscription,
      selectedLevelId: selected,
      isLoading: false,
      errorMessage: error,
      clearError: error == null,
      clearSelection: selected == null,
    );
  }

  Future<void> refresh() => load();

  void selectLevel(String levelId) {
    if (state.selectedLevelId == levelId) return;
    state = state.copyWith(selectedLevelId: levelId, clearAction: true);
  }

  Future<bool> purchaseSelected({Map<String, dynamic>? metadata}) async {
    final VipLevel? level = state.selectedLevel;
    if (level == null) {
      state = state.copyWith(errorMessage: 'Select a VIP level to purchase.');
      return false;
    }
    return purchase(levelId: level.id, metadata: metadata);
  }

  Future<bool> purchase({
    required String levelId,
    Map<String, dynamic>? metadata,
  }) async {
    state = state.copyWith(
      isPurchasing: true,
      clearError: true,
      clearAction: true,
    );
    final RepositoryResult<VipSubscription> result = await ref.read(
      purchaseVipUseCaseProvider,
    )(vipLevelId: levelId, metadata: metadata);

    if (!ref.mounted) return false;

    return result.fold(
      onSuccess: (VipSubscription subscription) {
        state = state.copyWith(
          subscription: subscription,
          isPurchasing: false,
          actionMessage: 'VIP activated successfully.',
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(
          isPurchasing: false,
          errorMessage: failure.message,
        );
        return false;
      },
    );
  }

  Future<bool> upgradeSelected({Map<String, dynamic>? metadata}) async {
    final VipLevel? level = state.selectedLevel;
    if (level == null) {
      state = state.copyWith(errorMessage: 'Select a VIP level to upgrade.');
      return false;
    }
    return upgrade(levelId: level.id, metadata: metadata);
  }

  Future<bool> upgrade({
    required String levelId,
    Map<String, dynamic>? metadata,
  }) async {
    state = state.copyWith(
      isUpgrading: true,
      clearError: true,
      clearAction: true,
    );
    final RepositoryResult<VipSubscription> result = await ref.read(
      upgradeVipUseCaseProvider,
    )(vipLevelId: levelId, metadata: metadata);

    if (!ref.mounted) return false;

    return result.fold(
      onSuccess: (VipSubscription subscription) {
        state = state.copyWith(
          subscription: subscription,
          isUpgrading: false,
          actionMessage: 'VIP upgraded successfully.',
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(
          isUpgrading: false,
          errorMessage: failure.message,
        );
        return false;
      },
    );
  }
}
