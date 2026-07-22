enum FollowStatus {
  none,
  pending,
  accepted;

  static FollowStatus fromJson(Object? value) => switch (value) {
    'pending' => FollowStatus.pending,
    'accepted' => FollowStatus.accepted,
    _ => FollowStatus.none,
  };
}

final class Country {
  const Country({
    required this.id,
    required this.name,
    this.alpha2,
    this.flagEmoji,
  });

  factory Country.fromJson(Map<String, dynamic> json) => Country(
    id: json['id']?.toString() ?? '',
    name: json['name']?.toString() ?? '',
    alpha2: json['alpha2']?.toString(),
    flagEmoji: json['flag_emoji']?.toString(),
  );

  final String id;
  final String name;
  final String? alpha2;
  final String? flagEmoji;
}

class SocialUser {
  const SocialUser({
    required this.id,
    required this.username,
    this.nickname,
    this.avatarUrl,
    this.coverImageUrl,
    this.bio,
    this.country,
    this.level = 1,
    this.isVip = false,
    this.isPrivate = false,
    this.followersCount = 0,
    this.followingCount = 0,
    this.likesCount = 0,
    this.isOnline = false,
    this.isLive = false,
  });

  factory SocialUser.fromJson(Map<String, dynamic> json) => SocialUser(
    id: json['id']?.toString() ?? json['user_id']?.toString() ?? '',
    username: json['username']?.toString() ?? '',
    nickname: json['nickname']?.toString(),
    avatarUrl: json['avatar_url']?.toString(),
    coverImageUrl: json['cover_image_url']?.toString(),
    bio: json['bio']?.toString(),
    country: _country(json['country']),
    level: _integer(json['level'], fallback: 1),
    isVip: json['is_vip'] == true,
    isPrivate: json['is_private'] == true,
    followersCount: _integer(json['followers_count']),
    followingCount: _integer(json['following_count']),
    likesCount: _integer(json['likes_count']),
    isOnline: json['is_online'] == true,
    isLive: json['is_live'] == true,
  );

  final String id;
  final String username;
  final String? nickname;
  final String? avatarUrl;
  final String? coverImageUrl;
  final String? bio;
  final Country? country;
  final int level;
  final bool isVip;
  final bool isPrivate;
  final int followersCount;
  final int followingCount;
  final int likesCount;
  final bool isOnline;
  final bool isLive;

  String get displayName =>
      nickname?.trim().isNotEmpty == true ? nickname!.trim() : '@$username';
}

final class UserProfile extends SocialUser {
  const UserProfile({
    required super.id,
    required super.username,
    this.profileId,
    this.birthday,
    this.gender,
    this.followStatus = FollowStatus.none,
    this.isBlocked = false,
    super.nickname,
    super.avatarUrl,
    super.coverImageUrl,
    super.bio,
    super.country,
    super.level,
    super.isVip,
    super.isPrivate,
    super.followersCount,
    super.followingCount,
    super.likesCount,
    super.isOnline,
    super.isLive,
  });

  factory UserProfile.fromJson(Map<String, dynamic> json) => UserProfile(
    profileId: json['id']?.toString(),
    id: json['user_id']?.toString() ?? json['id']?.toString() ?? '',
    username: json['username']?.toString() ?? '',
    nickname: json['nickname']?.toString() ?? json['display_name']?.toString(),
    avatarUrl: json['avatar_url']?.toString(),
    coverImageUrl: json['cover_image_url']?.toString(),
    bio: json['bio']?.toString(),
    birthday: DateTime.tryParse(
      json['birthday']?.toString() ?? json['birth_date']?.toString() ?? '',
    ),
    gender: json['gender']?.toString(),
    country: _country(json['country']),
    level: _integer(json['level'], fallback: 1),
    isVip: json['is_vip'] == true,
    isPrivate: json['is_private'] == true,
    followersCount: _integer(json['followers_count']),
    followingCount: _integer(json['following_count']),
    likesCount: _integer(json['likes_count']),
    isOnline: json['is_online'] == true,
    isLive: json['is_live'] == true,
    followStatus: FollowStatus.fromJson(json['follow_status']),
    isBlocked: json['is_blocked'] == true,
  );

