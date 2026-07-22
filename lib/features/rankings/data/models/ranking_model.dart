import 'package:civin/features/rankings/domain/entities/ranking.dart';

abstract final class RankingModel {
  static RankingEntry entryFromJson(Map<String, dynamic> json) {
    final Object? userRaw = json['user'];
    if (userRaw is! Map<String, dynamic>) {
      throw const FormatException('Invalid ranking entry user payload.');
    }
    return RankingEntry(
      rank: _int(json['rank'], fallback: 0),
      score: _int(json['score'], fallback: 0),
      user: userFromJson(userRaw),
    );
  }

  static List<RankingEntry> entriesFromJson(List<dynamic> items) => items
      .map((dynamic item) {
        if (item is! Map<String, dynamic>) {
          throw const FormatException('Invalid ranking entry item.');
        }
        return entryFromJson(item);
      })
      .toList(growable: false);

  static RankingUser userFromJson(Map<String, dynamic> json) {
    final _CountryInfo country = _parseCountry(json['country']);
    return RankingUser(
      id: json['id']?.toString() ?? '',
      nickname: json['nickname'] as String? ?? '',
      username: json['username'] as String?,
      avatarUrl: json['avatar_url'] as String?,
      countryCode: country.code,
      countryName: country.name,
      isVip: json['is_vip'] == true,
      level: json['level'] == null ? null : _int(json['level']),
    );
  }

  static _CountryInfo _parseCountry(Object? value) {
    if (value is String && value.trim().isNotEmpty) {
      final String trimmed = value.trim();
      return _CountryInfo(code: trimmed, name: trimmed);
    }
    if (value is Map<String, dynamic>) {
      final String? alpha2 = value['alpha2']?.toString();
      final String? alpha3 = value['alpha3']?.toString();
      final String? name = value['name']?.toString();
      return _CountryInfo(
        code: (alpha2?.isNotEmpty ?? false)
            ? alpha2
            : (alpha3?.isNotEmpty ?? false)
            ? alpha3
            : null,
        name: name,
      );
    }
    return const _CountryInfo();
  }

  static int _int(Object? value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }
}

final class _CountryInfo {
  const _CountryInfo({this.code, this.name});

  final String? code;
  final String? name;
}
