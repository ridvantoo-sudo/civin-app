import 'package:civin/features/vip/domain/entities/vip.dart';
import 'package:civin/features/vip/presentation/widgets/vip_animations.dart';
import 'package:flutter/material.dart';

/// Compact VIP indicator for chat message rows and list tiles.
final class VipChatBadge extends StatelessWidget {
  const VipChatBadge({
    this.label = 'VIP',
    this.level,
    this.compact = true,
    super.key,
  });

  final String label;
  final int? level;
  final bool compact;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final String text = level == null ? label : '$label $level';
    return Container(
      padding: EdgeInsets.symmetric(
        horizontal: compact ? 6 : 8,
        vertical: compact ? 2 : 4,
      ),
      decoration: BoxDecoration(
        gradient: LinearGradient(
          colors: <Color>[
            colors.primary.withValues(alpha: 0.95),
            colors.secondary.withValues(alpha: 0.85),
          ],
        ),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        text,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: colors.onPrimary,
          fontWeight: FontWeight.w800,
          letterSpacing: 0.4,
          fontSize: compact ? 10 : 11,
        ),
      ),
    );
  }
}

/// Profile-oriented VIP badge mark with optional pulse animation.
final class VipBadge extends StatelessWidget {
  const VipBadge({
    this.level,
    this.levelName,
    this.animated = true,
    this.size = VipBadgeSize.medium,
    this.onTap,
    super.key,
  });

  final int? level;
  final String? levelName;
  final bool animated;
  final VipBadgeSize size;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final double diameter = switch (size) {
      VipBadgeSize.small => 28,
      VipBadgeSize.medium => 40,
      VipBadgeSize.large => 64,
    };
    final String caption = levelName?.trim().isNotEmpty == true
        ? levelName!.trim()
        : level == null
        ? 'VIP'
        : 'VIP $level';

    final Widget badge = Semantics(
      label: caption,
      button: onTap != null,
      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: onTap,
          customBorder: const CircleBorder(),
          child: Container(
            width: diameter,
            height: diameter,
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: <Color>[
                  colors.secondary,
                  colors.primary,
                  colors.tertiary.withValues(alpha: 0.9),
                ],
              ),
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: colors.primary.withValues(alpha: 0.35),
                  blurRadius: 12,
                  offset: const Offset(0, 4),
                ),
              ],
            ),
            child: Icon(
              Icons.workspace_premium_rounded,
              color: colors.onPrimary,
              size: diameter * 0.52,
            ),
          ),
        ),
      ),
    );

    final Widget content = animated ? VipPulseBadgeShell(child: badge) : badge;

    if (size == VipBadgeSize.small) return content;

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        content,
        const SizedBox(height: 6),
        Text(
          caption,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
            fontWeight: FontWeight.w700,
            color: colors.secondary,
          ),
        ),
      ],
    );
  }
}

enum VipBadgeSize { small, medium, large }

final class VipBenefitsList extends StatelessWidget {
  const VipBenefitsList({required this.benefits, super.key});

  final List<String> benefits;

  @override
  Widget build(BuildContext context) {
    if (benefits.isEmpty) {
      return Text(
        'Unlock premium identity, chat presence, and exclusive rewards.',
        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
          color: Theme.of(context).colorScheme.onSurfaceVariant,
        ),
      );
    }

    return Column(
      children: <Widget>[
        for (final String benefit in benefits) ...<Widget>[
          _BenefitRow(label: benefit),
          if (benefit != benefits.last) const SizedBox(height: 8),
        ],
      ],
    );
  }
}

final class _BenefitRow extends StatelessWidget {
  const _BenefitRow({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return Row(
      children: <Widget>[
        Icon(Icons.check_circle_rounded, color: colors.secondary, size: 18),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            label,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              fontWeight: FontWeight.w600,
            ),
          ),
        ),
      ],
    );
  }
}

final class VipLevelCard extends StatelessWidget {
  const VipLevelCard({
    required this.level,
    required this.selected,
    required this.onTap,
    this.isCurrent = false,
    this.trailingAction,
    super.key,
  });

  final VipLevel level;
  final bool selected;
  final bool isCurrent;
  final VoidCallback onTap;
  final Widget? trailingAction;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return Material(
      color: selected
          ? colors.primary.withValues(alpha: 0.18)
          : colors.surfaceContainerHighest.withValues(alpha: 0.55),
      borderRadius: BorderRadius.circular(22),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 220),
          curve: Curves.easeOutCubic,
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(22),
            border: Border.all(
              color: selected
                  ? colors.secondary.withValues(alpha: 0.8)
                  : colors.outlineVariant.withValues(alpha: 0.35),
              width: selected ? 1.6 : 1,
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              Row(
                children: <Widget>[
                  VipBadge(
                    level: level.level,
                    levelName: level.name,
                    size: VipBadgeSize.small,
                    animated: selected,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: <Widget>[
                        Text(
                          level.name,
                          style: Theme.of(context).textTheme.titleMedium
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                        Text(
                          'Level ${level.level} · ${level.durationLabel}',
                          style: Theme.of(context).textTheme.bodySmall
                              ?.copyWith(color: colors.onSurfaceVariant),
                        ),
                      ],
                    ),
                  ),
                  Text(
                    level.priceLabel,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      color: colors.secondary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
              if (isCurrent) ...<Widget>[
                const SizedBox(height: 10),
                Text(
                  'Current plan',
                  style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    color: colors.secondary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
              const SizedBox(height: 12),
              VipBenefitsList(benefits: level.privileges.benefitLabels),
              if (trailingAction != null) ...<Widget>[
                const SizedBox(height: 14),
                trailingAction!,
              ],
            ],
          ),
        ),
      ),
    );
  }
}

final class VipStatusCard extends StatelessWidget {
  const VipStatusCard({required this.subscription, super.key});

  final VipSubscription subscription;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final bool active = subscription.isVip;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: active
              ? <Color>[
                  colors.primary.withValues(alpha: 0.55),
                  colors.secondary.withValues(alpha: 0.28),
                ]
              : <Color>[
                  colors.surfaceContainerHighest.withValues(alpha: 0.7),
                  colors.surface.withValues(alpha: 0.4),
                ],
        ),
        border: Border.all(
          color: colors.outlineVariant.withValues(alpha: 0.35),
        ),
      ),
      child: Row(
        children: <Widget>[
          VipBadge(
            level: subscription.level?.level,
            levelName: subscription.level?.name,
            size: VipBadgeSize.large,
            animated: active,
          ),
          const SizedBox(width: 16),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  active ? 'Current VIP' : 'Not a VIP yet',
                  style: Theme.of(context).textTheme.titleLarge?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  active
                      ? (subscription.level?.name ?? 'VIP member')
                      : 'Choose a level to unlock premium presence.',
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: colors.onSurfaceVariant,
                  ),
                ),
                if (active && subscription.expirationLabel != null) ...<Widget>[
                  const SizedBox(height: 10),
                  Text(
                    'Expires ${subscription.expirationLabel}',
                    style: Theme.of(context).textTheme.labelLarge?.copyWith(
                      color: colors.secondary,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ],
              ],
            ),
          ),
        ],
      ),
    );
  }
}
