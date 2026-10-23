import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/rankings/data/repositories/ranking_repository_impl.dart';
import 'package:civin/features/rankings/domain/entities/ranking.dart';
import 'package:civin/features/rankings/domain/usecases/ranking_usecases.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<GetRankings> getRankingsUseCaseProvider = Provider<GetRankings>(
  (Ref ref) => GetRankings(ref.watch(rankingRepositoryProvider)),
);

/// Leaderboard state for Host / Gifter / PK / Voice rankings.
final NotifierProvider<RankingController, RankingViewState> rankingProvider =
    NotifierProvider<RankingController, RankingViewState>(
      RankingController.new,
    );

const List<String> kRankingCountryPresets = <String>[
  'TR',
  'US',
  'GB',
  'DE',
  'SA',
  'AE',
  'EG',
  'IN',
];

final class RankingController extends Notifier<RankingViewState> {
  @override
  RankingViewState build() => const RankingViewState();

  Future<void> configure({
    required RankingType type,
    RankingPeriod? period,
    RankingScope? scope,
    String? country,
    int limit = 50,
    bool reload = true,
  }) async {
    state = state.copyWith(
      query: RankingQuery(
        type: type,
        period: period ?? state.query.period,
        scope: scope ?? state.query.scope,
        country: country ?? state.query.country ?? kRankingCountryPresets.first,
        limit: limit,
      ),
      clearError: true,
    );
    if (reload) {
      await load();
    }
  }

  Future<void> setPeriod(RankingPeriod period) async {
    if (state.query.period == period) return;
    state = state.copyWith(
      query: state.query.copyWith(period: period),
      clearError: true,
    );
    await load();
  }

  Future<void> setScope(RankingScope scope) async {
    if (state.query.scope == scope) return;
    state = state.copyWith(
      query: state.query.copyWith(
        scope: scope,
        country: state.query.country ?? kRankingCountryPresets.first,
      ),
      clearError: true,
    );
    await load();
  }

  Future<void> setCountry(String country) async {
    final String trimmed = country.trim().toUpperCase();
    if (trimmed.isEmpty) return;
    if (state.query.country == trimmed &&
        state.query.scope == RankingScope.country) {
      return;
    }
    state = state.copyWith(
      query: state.query.copyWith(
        scope: RankingScope.country,
        country: trimmed,
      ),
      clearError: true,
    );
    await load();
  }

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    final RankingQuery query = state.query;
    if (query.scope == RankingScope.country &&
        (query.effectiveCountry == null || query.effectiveCountry!.isEmpty)) {
      state = state.copyWith(
        isLoading: false,
        errorMessage: 'Select a country to filter rankings.',
        entries: const <RankingEntry>[],
      );
      return;
    }

    final RepositoryResult<List<RankingEntry>> result = await ref.read(
      getRankingsUseCaseProvider,
    )(query);

    if (!ref.mounted) return;

    result.fold(
      onSuccess: (List<RankingEntry> entries) {
        state = state.copyWith(
          entries: List<RankingEntry>.unmodifiable(entries),
          isLoading: false,
          clearError: true,
        );
      },
      onFailure: (failure) {
        state = state.copyWith(
          isLoading: false,
          errorMessage: failure.message,
        );
      },
    );
  }

  Future<void> refresh() => load();
}
