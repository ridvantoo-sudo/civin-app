import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/profile/data/user_social_remote_data_source.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:civin/features/profile/repository/user_social_repository_impl.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late _FakeRemoteDataSource remote;
  late UserSocialRepositoryImpl repository;

  setUp(() {
    remote = _FakeRemoteDataSource();
    repository = UserSocialRepositoryImpl(remote);
  });

  test('returns profile data from the remote source', () async {
    final RepositoryResult<UserProfile> result = await repository
        .getCurrentProfile();

    expect(result, isA<RepositorySuccess<UserProfile>>());
    expect((result as RepositorySuccess<UserProfile>).data.username, 'river');
  });

  test('forwards pagination parameters to search', () async {
    await repository.searchUsers('person', page: 3, perPage: 40);

    expect(remote.searchQuery, 'person');
    expect(remote.searchPage, 3);
    expect(remote.searchPerPage, 40);
  });

  test('maps Dio errors to a network failure', () async {
    remote.error = DioException(
      requestOptions: RequestOptions(path: '/api/v1/profile'),
      response: Response<dynamic>(
        requestOptions: RequestOptions(path: '/api/v1/profile'),
        statusCode: 403,
        data: <String, dynamic>{'message': 'Profile unavailable.'},
      ),
    );

    final RepositoryResult<UserProfile> result = await repository
        .getCurrentProfile();

    expect(result, isA<RepositoryFailure<UserProfile>>());
    final AppFailure failure =
        (result as RepositoryFailure<UserProfile>).failure;
    expect(failure, isA<NetworkFailure>());
    expect(failure.message, 'Profile unavailable.');
  });
}

final class _FakeRemoteDataSource implements UserSocialRemoteDataSource {
  Object? error;
  String? searchQuery;
  int? searchPage;
  int? searchPerPage;

  UserProfile get _profile =>
      const UserProfile(id: 'user-1', username: 'river', nickname: 'River');

  void _throwIfNeeded() {
    final Object? value = error;
    if (value is DioException) throw value;
    if (value is Exception) throw value;
  }

  @override
  Future<UserProfile> getCurrentProfile() async {
    _throwIfNeeded();
    return _profile;
  }

  @override
  Future<UserProfile> updateProfile(ProfileUpdate update) async => _profile;

  @override
  Future<UserProfile> getUserProfile(String userId) async => _profile;

  @override
  Future<PagedResult<SocialUser>> getFollowers(
    String userId, {
    required int page,
    required int perPage,
  }) async => PagedResult<SocialUser>.empty();

  @override
  Future<PagedResult<SocialUser>> getFollowing(
    String userId, {
    required int page,
    required int perPage,
  }) async => PagedResult<SocialUser>.empty();

  @override
  Future<PagedResult<SocialUser>> searchUsers(
    String query, {
    required int page,
    required int perPage,
    bool? isOnline,
  }) async {
    searchQuery = query;
    searchPage = page;
    searchPerPage = perPage;
    return PagedResult<SocialUser>.empty();
  }

  @override
  Future<PagedResult<SocialUser>> getBlockedUsers({
    required int page,
    required int perPage,
  }) async => PagedResult<SocialUser>.empty();

  @override
  Future<FollowResult> follow(String userId) async =>
      const FollowResult(id: 'follow-1', status: FollowStatus.accepted);

  @override
  Future<void> unfollow(String userId) async {}

  @override
  Future<void> block(String userId) async {}

  @override
  Future<void> unblock(String userId) async {}

  @override
  Future<List<String>> getReportCategories() async => <String>['spam'];

  @override
  Future<UserReport> reportUser(
    String userId, {
    required String category,
    String? details,
  }) async => UserReport(id: 'report-1', category: category, status: 'pending');
}
