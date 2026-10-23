import 'package:civin/features/vip/domain/entities/vip.dart';

abstract final class VipModel {
  static VipLevel levelFromJson(Map<String, dynamic> json) {
    final Object? privilegesRaw = json['privileges'];
    return VipLevel(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String? ?? 'VIP',
      level: _int(json['level']),
      coinPrice: _int(json['coin_price']),
      durationDays: _int(json['duration_days'], fallback: 30),
      status: json['status'] as String? ?? 'active',
      sortOrder: _int(json['sort_order']),
      privileges: privilegesRaw is Map<String, dynamic>
          ? privilegesFromJson(privilegesRaw)
          : const VipPrivileges(),
    );
  }

  static List<VipLevel> levelsFromJson(List<dynamic> items) => items
      .map((dynamic item) {
        if (item is! Map<String, dynamic>) {
          throw const FormatException('Invalid VIP level item.');
        }
        return levelFromJson(item);
      })
      .toList(growable: false);

  static VipPrivileges privilegesFromJson(Map<String, dynamic> json) =>
      VipPrivileges(
        badge: json['badge'] as String?,
        profileFrame: json['profile_frame'] as String?,
        chatEffect: json['chat_effect'] as String?,
        entranceAnimation: json['entrance_animation'] as String?,
        exclusiveGifts: json['exclusive_gifts'] == true,
      );

  static VipSubscription subscriptionFromJson(Map<String, dynamic> json) {
    final Object? levelRaw = json['level'];
    final Object? privilegesRaw = json['privileges'];
    final VipLevel? level = levelRaw is Map<String, dynamic>
        ? levelFromJson(levelRaw)
        : null;
    return VipSubscription(
      id: json['id']?.toString(),
      isVip: json['is_vip'] == true,
      status: _status(json['status'] as String?),
      startedAt: _date(json['started_at']),
      expiresAt: _date(json['expires_at']),
      level: level,
      privileges: privilegesRaw is Map<String, dynamic>
          ? privilegesFromJson(privilegesRaw)
          : level?.privileges,
    );
  }

  static VipStatus? _status(String? value) => switch (value?.toLowerCase()) {
    null => null,
    'active' => VipStatus.active,
    'expired' => VipStatus.expired,
    _ => VipStatus.unknown,
  };

  static DateTime? _date(Object? value) {
    if (value is DateTime) return value.toUtc();
    if (value is String && value.isNotEmpty) {
      return DateTime.tryParse(value)?.toUtc();
    }
    return null;
  }

  static int _int(Object? value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }
}
