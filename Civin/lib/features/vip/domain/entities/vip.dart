enum VipStatus { active, expired, unknown }

final class VipPrivileges {
  const VipPrivileges({
    this.badge,
    this.profileFrame,
    this.chatEffect,
    this.entranceAnimation,
    this.exclusiveGifts = false,
  });

  final String? badge;
  final String? profileFrame;
  final String? chatEffect;
  final String? entranceAnimation;
  final bool exclusiveGifts;

  List<String> get benefitLabels {
    final List<String> items = <String>[];
    if (badge != null && badge!.trim().isNotEmpty) {
      items.add('VIP badge');
    }
    if (profileFrame != null && profileFrame!.trim().isNotEmpty) {
      items.add('Profile frame');
    }
    if (chatEffect != null && chatEffect!.trim().isNotEmpty) {
      items.add('Chat effect');
    }
    if (entranceAnimation != null && entranceAnimation!.trim().isNotEmpty) {
      items.add('Entrance animation');
    }
    if (exclusiveGifts) {
      items.add('Exclusive gifts');
    }
    return List<String>.unmodifiable(items);
  }
}

final class VipLevel {
  const VipLevel({
    required this.id,
    required this.name,
    required this.level,
    required this.coinPrice,
    required this.durationDays,
    required this.privileges,
    this.status = 'active',
    this.sortOrder = 0,
  });

  final String id;
  final String name;
  final int level;
  final int coinPrice;
  final int durationDays;
  final String status;
  final int sortOrder;
  final VipPrivileges privileges;

  String get priceLabel => '$coinPrice coins';

  String get durationLabel =>
      durationDays == 1 ? '1 day' : '$durationDays days';
}

final class VipSubscription {
  const VipSubscription({
    required this.isVip,
    this.id,
    this.status,
    this.startedAt,
    this.expiresAt,
    this.level,
    this.privileges,
  });

  final String? id;
  final bool isVip;
  final VipStatus? status;
  final DateTime? startedAt;
  final DateTime? expiresAt;
  final VipLevel? level;
  final VipPrivileges? privileges;

  bool get hasActiveLevel => isVip && level != null;

  String? get expirationLabel {
    final DateTime? expires = expiresAt;
    if (expires == null) return null;
    final DateTime local = expires.toLocal();
    final String y = local.year.toString().padLeft(4, '0');
    final String m = local.month.toString().padLeft(2, '0');
    final String d = local.day.toString().padLeft(2, '0');
    return '$y-$m-$d';
  }

  List<VipLevel> upgradeTargets(List<VipLevel> catalog) {
    final int current = level?.level ?? 0;
    return catalog
        .where((VipLevel item) => item.level > current)
        .toList(growable: false);
  }
}

final class VipViewState {
  const VipViewState({
    this.levels = const <VipLevel>[],
    this.subscription = const VipSubscription(isVip: false),
    this.selectedLevelId,
    this.isLoading = false,
    this.isPurchasing = false,
    this.isUpgrading = false,
    this.errorMessage,
    this.actionMessage,
  });

  final List<VipLevel> levels;
  final VipSubscription subscription;
  final String? selectedLevelId;
  final bool isLoading;
  final bool isPurchasing;
  final bool isUpgrading;
  final String? errorMessage;
  final String? actionMessage;

  bool get isBusy => isLoading || isPurchasing || isUpgrading;

  bool get isVip => subscription.isVip;

  VipLevel? get currentLevel => subscription.level;

  VipLevel? get selectedLevel {
    final String? id = selectedLevelId;
    if (id == null) return null;
    for (final VipLevel level in levels) {
      if (level.id == id) return level;
    }
    return null;
  }

  List<String> get currentBenefits {
    final VipPrivileges? privileges =
        subscription.privileges ?? subscription.level?.privileges;
    if (privileges == null) return const <String>[];
    return privileges.benefitLabels;
  }

  bool canUpgradeTo(VipLevel level) {
    if (!subscription.isVip) return false;
    final int current = subscription.level?.level ?? 0;
    return level.level > current;
  }

  bool canPurchase(VipLevel level) => !subscription.isVip;

  VipViewState copyWith({
    List<VipLevel>? levels,
    VipSubscription? subscription,
    String? selectedLevelId,
    bool? isLoading,
    bool? isPurchasing,
    bool? isUpgrading,
    String? errorMessage,
    String? actionMessage,
    bool clearError = false,
    bool clearAction = false,
    bool clearSelection = false,
  }) => VipViewState(
    levels: levels ?? this.levels,
    subscription: subscription ?? this.subscription,
    selectedLevelId: clearSelection
        ? null
        : selectedLevelId ?? this.selectedLevelId,
    isLoading: isLoading ?? this.isLoading,
    isPurchasing: isPurchasing ?? this.isPurchasing,
    isUpgrading: isUpgrading ?? this.isUpgrading,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
    actionMessage: clearAction ? null : actionMessage ?? this.actionMessage,
  );
}
