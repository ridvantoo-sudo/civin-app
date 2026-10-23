enum GiftStatus { active, inactive, unknown }

final class GiftUser {
  const GiftUser({
    required this.id,
    required this.username,
    this.nickname,
    this.avatarUrl,
  });

  final String id;
  final String username;
  final String? nickname;
  final String? avatarUrl;

  String get displayName {
    final String? nick = nickname?.trim();
    if (nick != null && nick.isNotEmpty) return nick;
    return username;
  }
}

final class GiftCategory {
  const GiftCategory({
    required this.id,
    required this.name,
    this.icon,
    this.sortOrder = 0,
    this.status = GiftStatus.active,
  });

  final String id;
  final String name;
  final String? icon;
  final int sortOrder;
  final GiftStatus status;
}

final class GiftAnimationInfo {
  const GiftAnimationInfo({
    required this.giftId,
    required this.giftName,
    this.url,
    this.icon,
  });

  final String giftId;
  final String giftName;
  final String? url;
  final String? icon;

  bool get isLottie {
    final String? value = url?.toLowerCase();
    return value != null && value.endsWith('.json');
  }

  bool get isSvga {
    final String? value = url?.toLowerCase();
    return value != null && value.endsWith('.svga');
  }
}

final class Gift {
  const Gift({
    required this.id,
    required this.name,
    required this.coinPrice,
    this.icon,
    this.animationUrl,
    this.status = GiftStatus.active,
    this.animation,
    this.category,
  });

  final String id;
  final String name;
  final String? icon;
  final String? animationUrl;
  final int coinPrice;
  final GiftStatus status;
  final GiftAnimationInfo? animation;
  final GiftCategory? category;
}

final class GiftCatalog {
  const GiftCatalog({
    this.gifts = const <Gift>[],
    this.categories = const <GiftCategory>[],
  });

  final List<Gift> gifts;
  final List<GiftCategory> categories;

  List<Gift> giftsForCategory(String? categoryId) {
    if (categoryId == null || categoryId.isEmpty) return gifts;
    return gifts
        .where((Gift gift) => gift.category?.id == categoryId)
        .toList(growable: false);
  }
}

final class GiftSentEvent {
  const GiftSentEvent({
    required this.transactionId,
    required this.roomId,
    required this.sender,
    required this.gift,
    required this.quantity,
    required this.coins,
    required this.createdAt,
    this.receiver,
    this.animation,
  });

  final String transactionId;
  final String roomId;
  final GiftUser sender;
  final GiftUser? receiver;
  final Gift gift;
  final int quantity;
  final int coins;
  final GiftAnimationInfo? animation;
  final DateTime createdAt;
}

final class GiftTransaction {
  const GiftTransaction({
    required this.id,
    required this.roomId,
    required this.quantity,
    required this.coins,
    required this.createdAt,
    this.sender,
    this.receiver,
    this.gift,
    this.animation,
    this.metadata,
  });

  final String id;
  final String roomId;
  final int quantity;
  final int coins;
  final GiftUser? sender;
  final GiftUser? receiver;
  final Gift? gift;
  final GiftAnimationInfo? animation;
  final Map<String, dynamic>? metadata;
  final DateTime createdAt;
}

final class GiftRoomState {
  const GiftRoomState({
    this.roomId,
    this.history = const <GiftSentEvent>[],
    this.currentAnimation,
    this.isListening = false,
    this.panelOpen = false,
    this.selectedCategoryId,
    this.errorMessage,
  });

  final String? roomId;
  final List<GiftSentEvent> history;
  final GiftSentEvent? currentAnimation;
  final bool isListening;
  final bool panelOpen;
  final String? selectedCategoryId;
  final String? errorMessage;

  GiftRoomState copyWith({
    String? roomId,
    List<GiftSentEvent>? history,
    GiftSentEvent? currentAnimation,
    bool? isListening,
    bool? panelOpen,
    String? selectedCategoryId,
    String? errorMessage,
    bool clearAnimation = false,
    bool clearError = false,
    bool clearSelectedCategory = false,
  }) => GiftRoomState(
    roomId: roomId ?? this.roomId,
    history: history ?? this.history,
    currentAnimation: clearAnimation
        ? null
        : currentAnimation ?? this.currentAnimation,
    isListening: isListening ?? this.isListening,
    panelOpen: panelOpen ?? this.panelOpen,
    selectedCategoryId: clearSelectedCategory
        ? null
        : selectedCategoryId ?? this.selectedCategoryId,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
  );
}

final class GiftSendingState {
  const GiftSendingState({
    this.isSending = false,
    this.selectedGiftId,
    this.quantity = 1,
    this.errorMessage,
    this.lastSent,
  });

  final bool isSending;
  final String? selectedGiftId;
  final int quantity;
  final String? errorMessage;
  final GiftTransaction? lastSent;

  GiftSendingState copyWith({
    bool? isSending,
    String? selectedGiftId,
    int? quantity,
    String? errorMessage,
    GiftTransaction? lastSent,
    bool clearError = false,
    bool clearSelection = false,
  }) => GiftSendingState(
    isSending: isSending ?? this.isSending,
    selectedGiftId: clearSelection
        ? null
        : selectedGiftId ?? this.selectedGiftId,
    quantity: quantity ?? this.quantity,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
    lastSent: lastSent ?? this.lastSent,
  );
}
