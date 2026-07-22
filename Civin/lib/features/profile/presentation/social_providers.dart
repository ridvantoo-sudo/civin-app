import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:civin/features/profile/domain/repositories/user_social_repository.dart';
import 'package:civin/features/profile/repository/user_social_repository_impl.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final AsyncNotifierProvider<CurrentProfileController, UserProfile>
currentProfileProvider =
    AsyncNotifierProvider<CurrentProfileController, UserProfile>(
      CurrentProfileController.new,
    );

final userDetailsProvider =
    AsyncNotifierProvider.family<UserDetailsController, UserProfile, String>(
      UserDetailsController.new,
    );

final socialListProvider =
    AsyncNotifierProvider.family<
      SocialListController,
      PagedResult<SocialUser>,
      SocialListRequest
    >(SocialListController.new);

final AsyncNotifierProvider<SearchUsersController, PagedResult<SocialUser>>
searchUsersProvider =
    AsyncNotifierProvider<SearchUsersController, PagedResult<SocialUser>>(
      SearchUsersController.new,
    );

final FutureProvider<List<String>> reportCategoriesProvider =
    FutureProvider<List<String>>((Ref ref) async {
      final RepositoryResult<List<String>> result = await ref
          .watch(userSocialRepositoryProvider)
          .getReportCategories();
      return _unwrap(result);
    });

final class CurrentProfileController extends AsyncNotifier<UserProfile> {
  UserSocialRepository get _repository =>
      ref.read(userSocialRepositoryProvider);

  @override
  Future<UserProfile> build() async =>
      _unwrap(await _repository.getCurrentProfile());

  Future<bool> saveProfile(ProfileUpdate update) async {
    state = const AsyncLoading<UserProfile>();
    try {
      final UserProfile profile = _unwrap(
        await _repository.updateProfile(update),
      );
      state = AsyncData<UserProfile>(profile);
      return true;
    } on Object catch (error, stackTrace) {
      state = AsyncError<UserProfile>(error, stackTrace);
      return false;
    }
  }

  Future<void> refresh() async {
    state = const AsyncLoading<UserProfile>();
    state = await AsyncValue.guard(
      () async => _unwrap(await _repository.getCurrentProfile()),
    );
  }
}

final class UserDetailsController extends AsyncNotifier<UserProfile> {
  UserDetailsController(this.userId);

  final String userId;
  UserSocialRepository get _repository =>
      ref.read(userSocialRepositoryProvider);

  @override
  Future<UserProfile> build() async =>
      _unwrap(await _repository.getUserProfile(userId));

  Future<bool> toggleFollow() async {
    final UserProfile? profile = _data;
    if (profile == null) return false;
    try {
      if (profile.followStatus == FollowStatus.none) {
        final FollowResult follow = _unwrap(await _repository.follow(userId));
        state = AsyncData<UserProfile>(
          profile.copyWith(
            followStatus: follow.status,
            followersCount: follow.status == FollowStatus.accepted
                ? profile.followersCount + 1
                : profile.followersCount,
          ),
        );
      } else {
        _unwrap(await _repository.unfollow(userId));
        state = AsyncData<UserProfile>(
          profile.copyWith(
            followStatus: FollowStatus.none,
            followersCount: profile.isFollowing
                ? (profile.followersCount - 1).clamp(0, 1 << 31)
                : profile.followersCount,
          ),
        );
      }
      return true;
    } on Object catch (error, stackTrace) {
      state = AsyncError<UserProfile>(error, stackTrace);
      return false;
    }
  }

  Future<bool> block() async {
    final UserProfile? profile = _data;
    if (profile == null) return false;
    try {
      _unwrap(await _repository.block(userId));
      state = AsyncData<UserProfile>(
        profile.copyWith(isBlocked: true, followStatus: FollowStatus.none),
      );
      return true;
    } on Object catch (error, stackTrace) {
      state = AsyncError<UserProfile>(error, stackTrace);
      return false;
    }
  }

