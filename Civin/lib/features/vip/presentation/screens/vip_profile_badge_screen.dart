import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/vip/domain/entities/vip.dart';
import 'package:civin/features/vip/presentation/vip_providers.dart';
import 'package:civin/features/vip/presentation/widgets/vip_animations.dart';
import 'package:civin/features/vip/presentation/widgets/vip_theme.dart';
import 'package:civin/features/vip/presentation/widgets/vip_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

/// Profile badge showcase and integration surface for VIP identity.
final class VipProfileBadge extends ConsumerStatefulWidget {
  const VipProfileBadge({super.key});

  @override
  ConsumerState<VipProfileBadge> createState() => _VipProfileBadgeState();
}

final class _VipProfileBadgeState extends ConsumerState<VipProfileBadge>
    with SingleTickerProviderStateMixin {
  late final AnimationController _entrance;

  @override
  void initState() {
    super.initState();
    _entrance = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 700),
    )..forward();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (ref.read(vipProvider).levels.isEmpty) {
        ref.read(vipProvider.notifier).load();
      }
    });
  }

  @override
  void dispose() {
    _entrance.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final VipViewState state = ref.watch(vipProvider);
    final VipSubscription subscription = state.subscription;

    return Theme(
      data: vipDarkTheme(context),
      child: Builder(
        builder: (BuildContext context) {
          final ColorScheme colors = Theme.of(context).colorScheme;
          return Scaffold(
            appBar: AppBar(title: const Text('VIP badge')),
            body: DecoratedBox(
              decoration: vipAtmosphere(colors),
              child: state.isLoading && !state.isVip
                  ? const AppLoadingWidget(message: 'Loading badge')
                  : ListView(
                      padding: const EdgeInsets.fromLTRB(24, 20, 24, 32),
                      children: <Widget>[
                        VipEntranceAnimation(
                          animation: _entrance,
                          child: Center(
                            child: VipBadge(
                              level: subscription.level?.level,
                              levelName: subscription.level?.name ?? 'VIP',
                              size: VipBadgeSize.large,
                              animated: subscription.isVip,
                              onTap: () => context.push(AppRoutes.vip),
                            ),
                          ),
                        ),
                        const SizedBox(height: 28),
                        VipEntranceAnimation(
                          animation: _entrance,
                          delay: 0.12,
                          child: Text(
                            'Profile integration',
                            style: Theme.of(context).textTheme.titleMedium
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                        ),
                        const SizedBox(height: 10),
                        VipEntranceAnimation(
                          animation: _entrance,
                          delay: 0.16,
                          child: _PreviewTile(
                            title: 'Profile name row',
                            child: Row(
                              children: <Widget>[
                                Text(
                                  'You',
                                  style: Theme.of(context).textTheme.titleMedium
                                      ?.copyWith(fontWeight: FontWeight.w700),
                                ),
                                if (subscription.isVip) ...<Widget>[
                                  const SizedBox(width: 8),
                                  VipBadge(
                                    level: subscription.level?.level,
                                    size: VipBadgeSize.small,
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 12),
                        VipEntranceAnimation(
                          animation: _entrance,
                          delay: 0.2,
                          child: _PreviewTile(
                            title: 'Chat badge integration',
                            child: Row(
                              children: <Widget>[
                                Text(
                                  'Host',
                                  style: Theme.of(context).textTheme.bodyLarge,
                                ),
                                if (subscription.isVip) ...<Widget>[
                                  const SizedBox(width: 8),
                                  VipChatBadge(
                                    level: subscription.level?.level,
                                  ),
                                ],
                              ],
                            ),
                          ),
                        ),
                        const SizedBox(height: 24),
                        if (subscription.isVip &&
                            subscription.expirationLabel != null)
                          Text(
                            'Active until ${subscription.expirationLabel}',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.labelLarge
                                ?.copyWith(
                                  color: colors.secondary,
                                  fontWeight: FontWeight.w700,
                                ),
                          )
                        else
                          FilledButton(
                            onPressed: () =>
                                context.push(AppRoutes.vipPurchase),
                            child: const Text('Get VIP badge'),
                          ),
                      ],
                    ),
            ),
          );
        },
      ),
    );
  }
}

final class _PreviewTile extends StatelessWidget {
  const _PreviewTile({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHighest.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            title,
            style: Theme.of(context).textTheme.labelLarge?.copyWith(
              color: colors.onSurfaceVariant,
            ),
          ),
          const SizedBox(height: 10),
          child,
        ],
      ),
    );
  }
}
