/// Small JSON readers that keep `dynamic` from leaking into feature code.
abstract final class JsonValues {
  static int asInt(Object? value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    if (value is String) return int.tryParse(value) ?? fallback;
    return fallback;
  }

  static bool asBool(Object? value, {bool fallback = false}) {
    if (value is bool) return value;
    if (value is num) return value != 0;
    if (value is String) {
      final String normalized = value.toLowerCase();
      if (normalized == 'true' || normalized == '1') return true;
      if (normalized == 'false' || normalized == '0') return false;
    }
    return fallback;
  }

  static String asString(Object? value, {String fallback = ''}) {
    if (value is String) return value;
    if (value is num || value is bool) return value.toString();
    return fallback;
  }

  static String? asNullableString(Object? value) {
    if (value == null) return null;
    final String text = asString(value);
    return text.isEmpty ? null : text;
  }

  static DateTime? asDate(Object? value) {
    final String? text = asNullableString(value);
    if (text == null) return null;
    return DateTime.tryParse(text);
  }

  static Map<String, Object?> asMap(Object? value) {
    if (value is Map<String, Object?>) return value;
    if (value is Map<String, dynamic>) {
      return Map<String, Object?>.from(value);
    }
    if (value is! Map<dynamic, dynamic>) return const <String, Object?>{};
    final Map<String, Object?> result = <String, Object?>{};
    for (final MapEntry<dynamic, dynamic> entry in value.entries) {
      final Object? key = entry.key;
      if (key is String) result[key] = entry.value;
    }
    return result;
  }

  static Map<String, dynamic> asStringDynamicMap(Object? value) {
    final Map<String, Object?> source = asMap(value);
    return Map<String, dynamic>.from(source);
  }

  static List<Map<String, dynamic>> asMapList(Object? value) {
    if (value == null) return const <Map<String, dynamic>>[];
    if (value is! List<dynamic>) {
      throw const FormatException('Expected a JSON list.');
    }
    return value
        .map((dynamic item) {
          if (item is Map<String, dynamic>) return item;
          if (item is Map) return asStringDynamicMap(item);
          throw const FormatException('Expected a JSON object.');
        })
        .toList(growable: false);
  }

  static List<String> asStringList(Object? value) {
    if (value is! List<dynamic>) return const <String>[];
    final List<String> items = <String>[];
    for (final dynamic item in value) {
      if (item is String) {
        items.add(item);
      } else if (item is num || item is bool) {
        items.add(item.toString());
      }
    }
    return List<String>.unmodifiable(items);
  }

  static List<int> asIntList(Object? value) {
    if (value is! List<dynamic>) return const <int>[];
    return List<int>.unmodifiable(
      value.map((dynamic item) => asInt(item)).toList(growable: false),
    );
  }
}