  Future<void> refresh() async {
    state = const AsyncLoading<UserProfile>();
    state = await AsyncValue.guard(
      () async => _unwrap(await _repository.getUserProfile(userId)),
    );
  }

  UserProfile? get _data => switch (state) {
    AsyncData<UserProfile>(:final value) => value,
    _ => null,
  };
}

enum SocialListKind { followers, following, blocked }

final class SocialListRequest {
  const SocialListRequest(this.kind, {this.userId});

  final SocialListKind kind;
  final String? userId;

  @override
  bool operator ==(Object other) =>
      other is SocialListRequest &&
      other.kind == kind &&
      other.userId == userId;

  @override
  int get hashCode => Object.hash(kind, userId);
}

final class SocialListController
    extends AsyncNotifier<PagedResult<SocialUser>> {
  SocialListController(this.request);

  static const int _pageSize = 20;
  final SocialListRequest request;
  UserSocialRepository get _repository =>
      ref.read(userSocialRepositoryProvider);

  @override
  Future<PagedResult<SocialUser>> build() => _load(1);

  Future<void> refresh() async {
    state = const AsyncLoading<PagedResult<SocialUser>>();
    state = await AsyncValue.guard(() => _load(1));
  }

  Future<void> loadMore() async {
    final PagedResult<SocialUser>? current = _data;
    if (current == null || !current.hasMore) return;
    final PagedResult<SocialUser> next = await _load(current.currentPage + 1);
    state = AsyncData<PagedResult<SocialUser>>(current.append(next));
  }

  Future<PagedResult<SocialUser>> _load(int page) async {
    final RepositoryResult<PagedResult<SocialUser>> result =
        await switch (request.kind) {
          SocialListKind.followers => _repository.getFollowers(
            request.userId!,
            page: page,
            perPage: _pageSize,
          ),
          SocialListKind.following => _repository.getFollowing(
            request.userId!,
            page: page,
            perPage: _pageSize,
          ),
          SocialListKind.blocked => _repository.getBlockedUsers(
            page: page,
            perPage: _pageSize,
          ),
        };
    return _unwrap(result);
  }

  PagedResult<SocialUser>? get _data => switch (state) {
    AsyncData<PagedResult<SocialUser>>(:final value) => value,
    _ => null,
  };
}

final class SearchUsersController
    extends AsyncNotifier<PagedResult<SocialUser>> {
  static const int _pageSize = 20;
  String _query = '';

  UserSocialRepository get _repository =>
      ref.read(userSocialRepositoryProvider);

  @override
  Future<PagedResult<SocialUser>> build() async =>
      PagedResult<SocialUser>.empty();

  Future<void> search(String query) async {
    _query = query.trim();
    if (_query.isEmpty) {
      state = AsyncData<PagedResult<SocialUser>>(
        PagedResult<SocialUser>.empty(),
      );
      return;
    }
    state = const AsyncLoading<PagedResult<SocialUser>>();
    state = await AsyncValue.guard(() => _load(1));
  }

  Future<void> loadMore() async {
    final PagedResult<SocialUser>? current = _data;
    if (current == null || !current.hasMore || _query.isEmpty) return;
    final PagedResult<SocialUser> next = await _load(current.currentPage + 1);
    state = AsyncData<PagedResult<SocialUser>>(current.append(next));
  }

  Future<PagedResult<SocialUser>> _load(int page) async => _unwrap(
    await _repository.searchUsers(_query, page: page, perPage: _pageSize),
  );

  PagedResult<SocialUser>? get _data => switch (state) {
    AsyncData<PagedResult<SocialUser>>(:final value) => value,
    _ => null,
  };
}

final class SocialRepositoryException implements Exception {
  const SocialRepositoryException(this.message);
  final String message;

  @override
  String toString() => message;
}

T _unwrap<T>(RepositoryResult<T> result) => result.fold(
  onSuccess: (T data) => data,
  onFailure: (failure) => throw SocialRepositoryException(failure.message),
);
