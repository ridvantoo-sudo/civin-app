import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/profile/data/user_social_remote_data_source.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:civin/features/profile/domain/repositories/user_social_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<UserSocialRepository> userSocialRepositoryProvider =
    Provider<UserSocialRepository>(
      (Ref ref) => UserSocialRepositoryImpl(
        ref.watch(userSocialRemoteDataSourceProvider),
      ),
    );

final class UserSocialRepositoryImpl extends BaseRepository
    implements UserSocialRepository {
  const UserSocialRepositoryImpl(this._remote);

  final UserSocialRemoteDataSource _remote;

  @override
  Future<RepositoryResult<UserProfile>> getCurrentProfile() =>
      execute(_remote.getCurrentProfile);

  @override
  Future<RepositoryResult<UserProfile>> updateProfile(ProfileUpdate update) =>
      execute(() => _remote.updateProfile(update));

  @override
  Future<RepositoryResult<UserProfile>> getUserProfile(String userId) =>
      execute(() => _remote.getUserProfile(userId));

  @override
  Future<RepositoryResult<PagedResult<SocialUser>>> getFollowers(
    String userId, {
    int page = 1,
    int perPage = 20,
  }) =>
      execute(() => _remote.getFollowers(userId, page: page, perPage: perPage));

  @override
  Future<RepositoryResult<PagedResult<SocialUser>>> getFollowing(
    String userId, {
    int page = 1,
    int perPage = 20,
  }) =>
      execute(() => _remote.getFollowing(userId, page: page, perPage: perPage));

  @override
  Future<RepositoryResult<PagedResult<SocialUser>>> searchUsers(
    String query, {
    int page = 1,
    int perPage = 20,
    bool? isOnline,
  }) => execute(
    () => _remote.searchUsers(
      query,
      page: page,
      perPage: perPage,
      isOnline: isOnline,
    ),
  );

  @override
  Future<RepositoryResult<PagedResult<SocialUser>>> getBlockedUsers({
    int page = 1,
    int perPage = 20,
  }) => execute(() => _remote.getBlockedUsers(page: page, perPage: perPage));

  @override
  Future<RepositoryResult<FollowResult>> follow(String userId) =>
      execute(() => _remote.follow(userId));

  @override
  Future<RepositoryResult<void>> unfollow(String userId) =>
      execute(() => _remote.unfollow(userId));

  @override
  Future<RepositoryResult<void>> block(String userId) =>
      execute(() => _remote.block(userId));

  @override
  Future<RepositoryResult<void>> unblock(String userId) =>
      execute(() => _remote.unblock(userId));

  @override
  Future<RepositoryResult<List<String>>> getReportCategories() =>
      execute(_remote.getReportCategories);

  @override
  Future<RepositoryResult<UserReport>> reportUser(
    String userId, {
    required String category,
    String? details,
  }) => execute(
    () => _remote.reportUser(userId, category: category, details: details),
  );
}
