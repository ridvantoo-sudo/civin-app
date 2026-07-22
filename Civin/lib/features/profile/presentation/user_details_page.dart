import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/app_network_image.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:civin/features/profile/presentation/social_providers.dart';
import 'package:civin/features/profile/widgets/social_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class UserDetailsPage extends ConsumerWidget {
  const UserDetailsPage({required this.userId, super.key});

  final String userId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final provider = userDetailsProvider(userId);
    final AsyncValue<UserProfile> state = ref.watch(provider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('User profile'),
        actions: <Widget>[
          PopupMenuButton<_UserAction>(
            onSelected: (_UserAction action) {
              final UserProfile? profile = switch (state) {
                AsyncData<UserProfile>(:final value) => value,
                _ => null,
              };
              if (profile == null) return;
              switch (action) {
                case _UserAction.report:
                  context.push(
                    AppRoutes.reportUserPath(userId),
                    extra: profile.displayName,
                  );
                case _UserAction.block:
                  _confirmBlock(context, ref, profile);
              }
            },
            itemBuilder: (BuildContext context) =>
                const <PopupMenuEntry<_UserAction>>[
                  PopupMenuItem<_UserAction>(
                    value: _UserAction.report,
                    child: ListTile(
                      leading: Icon(Icons.flag_outlined),
                      title: Text('Report'),
                      contentPadding: EdgeInsets.zero,
                    ),
                  ),
                  PopupMenuItem<_UserAction>(
                    value: _UserAction.block,
                    child: ListTile(
                      leading: Icon(Icons.block_rounded),
                      title: Text('Block'),
                      contentPadding: EdgeInsets.zero,
                    ),
                  ),
                ],
          ),
        ],
      ),
      body: SocialPageWidth(
        child: state.when(
          loading: () => const AppLoadingWidget(message: 'Loading profile'),
          error: (Object error, StackTrace stackTrace) => AppErrorWidget(
            message: error.toString(),
            onRetry: () => ref.read(provider.notifier).refresh(),
          ),
          data: (UserProfile profile) => RefreshIndicator.adaptive(
            onRefresh: () => ref.read(provider.notifier).refresh(),
            child: ListView(
              physics: const AlwaysScrollableScrollPhysics(),
              children: <Widget>[
                SizedBox(
                  height: 190,
                  child: Stack(
                    fit: StackFit.expand,
                    clipBehavior: Clip.none,
                    children: <Widget>[
                      _DetailsCover(url: profile.coverImageUrl),
                      Positioned(
                        left: 24,
                        bottom: -42,
                        child: SocialAvatar(
                          user: profile,
                          radius: 52,
                          heroTag: 'avatar-${profile.id}',
                        ),
                      ),
                      if (profile.isLive)
                        const Positioned(
                          right: 16,
                          bottom: 12,
                          child: LiveBadge(),
                        ),
                    ],
                  ),
                ),
                const SizedBox(height: 52),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 20),
                  child: Row(
                    children: <Widget>[
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: <Widget>[
                            Row(
                              children: <Widget>[
                                Flexible(
                                  child: Text(
                                    profile.displayName,
                                    style: Theme.of(context)
                                        .textTheme
                                        .headlineSmall
                                        ?.copyWith(fontWeight: FontWeight.w700),
                                  ),
                                ),
                                if (profile.isVip)
                                  Padding(
                                    padding: const EdgeInsets.only(left: 6),
                                    child: Icon(
                                      Icons.verified_rounded,
                                      color: Theme.of(
                                        context,
                                      ).colorScheme.primary,
                                    ),
                                  ),
                              ],
                            ),
                            Text(
                              '@${profile.username} · Level ${profile.level}',
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(width: 12),
                      _FollowButton(
                        status: profile.followStatus,
                        onPressed: () => _toggleFollow(context, ref),
                      ),
                    ],
                  ),
                ),
                if (profile.bio?.trim().isNotEmpty == true)
                  Padding(
                    padding: const EdgeInsets.fromLTRB(20, 18, 20, 0),
                    child: Text(profile.bio!),
                  ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                  children: <Widget>[
                    ProfileStat(
                      value: profile.followersCount,
                      label: 'Followers',
                      onTap: () => context.push(
                        AppRoutes.followersPath(profile.id),
                        extra: profile.displayName,
                      ),
                    ),
                    ProfileStat(
                      value: profile.followingCount,
                      label: 'Following',
                      onTap: () => context.push(
                        AppRoutes.followingPath(profile.id),
                        extra: profile.displayName,
                      ),
                    ),
                    ProfileStat(value: profile.likesCount, label: 'Likes'),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _toggleFollow(BuildContext context, WidgetRef ref) async {
    final provider = userDetailsProvider(userId);
    final bool success = await ref.read(provider.notifier).toggleFollow();
    if (!context.mounted || success) return;
    final Object? error = ref.read(provider).error;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(error?.toString() ?? 'Could not update follow.')),
    );
    await ref.read(provider.notifier).refresh();
  }

  Future<void> _confirmBlock(
    BuildContext context,
    WidgetRef ref,
    UserProfile profile,
  ) async {
    final bool? confirmed = await showDialog<bool>(
      context: context,
      builder: (BuildContext context) => AlertDialog(
        title: Text('Block ${profile.displayName}?'),
        content: const Text(
          'You will no longer be able to follow or view each other.',
        ),
        actions: <Widget>[
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Block'),
          ),
        ],
      ),
    );
    if (confirmed != true || !context.mounted) return;
    final provider = userDetailsProvider(userId);
    final bool success = await ref.read(provider.notifier).block();
    if (!context.mounted) return;
    if (success) {
      context.pop();
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('${profile.displayName} blocked.')),
      );
      return;
    }
    final Object? error = ref.read(provider).error;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(error?.toString() ?? 'Could not block user.')),
    );
  }
}

enum _UserAction { report, block }

final class _FollowButton extends StatelessWidget {
  const _FollowButton({required this.status, required this.onPressed});

  final FollowStatus status;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) {
    final String label = switch (status) {
      FollowStatus.none => 'Follow',
      FollowStatus.pending => 'Requested',
      FollowStatus.accepted => 'Following',
    };
    return FilledButton.tonal(
      onPressed: onPressed,
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 180),
        child: Text(label, key: ValueKey<FollowStatus>(status)),
      ),
    );
  }
}

final class _DetailsCover extends StatelessWidget {
  const _DetailsCover({this.url});

  final String? url;

  @override
  Widget build(BuildContext context) {
    if (url?.isNotEmpty == true) return AppNetworkImage(url: url!);
    final ColorScheme colors = Theme.of(context).colorScheme;
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: <Color>[colors.primaryContainer, colors.secondaryContainer],
        ),
      ),
    );
  }
}
