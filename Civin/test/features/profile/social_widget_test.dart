import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:civin/features/profile/domain/repositories/user_social_repository.dart';
import 'package:civin/features/profile/presentation/profile_page.dart';
import 'package:civin/features/profile/presentation/search_users_page.dart';
import 'package:civin/features/profile/repository/user_social_repository_impl.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('profile screen renders identity, status, and statistics', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(_app(const ProfilePage()));
    await tester.pumpAndSettle();

    expect(find.text('River'), findsOneWidget);
    expect(find.text('@river · Level 8'), findsOneWidget);
    expect(find.text('Followers'), findsOneWidget);
    expect(find.text('Following'), findsOneWidget);
    expect(find.text('LIVE'), findsOneWidget);
    expect(find.text('Edit'), findsOneWidget);
  });

  testWidgets('search screen debounces and renders matching users', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(_app(const SearchUsersPage()));
    await tester.pump();

    expect(find.text('Search by username or nickname.'), findsOneWidget);

    await tester.enterText(find.byType(TextFormField), 'ali');
    await tester.pump(const Duration(milliseconds: 400));
    await tester.pump();

    expect(find.text('Alice'), findsOneWidget);
    expect(find.text('@alice'), findsOneWidget);
  });
}

Widget _app(Widget child) => ProviderScope(
  overrides: [
    userSocialRepositoryProvider.overrideWithValue(
      const _FakeSocialRepository(),
    ),
  ],
  child: MaterialApp(home: child),
);

final class _FakeSocialRepository implements UserSocialRepository {
  const _FakeSocialRepository();

  static const UserProfile _profile = UserProfile(
    id: 'user-1',
    username: 'river',
    nickname: 'River',
    bio: 'Live creator',
    level: 8,
    followersCount: 1200,
    followingCount: 52,
    likesCount: 9000,
    isOnline: true,
    isLive: true,
  );

  @override
  Future<RepositoryResult<UserProfile>> getCurrentProfile() async =>
      const RepositorySuccess<UserProfile>(_profile);

  @override
  Future<RepositoryResult<UserProfile>> updateProfile(
    ProfileUpdate update,
  ) async => const RepositorySuccess<UserProfile>(_profile);

  @override
  Future<RepositoryResult<UserProfile>> getUserProfile(String userId) async =>
      const RepositorySuccess<UserProfile>(_profile);

  @override
  Future<RepositoryResult<PagedResult<SocialUser>>> searchUsers(
    String query, {
    int page = 1,
    int perPage = 20,
    bool? isOnline,
  }) async => const RepositorySuccess<PagedResult<SocialUser>>(
    PagedResult<SocialUser>(
      items: <SocialUser>[
        SocialUser(id: 'user-2', username: 'alice', nickname: 'Alice'),
      ],
      currentPage: 1,
      lastPage: 1,
    ),
  );

  @override
  Future<RepositoryResult<PagedResult<SocialUser>>> getFollowers(
    String userId, {
    int page = 1,
    int perPage = 20,
  }) async => RepositorySuccess<PagedResult<SocialUser>>(
    PagedResult<SocialUser>.empty(),
  );

  @override
  Future<RepositoryResult<PagedResult<SocialUser>>> getFollowing(
    String userId, {
    int page = 1,
    int perPage = 20,
  }) async => RepositorySuccess<PagedResult<SocialUser>>(
    PagedResult<SocialUser>.empty(),
  );

  @override
  Future<RepositoryResult<PagedResult<SocialUser>>> getBlockedUsers({
    int page = 1,
    int perPage = 20,
  }) async => RepositorySuccess<PagedResult<SocialUser>>(
    PagedResult<SocialUser>.empty(),
  );

  @override
  Future<RepositoryResult<FollowResult>> follow(String userId) async =>
      const RepositorySuccess<FollowResult>(
        FollowResult(id: 'follow-1', status: FollowStatus.accepted),
      );

  @override
  Future<RepositoryResult<void>> unfollow(String userId) async =>
      const RepositorySuccess<void>(null);

  @override
  Future<RepositoryResult<void>> block(String userId) async =>
      const RepositorySuccess<void>(null);

  @override
  Future<RepositoryResult<void>> unblock(String userId) async =>
      const RepositorySuccess<void>(null);

  @override
  Future<RepositoryResult<List<String>>> getReportCategories() async =>
      const RepositorySuccess<List<String>>(<String>['spam', 'other']);

  @override
  Future<RepositoryResult<UserReport>> reportUser(
    String userId, {
    required String category,
    String? details,
  }) async => RepositorySuccess<UserReport>(
    UserReport(id: 'report-1', category: category, status: 'pending'),
  );
}
