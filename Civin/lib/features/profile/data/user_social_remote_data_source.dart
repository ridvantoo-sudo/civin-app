import 'package:civin/core/network/dio_client.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<UserSocialRemoteDataSource> userSocialRemoteDataSourceProvider =
    Provider<UserSocialRemoteDataSource>(
      (Ref ref) => DioUserSocialRemoteDataSource(ref.watch(dioClientProvider)),
    );

abstract interface class UserSocialRemoteDataSource {
  Future<UserProfile> getCurrentProfile();
  Future<UserProfile> updateProfile(ProfileUpdate update);
  Future<UserProfile> getUserProfile(String userId);
  Future<PagedResult<SocialUser>> getFollowers(
    String userId, {
    required int page,
    required int perPage,
  });
  Future<PagedResult<SocialUser>> getFollowing(
    String userId, {
    required int page,
    required int perPage,
  });
  Future<PagedResult<SocialUser>> searchUsers(
    String query, {
    required int page,
    required int perPage,
    bool? isOnline,
  });
  Future<PagedResult<SocialUser>> getBlockedUsers({
    required int page,
    required int perPage,
  });
  Future<FollowResult> follow(String userId);
  Future<void> unfollow(String userId);
  Future<void> block(String userId);
  Future<void> unblock(String userId);
  Future<List<String>> getReportCategories();
  Future<UserReport> reportUser(
    String userId, {
    required String category,
    String? details,
  });
}

final class DioUserSocialRemoteDataSource
    implements UserSocialRemoteDataSource {
  const DioUserSocialRemoteDataSource(this._client);

  static const String _api = '/api/v1';
  final DioClient _client;

  @override
  Future<UserProfile> getCurrentProfile() async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '$_api/profile',
    );
    return UserProfile.fromJson(_dataMap(response));
  }

  @override
  Future<UserProfile> updateProfile(ProfileUpdate update) async {
    final Response<dynamic> response = await _client.patch<dynamic>(
      '$_api/profile',
      data: update.toJson(),
    );
    return UserProfile.fromJson(_dataMap(response));
  }

  @override
  Future<UserProfile> getUserProfile(String userId) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '$_api/users/$userId/profile',
    );
    return UserProfile.fromJson(_dataMap(response));
  }

  @override
  Future<PagedResult<SocialUser>> getFollowers(
    String userId, {
    required int page,
    required int perPage,
  }) => _usersPage(
    '$_api/users/$userId/followers',
    page: page,
    perPage: perPage,
    nestedUser: true,
  );

  @override
  Future<PagedResult<SocialUser>> getFollowing(
    String userId, {
    required int page,
    required int perPage,
  }) => _usersPage(
    '$_api/users/$userId/following',
    page: page,
    perPage: perPage,
    nestedUser: true,
  );

  @override
  Future<PagedResult<SocialUser>> searchUsers(
    String query, {
    required int page,
    required int perPage,
    bool? isOnline,
  }) => _usersPage(
    '$_api/users/search',
    page: page,
    perPage: perPage,
    extraQuery: <String, dynamic>{
      'query': query.trim(),
      if (isOnline != null) 'is_online': isOnline ? 1 : 0,
    },
  );

  @override
  Future<PagedResult<SocialUser>> getBlockedUsers({
    required int page,
    required int perPage,
  }) => _usersPage(
    '$_api/blocks',
    page: page,
    perPage: perPage,
    nestedUser: true,
  );

  @override
  Future<FollowResult> follow(String userId) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '$_api/users/$userId/follow',
    );
    return FollowResult.fromJson(_dataMap(response));
  }

  @override
  Future<void> unfollow(String userId) async {
    await _client.delete<void>('$_api/users/$userId/follow');
  }

  @override
  Future<void> block(String userId) async {
    await _client.post<dynamic>('$_api/users/$userId/block');
  }

  @override
  Future<void> unblock(String userId) async {
    await _client.delete<void>('$_api/users/$userId/block');
  }

  @override
  Future<List<String>> getReportCategories() async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '$_api/report-categories',
    );
    final Object? data = _responseMap(response)['data'];
    if (data is! List<dynamic>) {
      throw const FormatException('Invalid report category response.');
    }
    return data
        .map((dynamic value) => value.toString())
        .toList(growable: false);
  }

  @override
  Future<UserReport> reportUser(
    String userId, {
    required String category,
    String? details,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '$_api/users/$userId/reports',
      data: <String, dynamic>{
        'category': category,
        if (details?.trim().isNotEmpty == true) 'details': details!.trim(),
      },
    );
    return UserReport.fromJson(_dataMap(response));
  }

  Future<PagedResult<SocialUser>> _usersPage(
    String path, {
    required int page,
    required int perPage,
    bool nestedUser = false,
    Map<String, dynamic> extraQuery = const <String, dynamic>{},
  }) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      path,
      queryParameters: <String, dynamic>{
        'page': page,
        'per_page': perPage,
        ...extraQuery,
      },
    );
    final Map<String, dynamic> body = _responseMap(response);
    final Object? rawData = body['data'];
    if (rawData is! List<dynamic>) {
      throw const FormatException('Invalid paginated response.');
    }
    final List<SocialUser> users = rawData
        .map((dynamic item) {
          if (item is! Map<String, dynamic>) {
            throw const FormatException('Invalid user item.');
          }
          final Object? rawUser = nestedUser ? item['user'] : item;
          if (rawUser is! Map<String, dynamic>) {
            throw const FormatException('Invalid user item.');
          }
          return SocialUser.fromJson(rawUser);
        })
        .toList(growable: false);
    final Map<String, dynamic> meta = body['meta'] is Map<String, dynamic>
        ? body['meta'] as Map<String, dynamic>
        : const <String, dynamic>{};
    return PagedResult<SocialUser>(
      items: users,
      currentPage: _int(meta['current_page'], fallback: page),
      lastPage: _int(meta['last_page'], fallback: page),
    );
  }

  Map<String, dynamic> _dataMap(Response<dynamic> response) {
    final Object? data = _responseMap(response)['data'];
    if (data is! Map<String, dynamic>) {
      throw const FormatException('Invalid API response.');
    }
    return data;
  }

  Map<String, dynamic> _responseMap(Response<dynamic> response) {
    final Object? body = response.data;
    if (body is! Map<String, dynamic>) {
      throw const FormatException('Invalid API response.');
    }
    return body;
  }

  int _int(Object? value, {required int fallback}) => switch (value) {
    final int number => number,
    final String text => int.tryParse(text) ?? fallback,
    _ => fallback,
  };
}
