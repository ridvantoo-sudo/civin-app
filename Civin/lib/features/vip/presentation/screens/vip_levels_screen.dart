import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/empty_widget.dart';
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

final class VipLevels extends ConsumerStatefulWidget {
  const VipLevels({super.key});

  @override
  ConsumerState<VipLevels> createState() => _VipLevelsState();
}

final class _VipLevelsState extends ConsumerState<VipLevels>
    with SingleTickerProviderStateMixin {
  late final AnimationController _entrance;

  @override
  void initState() {
    super.initState();
    _entrance = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 650),
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

    return Theme(
      data: vipDarkTheme(context),
      child: Builder(
        builder: (BuildContext context) {
          final ColorScheme colors = Theme.of(context).colorScheme;
          return Scaffold(
            appBar: AppBar(title: const Text('VIP levels')),
            body: DecoratedBox(
              decoration: vipAtmosphere(colors),
              child: RefreshIndicator.adaptive(
                onRefresh: () => ref.read(vipProvider.notifier).refresh(),
                child: state.isLoading && state.levels.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const <Widget>[
                          SizedBox(height: 120),
                          AppLoadingWidget(message: 'Loading levels'),
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
                    : state.levels.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const <Widget>[
                          SizedBox(height: 80),
                          EmptyStateWidget(
                            message: 'No VIP levels available right now.',
                          ),
                        ],
                      )
                    : ListView.separated(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(20, 12, 20, 28),
                        itemCount: state.levels.length,
                        separatorBuilder: (_, _) => const SizedBox(height: 12),
                        itemBuilder: (BuildContext context, int index) {
                          final VipLevel level = state.levels[index];
                          final bool isCurrent =
                              state.subscription.level?.id == level.id;
                          return VipEntranceAnimation(
                            animation: _entrance,
                            delay: (index * 0.06).clamp(0.0, 0.5),
                            child: VipLevelCard(
                              level: level,
                              selected: state.selectedLevelId == level.id,
                              isCurrent: isCurrent,
                              onTap: () => ref
                                  .read(vipProvider.notifier)
                                  .selectLevel(level.id),
                              trailingAction: FilledButton.tonal(
                                onPressed: () {
                                  ref
                                      .read(vipProvider.notifier)
                                      .selectLevel(level.id);
                                  context.push(AppRoutes.vipPurchase);
                                },
                                child: Text(
                                  isCurrent
                                      ? 'Current'
                                      : state.canUpgradeTo(level)
                                      ? 'Upgrade'
                                      : state.canPurchase(level)
                                      ? 'Purchase'
                                      : 'View',
                                ),
                              ),
                            ),
                          );
                        },
                      ),
              ),
            ),
          );
        },
      ),
    );
  }
}
