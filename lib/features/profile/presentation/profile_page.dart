import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/app_network_image.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/authentication/presentation/auth_controller.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:civin/features/profile/presentation/social_providers.dart';
import 'package:civin/features/profile/widgets/social_widgets.dart';
import 'package:civin/features/vip/presentation/widgets/vip_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class ProfilePage extends ConsumerWidget {
  const ProfilePage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<UserProfile> profile = ref.watch(currentProfileProvider);
    return Scaffold(
      appBar: AppBar(
        title: const Text('Profile'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Settings',
            onPressed: () => context.push(AppRoutes.accountSecurity),
            icon: const Icon(Icons.settings_outlined),
          ),
          IconButton(
            tooltip: 'Agency',
            onPressed: () => context.push(AppRoutes.agency),
            icon: const Icon(Icons.apartment_outlined),
          ),
          IconButton(
            tooltip: 'VIP',
            onPressed: () => context.push(AppRoutes.vip),
            icon: const Icon(Icons.workspace_premium_outlined),
          ),
          IconButton(
            tooltip: 'Search users',
            onPressed: () => context.push(AppRoutes.searchUsers),
            icon: const Icon(Icons.person_search_rounded),
          ),
          IconButton(
            tooltip: 'Blocked users',
            onPressed: () => context.push(AppRoutes.blockedUsers),
            icon: const Icon(Icons.block_rounded),
          ),
        ],
      ),
      body: SocialPageWidth(
        child: profile.when(
          loading: () => const AppLoadingWidget(message: 'Loading profile'),
          error: (Object error, StackTrace stackTrace) => AppErrorWidget(
            message: error.toString(),
            onRetry: () => ref.read(currentProfileProvider.notifier).refresh(),
          ),
          data: (UserProfile value) => RefreshIndicator.adaptive(
            onRefresh: () =>
                ref.read(currentProfileProvider.notifier).refresh(),
            child: _ProfileContent(profile: value),
          ),
        ),
      ),
    );
  }
}

final class _ProfileContent extends ConsumerWidget {
  const _ProfileContent({required this.profile});

  final UserProfile profile;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AuthViewState authState = ref.watch(authControllerProvider);
    final ColorScheme colors = Theme.of(context).colorScheme;

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      children: <Widget>[
        SizedBox(
          height: 190,
          child: Stack(
            fit: StackFit.expand,
            clipBehavior: Clip.none,
            children: <Widget>[
              _CoverImage(url: profile.coverImageUrl),
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
                const Positioned(right: 16, bottom: 12, child: LiveBadge()),
            ],
          ),
        ),
        const SizedBox(height: 50),
        Padding(
          padding: const EdgeInsets.symmetric(horizontal: 20),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
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
                            style: Theme.of(context).textTheme.headlineSmall
                                ?.copyWith(fontWeight: FontWeight.w700),
                          ),
                        ),
                        if (profile.isVip) ...<Widget>[
                          const SizedBox(width: 8),
                          VipBadge(
                            size: VipBadgeSize.small,
                            onTap: () => context.push(AppRoutes.vipBadge),
                          ),
                        ],
                      ],
                    ),
                    Text('@${profile.username} · Level ${profile.level}'),
                  ],
                ),
              ),
              FilledButton.tonalIcon(
                onPressed: () => context.push(AppRoutes.editProfile),
                icon: const Icon(Icons.edit_rounded, size: 18),
                label: const Text('Edit'),
              ),
            ],
          ),
        ),
        if (profile.bio?.trim().isNotEmpty == true)
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 18, 20, 0),
            child: Text(profile.bio!),
          ),
        if (profile.country != null)
          Padding(
            padding: const EdgeInsets.fromLTRB(20, 10, 20, 0),
            child: Text(
              '${profile.country!.flagEmoji ?? '🌍'} ${profile.country!.name}',
              style: Theme.of(context).textTheme.bodySmall,
            ),
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
        const SizedBox(height: 24),
        const Padding(
          padding: EdgeInsets.symmetric(horizontal: 20),
          child: Text(
            'Settings',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
        ),
        ListTile(
          leading: const Icon(Icons.shield_outlined),
          title: const Text('Account security'),
          subtitle: const Text('Biometrics, session, and account access'),
          trailing: const Icon(Icons.chevron_right_rounded),
          onTap: () => context.push(AppRoutes.accountSecurity),
        ),
        ListTile(
          leading: Icon(Icons.logout_rounded, color: colors.error),
          title: Text('Log out', style: TextStyle(color: colors.error)),
          subtitle: const Text('Sign out of this device'),
          enabled: !authState.isLoading,
          onTap: () => _logout(context, ref),
        ),
        const SizedBox(height: 32),
      ],
    );
  }

  Future<void> _logout(BuildContext context, WidgetRef ref) async {
    final bool confirmed =
        await showDialog<bool>(
          context: context,
          builder: (BuildContext dialogContext) => AlertDialog(
            title: const Text('Log out?'),
            content: const Text('You will need to sign in again to continue.'),
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.pop(dialogContext, false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(dialogContext, true),
                child: const Text('Log out'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !context.mounted) return;

    final bool success = await ref
        .read(authControllerProvider.notifier)
        .signOut();
    if (context.mounted && success) context.go(AppRoutes.login);
  }
}

final class _CoverImage extends StatelessWidget {
  const _CoverImage({this.url});

  final String? url;

  @override
  Widget build(BuildContext context) {
    if (url?.isNotEmpty == true) {
      return AppNetworkImage(url: url!);
    }
    final ColorScheme colors = Theme.of(context).colorScheme;
    return SizedBox.expand(
      child: DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: <Color>[colors.primaryContainer, colors.secondaryContainer],
          ),
        ),
        child: Icon(
          Icons.landscape_rounded,
          size: 64,
          color: colors.onPrimaryContainer.withValues(alpha: .35),
        ),
      ),
    );
  }
}
