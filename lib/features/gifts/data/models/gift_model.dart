import 'package:civin/features/gifts/domain/entities/gift.dart';

abstract final class GiftModel {
  static GiftCatalog catalogFromJson(List<dynamic> items) {
    final List<Gift> gifts = items
        .map((dynamic item) {
          if (item is! Map<String, dynamic>) {
            throw const FormatException('Invalid gift catalog item.');
          }
          return fromJson(item);
        })
        .toList(growable: false);

    final Map<String, GiftCategory> categories = <String, GiftCategory>{};
    for (final Gift gift in gifts) {
      final GiftCategory? category = gift.category;
      if (category != null) {
        categories[category.id] = category;
      }
    }

    final List<GiftCategory> sorted = categories.values.toList(growable: false)
      ..sort(
        (GiftCategory a, GiftCategory b) => a.sortOrder.compareTo(b.sortOrder),
      );

    return GiftCatalog(gifts: gifts, categories: sorted);
  }

  static Gift fromJson(Map<String, dynamic> json) {
    final Object? categoryJson = json['category'];
    final Object? animationJson = json['animation'];
    return Gift(
      id: json['id'] as String,
      name: json['name'] as String? ?? 'Gift',
      icon: json['icon'] as String?,
      animationUrl: json['animation_url'] as String?,
      coinPrice: _int(json['coin_price']),
      status: _status(json['status'] as String?),
      animation: animationJson is Map<String, dynamic>
          ? animationFromJson(animationJson)
          : null,
      category: categoryJson is Map<String, dynamic>
          ? categoryFromJson(categoryJson)
          : null,
    );
  }

  static GiftCategory categoryFromJson(Map<String, dynamic> json) =>
      GiftCategory(
        id: json['id'] as String,
        name: json['name'] as String? ?? 'Category',
        icon: json['icon'] as String?,
        sortOrder: _int(json['sort_order']),
        status: _status(json['status'] as String?),
      );

  static GiftAnimationInfo animationFromJson(Map<String, dynamic> json) =>
      GiftAnimationInfo(
        giftId: json['gift_id'] as String? ?? '',
        giftName: json['gift_name'] as String? ?? 'Gift',
        url: json['url'] as String?,
        icon: json['icon'] as String?,
      );

  static GiftUser userFromJson(Map<String, dynamic> json) => GiftUser(
    id: json['id']?.toString() ?? json['user_id']?.toString() ?? '',
    username: json['username'] as String? ?? 'user',
    nickname: json['nickname'] as String?,
    avatarUrl: json['avatar_url'] as String?,
  );

  static GiftSentEvent eventFromJson(Map<String, dynamic> json) {
    final Object? giftJson = json['gift'];
    final Object? senderJson = json['sender'];
    final Object? receiverJson = json['receiver'];
    final Object? animationJson = json['animation'];

    if (giftJson is! Map<String, dynamic>) {
      throw const FormatException('Invalid gift.sent payload: missing gift.');
    }
    if (senderJson is! Map<String, dynamic>) {
      throw const FormatException('Invalid gift.sent payload: missing sender.');
    }

    final Gift gift = fromJson(giftJson);
    return GiftSentEvent(
      transactionId: json['transaction_id'] as String? ?? '',
      roomId: json['room_id'] as String? ?? '',
      sender: userFromJson(senderJson),
      receiver: receiverJson is Map<String, dynamic>
          ? userFromJson(receiverJson)
          : null,
      gift: gift,
      quantity: _int(json['quantity'], fallback: 1),
      coins: _int(json['coins']),
      animation: animationJson is Map<String, dynamic>
          ? animationFromJson(animationJson)
          : gift.animation,
      createdAt:
          DateTime.tryParse(json['created_at'] as String? ?? '') ??
          DateTime.now().toUtc(),
    );
  }

  static GiftTransaction transactionFromJson(Map<String, dynamic> json) {
    final Object? giftJson = json['gift'];
    final Object? senderJson = json['sender'];
    final Object? receiverJson = json['receiver'];
    final Object? animationJson = json['animation'];
    return GiftTransaction(
      id: json['id'] as String,
      roomId: json['room_id'] as String? ?? '',
      quantity: _int(json['quantity'], fallback: 1),
      coins: _int(json['coins']),
      sender: senderJson is Map<String, dynamic>
          ? userFromJson(senderJson)
          : null,
      receiver: receiverJson is Map<String, dynamic>
          ? userFromJson(receiverJson)
          : null,
      gift: giftJson is Map<String, dynamic> ? fromJson(giftJson) : null,
      animation: animationJson is Map<String, dynamic>
          ? animationFromJson(animationJson)
          : null,
      metadata: json['metadata'] is Map<String, dynamic>
          ? Map<String, dynamic>.from(json['metadata'] as Map)
          : null,
      createdAt:
          DateTime.tryParse(json['created_at'] as String? ?? '') ??
          DateTime.now().toUtc(),
    );
  }

  static GiftStatus _status(String? value) => switch (value?.toLowerCase()) {
    'active' => GiftStatus.active,
    'inactive' => GiftStatus.inactive,
    _ => GiftStatus.unknown,
  };

  static int _int(Object? value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }
}