  final String? profileId;
  final DateTime? birthday;
  final String? gender;
  final FollowStatus followStatus;
  final bool isBlocked;

  bool get isFollowing => followStatus == FollowStatus.accepted;
  bool get isFollowPending => followStatus == FollowStatus.pending;

  UserProfile copyWith({
    FollowStatus? followStatus,
    bool? isBlocked,
    int? followersCount,
  }) => UserProfile(
    profileId: profileId,
    id: id,
    username: username,
    nickname: nickname,
    avatarUrl: avatarUrl,
    coverImageUrl: coverImageUrl,
    bio: bio,
    birthday: birthday,
    gender: gender,
    country: country,
    level: level,
    isVip: isVip,
    isPrivate: isPrivate,
    followersCount: followersCount ?? this.followersCount,
    followingCount: followingCount,
    likesCount: likesCount,
    isOnline: isOnline,
    isLive: isLive,
    followStatus: followStatus ?? this.followStatus,
    isBlocked: isBlocked ?? this.isBlocked,
  );
}

final class ProfileUpdate {
  const ProfileUpdate({
    required this.nickname,
    this.bio,
    this.avatarUrl,
    this.coverImageUrl,
    this.countryId,
    this.gender,
    this.birthday,
    this.isPrivate = false,
  });

  final String nickname;
  final String? bio;
  final String? avatarUrl;
  final String? coverImageUrl;
  final String? countryId;
  final String? gender;
  final DateTime? birthday;
  final bool isPrivate;

  Map<String, dynamic> toJson() => <String, dynamic>{
    'nickname': nickname.trim(),
    'bio': _nullableText(bio),
    'avatar_url': _nullableText(avatarUrl),
    'cover_image_url': _nullableText(coverImageUrl),
    'country_id': _nullableText(countryId),
    'gender': _nullableText(gender),
    'birthday': birthday == null
        ? null
        : '${birthday!.year.toString().padLeft(4, '0')}-'
              '${birthday!.month.toString().padLeft(2, '0')}-'
              '${birthday!.day.toString().padLeft(2, '0')}',
    'is_private': isPrivate,
  };
}

final class PagedResult<T> {
  const PagedResult({
    required this.items,
    required this.currentPage,
    required this.lastPage,
  });

  PagedResult.empty() : items = <T>[], currentPage = 1, lastPage = 1;

  final List<T> items;
  final int currentPage;
  final int lastPage;

  bool get hasMore => currentPage < lastPage;

  PagedResult<T> append(PagedResult<T> next) => PagedResult<T>(
    items: <T>[...items, ...next.items],
    currentPage: next.currentPage,
    lastPage: next.lastPage,
  );
}

final class FollowResult {
  const FollowResult({required this.id, required this.status});

  factory FollowResult.fromJson(Map<String, dynamic> json) => FollowResult(
    id: json['id']?.toString() ?? '',
    status: FollowStatus.fromJson(json['status']),
  );

  final String id;
  final FollowStatus status;
}

final class UserReport {
  const UserReport({
    required this.id,
    required this.category,
    required this.status,
  });

  factory UserReport.fromJson(Map<String, dynamic> json) => UserReport(
    id: json['id']?.toString() ?? '',
    category: json['category']?.toString() ?? '',
    status: json['status']?.toString() ?? 'pending',
  );

  final String id;
  final String category;
  final String status;
}

Country? _country(Object? value) =>
    value is Map<String, dynamic> ? Country.fromJson(value) : null;

int _integer(Object? value, {int fallback = 0}) => switch (value) {
  final int number => number,
  final String text => int.tryParse(text) ?? fallback,
  _ => fallback,
};

String? _nullableText(String? value) =>
    value?.trim().isEmpty == true ? null : value?.trim();
