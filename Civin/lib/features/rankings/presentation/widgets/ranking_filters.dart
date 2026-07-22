import 'package:civin/features/rankings/domain/entities/ranking.dart';
import 'package:civin/features/rankings/presentation/ranking_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class RankingPeriodTabs extends ConsumerWidget {
  const RankingPeriodTabs({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final RankingPeriod selected = ref.watch(
      rankingProvider.select((RankingViewState s) => s.query.period),
    );

    return SegmentedButton<RankingPeriod>(
      segments: const <ButtonSegment<RankingPeriod>>[
        ButtonSegment<RankingPeriod>(
          value: RankingPeriod.daily,
          label: Text('Daily'),
          icon: Icon(Icons.today_rounded),
        ),
        ButtonSegment<RankingPeriod>(
          value: RankingPeriod.weekly,
          label: Text('Weekly'),
          icon: Icon(Icons.date_range_rounded),
        ),
        ButtonSegment<RankingPeriod>(
          value: RankingPeriod.monthly,
          label: Text('Monthly'),
          icon: Icon(Icons.calendar_month_rounded),
        ),
      ],
      selected: <RankingPeriod>{selected},
      onSelectionChanged: (Set<RankingPeriod> next) {
        if (next.isEmpty) return;
        ref.read(rankingProvider.notifier).setPeriod(next.first);
      },
    );
  }
}

final class RankingScopeFilters extends ConsumerWidget {
  const RankingScopeFilters({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final RankingViewState state = ref.watch(rankingProvider);
    final RankingScope scope = state.query.scope;
    final String? country = state.query.country;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        SegmentedButton<RankingScope>(
          segments: const <ButtonSegment<RankingScope>>[
            ButtonSegment<RankingScope>(
              value: RankingScope.global,
              label: Text('Global'),
              icon: Icon(Icons.public_rounded),
            ),
            ButtonSegment<RankingScope>(
              value: RankingScope.country,
              label: Text('Country'),
              icon: Icon(Icons.flag_rounded),
            ),
          ],
          selected: <RankingScope>{scope},
          onSelectionChanged: (Set<RankingScope> next) {
            if (next.isEmpty) return;
            ref.read(rankingProvider.notifier).setScope(next.first);
          },
        ),
        if (scope == RankingScope.country) ...[
          const SizedBox(height: 12),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final String code in kRankingCountryPresets)
                FilterChip(
                  label: Text(code),
                  selected: country == code,
                  onSelected: (_) =>
                      ref.read(rankingProvider.notifier).setCountry(code),
                ),
            ],
          ),
        ],
      ],
    );
  }
}
