import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/vip/domain/entities/vip.dart';
import 'package:civin/features/vip/presentation/vip_providers.dart';
import 'package:civin/features/vip/presentation/widgets/vip_animations.dart';
import 'package:civin/features/vip/presentation/widgets/vip_theme.dart';
import 'package:civin/features/vip/presentation/widgets/vip_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class VipHome extends ConsumerStatefulWidget {
  const VipHome({super.key});

  @override
  ConsumerState<VipHome> createState() => _VipHomeState();
}

final class _VipHomeState extends ConsumerState<VipHome>
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
      ref.read(vipProvider.notifier).load();
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

    return Theme(
      data: vipDarkTheme(context),
      child: Builder(
        builder: (BuildContext context) {
          final ColorScheme colors = Theme.of(context).colorScheme;
          return Scaffold(
            appBar: AppBar(
              title: const Text('VIP'),
              actions: <Widget>[
                IconButton(
                  tooltip: 'Badge',
                  onPressed: () => context.push(AppRoutes.vipBadge),
                  icon: const Icon(Icons.workspace_premium_outlined),
                ),
              ],
            ),
            body: DecoratedBox(
              decoration: vipAtmosphere(colors),
              child: RefreshIndicator.adaptive(
                onRefresh: () => ref.read(vipProvider.notifier).refresh(),
                child: state.isLoading && state.levels.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const <Widget>[
                          SizedBox(height: 120),
                          AppLoadingWidget(message: 'Loading VIP'),
                        ],
                      )
                    : state.errorMessage != null && state.levels.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: <Widget>[
                          const SizedBox(height: 48),
                          AppErrorWidget(
                            message: state.errorMessage!,
                            onRetry: () =>
                                ref.read(vipProvider.notifier).load(),
                          ),
                        ],
                      )
                    : ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
                        children: <Widget>[
                          VipEntranceAnimation(
                            animation: _entrance,
                            child: Text(
                              'Premium presence',
                              style: Theme.of(context).textTheme.headlineSmall
                                  ?.copyWith(fontWeight: FontWeight.w800),
                            ),
                          ),
                          const SizedBox(height: 6),
                          VipEntranceAnimation(
                            animation: _entrance,
                            delay: 0.08,
                            child: Text(
                              'Stand out with badges, frames, chat effects, and exclusive gifts.',
                              style: Theme.of(context).textTheme.bodyMedium
                                  ?.copyWith(color: colors.onSurfaceVariant),
                            ),
                          ),
                          const SizedBox(height: 20),
                          VipEntranceAnimation(
                            animation: _entrance,
                            delay: 0.12,
                            child: VipStatusCard(
                              subscription: state.subscription,
                            ),
                          ),
                          const SizedBox(height: 20),
                          VipEntranceAnimation(
                            animation: _entrance,
                            delay: 0.18,
                            child: _SectionCard(
                              title: 'Benefits',
                              child: VipBenefitsList(
                                benefits: state.isVip
                                    ? state.currentBenefits
                                    : const <String>[
                                        'VIP badge',
                                        'Profile frame',
                                        'Chat effect',
                                        'Entrance animation',
                                        'Exclusive gifts',
                                      ],
                              ),
                            ),
                          ),
                          if (state.isVip &&
                              state.subscription.expirationLabel != null) ...<
                            Widget
                          >[
                            const SizedBox(height: 16),
                            VipEntranceAnimation(
                              animation: _entrance,
                              delay: 0.22,
                              child: _SectionCard(
                                title: 'Expiration date',
                                child: Text(
                                  state.subscription.expirationLabel!,
                                  style: Theme.of(context)
                                      .textTheme
                                      .titleMedium
                                      ?.copyWith(
                                        fontWeight: FontWeight.w800,
                                        color: colors.secondary,
                                      ),
                                ),
                              ),
                            ),
                          ],
                          const SizedBox(height: 20),
                          VipEntranceAnimation(
                            animation: _entrance,
                            delay: 0.28,
                            child: Row(
                              children: <Widget>[
                                Expanded(
                                  child: FilledButton(
                                    onPressed: () =>
                                        context.push(AppRoutes.vipPurchase),
                                    child: Text(
                                      state.isVip ? 'Upgrade' : 'Purchase',
                                    ),
                                  ),
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: OutlinedButton(
                                    onPressed: () =>
                                        context.push(AppRoutes.vipLevels),
                                    child: const Text('VIP levels'),
                                  ),
                                ),
                              ],
                            ),
                          ),
                          if (state.errorMessage != null) ...<Widget>[
                            const SizedBox(height: 16),
                            Text(
                              state.errorMessage!,
                              style: TextStyle(color: colors.error),
                            ),
                          ],
                          if (state.actionMessage != null) ...<Widget>[
                            const SizedBox(height: 16),
                            Text(
                              state.actionMessage!,
                              style: TextStyle(color: colors.secondary),
                            ),
                          ],
                        ],
                      ),
              ),
            ),
          );
        },
      ),
    );
  }
}

final class _SectionCard extends StatelessWidget {
  const _SectionCard({required this.title, required this.child});

  final String title;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHighest.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(22),
        border: Border.all(
          color: colors.outlineVariant.withValues(alpha: 0.3),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            title,
            style: Theme.of(
              context,
            ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 12),
          child,
        ],
      ),
    );
  }
}
