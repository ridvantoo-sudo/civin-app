enum RankingType { host, gifter, pk, voice }

enum RankingPeriod { daily, weekly, monthly }

enum RankingScope { global, country }

final class RankingUser {
  const RankingUser({
    required this.id,
    required this.nickname,
    this.username,
    this.avatarUrl,
    this.countryCode,
    this.countryName,
    this.isVip = false,
    this.level,
  });

  final String id;
  final String nickname;
  final String? username;
  final String? avatarUrl;
  final String? countryCode;
  final String? countryName;
  final bool isVip;
  final int? level;

  String get displayName {
    final String trimmed = nickname.trim();
    if (trimmed.isNotEmpty) return trimmed;
    final String? handle = username?.trim();
    if (handle != null && handle.isNotEmpty) return handle;
    return 'User';
  }
}

final class RankingEntry {
  const RankingEntry({
    required this.rank,
    required this.score,
    required this.user,
  });

  final int rank;
  final int score;
  final RankingUser user;
}

final class RankingQuery {
  const RankingQuery({
    required this.type,
    this.period = RankingPeriod.daily,
    this.scope = RankingScope.global,
    this.country,
    this.limit = 50,
  });

  final RankingType type;
  final RankingPeriod period;
  final RankingScope scope;
  final String? country;
  final int limit;

  String? get effectiveCountry =>
      scope == RankingScope.country ? country?.trim() : null;

  RankingQuery copyWith({
    RankingType? type,
    RankingPeriod? period,
    RankingScope? scope,
    String? country,
    int? limit,
    bool clearCountry = false,
  }) => RankingQuery(
    type: type ?? this.type,
    period: period ?? this.period,
    scope: scope ?? this.scope,
    country: clearCountry ? null : country ?? this.country,
    limit: limit ?? this.limit,
  );
}

final class RankingViewState {
  const RankingViewState({
    this.query = const RankingQuery(type: RankingType.host),
    this.entries = const <RankingEntry>[],
    this.isLoading = false,
    this.errorMessage,
  });

  final RankingQuery query;
  final List<RankingEntry> entries;
  final bool isLoading;
  final String? errorMessage;

  List<RankingEntry> get podium {
    if (entries.isEmpty) return const <RankingEntry>[];
    final List<RankingEntry> top = entries.take(3).toList(growable: false);
    return List<RankingEntry>.unmodifiable(top);
  }

  List<RankingEntry> get rest {
    if (entries.length <= 3) return const <RankingEntry>[];
    return List<RankingEntry>.unmodifiable(entries.skip(3));
  }

  RankingViewState copyWith({
    RankingQuery? query,
    List<RankingEntry>? entries,
    bool? isLoading,
    String? errorMessage,
    bool clearError = false,
  }) => RankingViewState(
    query: query ?? this.query,
    entries: entries ?? this.entries,
    isLoading: isLoading ?? this.isLoading,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
  );
}

extension RankingTypeX on RankingType {
  String get apiPath => switch (this) {
    RankingType.host => 'hosts',
    RankingType.gifter => 'gifters',
    RankingType.pk => 'pk',
    RankingType.voice => 'voice',
  };

  String get title => switch (this) {
    RankingType.host => 'Host Ranking',
    RankingType.gifter => 'Gifter Ranking',
    RankingType.pk => 'PK Ranking',
    RankingType.voice => 'Voice Ranking',
  };

  String get subtitle => switch (this) {
    RankingType.host => 'Top hosts by diamonds earned',
    RankingType.gifter => 'Top spenders by gifts sent',
    RankingType.pk => 'Top PK battle winners',
    RankingType.voice => 'Top voice room hosts',
  };
}

extension RankingPeriodX on RankingPeriod {
  String get apiValue => switch (this) {
    RankingPeriod.daily => 'DAILY',
    RankingPeriod.weekly => 'WEEKLY',
    RankingPeriod.monthly => 'MONTHLY',
  };

  String get label => switch (this) {
    RankingPeriod.daily => 'Daily',
    RankingPeriod.weekly => 'Weekly',
    RankingPeriod.monthly => 'Monthly',
  };
}
