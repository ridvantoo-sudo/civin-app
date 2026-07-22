import 'package:civin/core/widgets/app_network_image.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:flutter/material.dart';

final class SocialAvatar extends StatelessWidget {
  const SocialAvatar({
    required this.user,
    super.key,
    this.radius = 28,
    this.heroTag,
  });

  final SocialUser user;
  final double radius;
  final Object? heroTag;

  @override
  Widget build(BuildContext context) {
    final String? url = user.avatarUrl;
    final Widget avatar = Stack(
      clipBehavior: Clip.none,
      children: <Widget>[
        CircleAvatar(
          radius: radius,
          backgroundColor: Theme.of(
            context,
          ).colorScheme.surfaceContainerHighest,
          child: ClipOval(
            child: url?.isNotEmpty == true
                ? AppNetworkImage(
                    url: url!,
                    width: radius * 2,
                    height: radius * 2,
                  )
                : Icon(Icons.person_rounded, size: radius),
          ),
        ),
        if (user.isOnline)
          Positioned(
            right: 0,
            bottom: 1,
            child: Container(
              width: radius * .38,
              height: radius * .38,
              decoration: BoxDecoration(
                color: Colors.greenAccent.shade700,
                shape: BoxShape.circle,
                border: Border.all(
                  color: Theme.of(context).colorScheme.surface,
                  width: 2,
                ),
              ),
            ),
          ),
      ],
    );
    return heroTag == null ? avatar : Hero(tag: heroTag!, child: avatar);
  }
}

final class SocialUserTile extends StatelessWidget {
  const SocialUserTile({
    required this.user,
    required this.onTap,
    super.key,
    this.trailing,
  });

  final SocialUser user;
  final VoidCallback onTap;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) => ListTile(
    onTap: onTap,
    leading: SocialAvatar(user: user, heroTag: 'avatar-${user.id}'),
    title: Row(
      children: <Widget>[
        Flexible(
          child: Text(
            user.displayName,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
        ),
        if (user.isVip) ...<Widget>[
          const SizedBox(width: 4),
          Icon(
            Icons.verified_rounded,
            size: 17,
            color: Theme.of(context).colorScheme.primary,
          ),
        ],
        if (user.isLive) ...<Widget>[
          const SizedBox(width: 8),
          const LiveBadge(),
        ],
      ],
    ),
    subtitle: Text(
      '@${user.username}',
      maxLines: 1,
      overflow: TextOverflow.ellipsis,
    ),
    trailing: trailing ?? const Icon(Icons.chevron_right_rounded),
  );
}

final class LiveBadge extends StatelessWidget {
  const LiveBadge({super.key});

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.error,
      borderRadius: BorderRadius.circular(20),
    ),
    child: const Padding(
      padding: EdgeInsets.symmetric(horizontal: 7, vertical: 3),
      child: Text(
        'LIVE',
        style: TextStyle(
          color: Colors.white,
          fontSize: 10,
          fontWeight: FontWeight.w800,
          letterSpacing: .5,
        ),
      ),
    ),
  );
}

final class ProfileStat extends StatelessWidget {
  const ProfileStat({
    required this.value,
    required this.label,
    super.key,
    this.onTap,
  });

  final int value;
  final String label;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => InkWell(
    onTap: onTap,
    borderRadius: BorderRadius.circular(12),
    child: Padding(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          AnimatedSwitcher(
            duration: const Duration(milliseconds: 220),
            child: Text(
              _compact(value),
              key: ValueKey<int>(value),
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
            ),
          ),
          Text(label, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    ),
  );
}

final class SocialPageWidth extends StatelessWidget {
  const SocialPageWidth({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context) => Align(
    alignment: Alignment.topCenter,
    child: ConstrainedBox(
      constraints: const BoxConstraints(maxWidth: 720),
      child: child,
    ),
  );
}

String _compact(int value) {
  if (value >= 1000000) return '${(value / 1000000).toStringAsFixed(1)}M';
  if (value >= 1000) return '${(value / 1000).toStringAsFixed(1)}K';
  return value.toString();
}
