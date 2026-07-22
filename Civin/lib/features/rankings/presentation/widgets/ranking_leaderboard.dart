import 'package:civin/core/widgets/empty_widget.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/rankings/domain/entities/ranking.dart';
import 'package:civin/features/rankings/presentation/ranking_providers.dart';
import 'package:civin/features/rankings/presentation/widgets/ranking_filters.dart';
import 'package:civin/features/rankings/presentation/widgets/ranking_list_tile.dart';
import 'package:civin/features/rankings/presentation/widgets/ranking_podium.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

ThemeData rankingDarkTheme(BuildContext context) {
  final ThemeData base = Theme.of(context);
  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    colorScheme: ColorScheme.fromSeed(
      seedColor: const Color(0xFF6C4DFF),
      brightness: Brightness.dark,
      secondary: const Color(0xFFFF4D8D),
      tertiary: const Color(0xFFF5C518),
    ),
    scaffoldBackgroundColor: const Color(0xFF0E0E12),
    textTheme: base.textTheme.apply(
      bodyColor: const Color(0xFFF7F7FA),
      displayColor: const Color(0xFFF7F7FA),
    ),
    appBarTheme: const AppBarTheme(
      centerTitle: true,
      backgroundColor: Colors.transparent,
      elevation: 0,
    ),
  );
}

final class RankingLeaderboard extends ConsumerStatefulWidget {
  const RankingLeaderboard({required this.type, super.key});

  final RankingType type;

  @override
  ConsumerState<RankingLeaderboard> createState() => _RankingLeaderboardState();
}

final class _RankingLeaderboardState extends ConsumerState<RankingLeaderboard>
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
      ref.read(rankingProvider.notifier).configure(type: widget.type);
    });
  }

  @override
  void didUpdateWidget(covariant RankingLeaderboard oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.type != widget.type) {
      _entrance
        ..reset()
        ..forward();
      ref.read(rankingProvider.notifier).configure(type: widget.type);
    }
  }

  @override
  void dispose() {
    _entrance.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final RankingViewState state = ref.watch(rankingProvider);
    final ColorScheme colors = Theme.of(context).colorScheme;

    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: <Color>[
            colors.primary.withValues(alpha: 0.22),
            colors.surface,
            const Color(0xFF0E0E12),
          ],
        ),
      ),
      child: RefreshIndicator.adaptive(
        onRefresh: () => ref.read(rankingProvider.notifier).refresh(),
        child: CustomScrollView(
          physics: const AlwaysScrollableScrollPhysics(),
          slivers: [
            SliverToBoxAdapter(
              child: Padding(
                padding: const EdgeInsets.fromLTRB(16, 8, 16, 0),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    FadeTransition(
                      opacity: CurvedAnimation(
                        parent: _entrance,
                        curve: Curves.easeOut,
                      ),
                      child: SlideTransition(
                        position:
                            Tween<Offset>(
                              begin: const Offset(0, 0.08),
                              end: Offset.zero,
                            ).animate(
                              CurvedAnimation(
                                parent: _entrance,
                                curve: Curves.easeOutCubic,
                              ),
                            ),
                        child: Text(
                          widget.type.subtitle,
                          textAlign: TextAlign.center,
                          style: Theme.of(context).textTheme.bodyMedium
                              ?.copyWith(color: colors.onSurfaceVariant),
                        ),
                      ),
                    ),
                    const SizedBox(height: 16),
                    const RankingPeriodTabs(),
                    const SizedBox(height: 12),
                    const RankingScopeFilters(),
                    const SizedBox(height: 20),
                  ],
                ),
              ),
            ),
            if (state.isLoading && state.entries.isEmpty)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: AppLoadingWidget(message: 'Loading rankings'),
              )
            else if (state.errorMessage != null && state.entries.isEmpty)
              SliverFillRemaining(
                hasScrollBody: false,
                child: AppErrorWidget(
                  message: state.errorMessage!,
                  onRetry: () => ref.read(rankingProvider.notifier).load(),
                ),
              )
            else if (state.entries.isEmpty)
              const SliverFillRemaining(
                hasScrollBody: false,
                child: EmptyStateWidget(
                  message: 'No rankings yet for this period.',
                  icon: Icons.emoji_events_outlined,
                ),
              )
            else ...[
              SliverToBoxAdapter(child: RankingPodium(entries: state.podium)),
              const SliverToBoxAdapter(child: SizedBox(height: 16)),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(16, 0, 16, 28),
                sliver: SliverList.separated(
                  itemCount: state.rest.length,
                  separatorBuilder: (_, _) => const SizedBox(height: 10),
                  itemBuilder: (BuildContext context, int index) =>
                      RankingListTile(
                        entry: state.rest[index],
                        index: index,
                      ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
