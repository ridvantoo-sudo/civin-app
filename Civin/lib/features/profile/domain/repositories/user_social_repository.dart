import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';

abstract interface class UserSocialRepository {
  Future<RepositoryResult<UserProfile>> getCurrentProfile();

  Future<RepositoryResult<UserProfile>> updateProfile(ProfileUpdate update);

  Future<RepositoryResult<UserProfile>> getUserProfile(String userId);

  Future<RepositoryResult<PagedResult<SocialUser>>> getFollowers(
    String userId, {
    int page = 1,
    int perPage = 20,
  });

  Future<RepositoryResult<PagedResult<SocialUser>>> getFollowing(
    String userId, {
    int page = 1,
    int perPage = 20,
  });

  Future<RepositoryResult<PagedResult<SocialUser>>> searchUsers(
    String query, {
    int page = 1,
    int perPage = 20,
    bool? isOnline,
  });

  Future<RepositoryResult<PagedResult<SocialUser>>> getBlockedUsers({
    int page = 1,
    int perPage = 20,
  });

  Future<RepositoryResult<FollowResult>> follow(String userId);

  Future<RepositoryResult<void>> unfollow(String userId);

  Future<RepositoryResult<void>> block(String userId);

  Future<RepositoryResult<void>> unblock(String userId);

  Future<RepositoryResult<List<String>>> getReportCategories();

  Future<RepositoryResult<UserReport>> reportUser(
    String userId, {
    required String category,
    String? details,
  });
}
