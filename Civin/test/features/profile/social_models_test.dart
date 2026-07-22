import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('UserProfile', () {
    test('parses profile and relationship fields', () {
      final UserProfile profile = UserProfile.fromJson(<String, dynamic>{
        'id': 'profile-1',
        'user_id': 'user-1',
        'username': 'river',
        'nickname': 'River',
        'followers_count': 42,
        'following_count': '7',
        'is_online': true,
        'is_live': true,
        'follow_status': 'pending',
        'country': <String, dynamic>{
          'id': 'country-1',
          'name': 'Türkiye',
          'flag_emoji': '🇹🇷',
        },
      });

      expect(profile.id, 'user-1');
      expect(profile.displayName, 'River');
      expect(profile.followersCount, 42);
      expect(profile.followingCount, 7);
      expect(profile.isOnline, isTrue);
      expect(profile.isLive, isTrue);
      expect(profile.followStatus, FollowStatus.pending);
      expect(profile.country?.name, 'Türkiye');
    });

    test('falls back to username for an empty nickname', () {
      const SocialUser user = SocialUser(id: '1', username: 'person');

      expect(user.displayName, '@person');
    });
  });

  test('ProfileUpdate serializes API field names and date', () {
    final Map<String, dynamic> json = ProfileUpdate(
      nickname: ' River ',
      bio: ' Hello ',
      birthday: DateTime(2000, 1, 2),
      isPrivate: true,
    ).toJson();

    expect(json['nickname'], 'River');
    expect(json['bio'], 'Hello');
    expect(json['birthday'], '2000-01-02');
    expect(json['is_private'], isTrue);
  });

  test('PagedResult appends pages and reports availability', () {
    const PagedResult<int> first = PagedResult<int>(
      items: <int>[1, 2],
      currentPage: 1,
      lastPage: 2,
    );
    const PagedResult<int> second = PagedResult<int>(
      items: <int>[3],
      currentPage: 2,
      lastPage: 2,
    );

    final PagedResult<int> combined = first.append(second);

    expect(first.hasMore, isTrue);
    expect(combined.items, <int>[1, 2, 3]);
    expect(combined.hasMore, isFalse);
  });
}
