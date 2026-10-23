import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/empty_widget.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:civin/features/profile/presentation/social_providers.dart';
import 'package:civin/features/profile/repository/user_social_repository_impl.dart';
import 'package:civin/features/profile/widgets/social_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class FollowersPage extends StatelessWidget {
  const FollowersPage({required this.userId, super.key, this.userName});

  final String userId;
  final String? userName;

  @override
  Widget build(BuildContext context) => _SocialListPage(
    title: userName == null ? 'Followers' : '$userName’s followers',
    request: SocialListRequest(SocialListKind.followers, userId: userId),
    emptyMessage: 'No followers yet.',
  );
}

final class FollowingPage extends StatelessWidget {
  const FollowingPage({required this.userId, super.key, this.userName});

  final String userId;
  final String? userName;

  @override
  Widget build(BuildContext context) => _SocialListPage(
    title: userName == null ? 'Following' : '$userName’s following',
    request: SocialListRequest(SocialListKind.following, userId: userId),
    emptyMessage: 'Not following anyone yet.',
  );
}

final class BlockedUsersPage extends StatelessWidget {
  const BlockedUsersPage({super.key});

  @override
  Widget build(BuildContext context) => const _SocialListPage(
    title: 'Blocked users',
    request: SocialListRequest(SocialListKind.blocked),
    emptyMessage: 'You have not blocked anyone.',
  );
}

final class _SocialListPage extends ConsumerStatefulWidget {
  const _SocialListPage({
    required this.title,
    required this.request,
    required this.emptyMessage,
  });

  final String title;
  final SocialListRequest request;
  final String emptyMessage;

  @override
  ConsumerState<_SocialListPage> createState() => _SocialListPageState();
}

final class _SocialListPageState extends ConsumerState<_SocialListPage> {
  final ScrollController _scrollController = ScrollController();
  bool _loadingMore = false;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_loadMore);
  }

  @override
  void dispose() {
    _scrollController
      ..removeListener(_loadMore)
      ..dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final provider = socialListProvider(widget.request);
    final AsyncValue<PagedResult<SocialUser>> state = ref.watch(provider);
    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: SocialPageWidth(
        child: state.when(
          loading: () => const AppLoadingWidget(),
          error: (Object error, StackTrace stackTrace) => AppErrorWidget(
            message: error.toString(),
            onRetry: () => ref.read(provider.notifier).refresh(),
          ),
          data: (PagedResult<SocialUser> page) => RefreshIndicator.adaptive(
            onRefresh: () => ref.read(provider.notifier).refresh(),
            child: page.items.isEmpty
                ? ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: <Widget>[
                      SizedBox(
                        height: MediaQuery.sizeOf(context).height * .6,
                        child: EmptyStateWidget(
                          message: widget.emptyMessage,
                          icon: widget.request.kind == SocialListKind.blocked
                              ? Icons.block_rounded
                              : Icons.people_outline_rounded,
                        ),
                      ),
                    ],
                  )
                : ListView.builder(
                    controller: _scrollController,
                    physics: const AlwaysScrollableScrollPhysics(),
                    itemCount: page.items.length + (page.hasMore ? 1 : 0),
                    itemBuilder: (BuildContext context, int index) {
                      if (index == page.items.length) {
                        return const Padding(
                          padding: EdgeInsets.all(20),
                          child: Center(
                            child: CircularProgressIndicator.adaptive(),
                          ),
                        );
                      }
                      final SocialUser user = page.items[index];
                      return SocialUserTile(
                        user: user,
                        onTap: widget.request.kind == SocialListKind.blocked
                            ? () {}
                            : () => context.push(
                                AppRoutes.userDetailsPath(user.id),
                              ),
                        trailing: widget.request.kind == SocialListKind.blocked
                            ? TextButton(
                                onPressed: () => _unblock(user),
                                child: const Text('Unblock'),
                              )
                            : null,
                      );
                    },
                  ),
          ),
        ),
      ),
    );
  }

  Future<void> _loadMore() async {
    if (_loadingMore ||
        !_scrollController.hasClients ||
        _scrollController.position.extentAfter >= 320) {
      return;
    }
    _loadingMore = true;
    await ref.read(socialListProvider(widget.request).notifier).loadMore();
    _loadingMore = false;
  }

  Future<void> _unblock(SocialUser user) async {
    final RepositoryResult<void> result = await ref
        .read(userSocialRepositoryProvider)
        .unblock(user.id);
    if (!mounted) return;
    result.fold(
      onSuccess: (_) {
        ref.read(socialListProvider(widget.request).notifier).refresh();
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('${user.displayName} unblocked.')),
        );
      },
      onFailure: (failure) => ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(failure.message))),
    );
  }
}
