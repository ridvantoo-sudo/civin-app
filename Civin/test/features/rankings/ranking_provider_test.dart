import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/rankings/data/repositories/ranking_repository_impl.dart';
import 'package:civin/features/rankings/domain/entities/ranking.dart';
import 'package:civin/features/rankings/domain/repositories/ranking_repository.dart';
import 'package:civin/features/rankings/presentation/ranking_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('rankingProvider loads entries for configured type', () async {
    final _FakeRankingRepository repository = _FakeRankingRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [rankingRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container
        .read(rankingProvider.notifier)
        .configure(type: RankingType.host);

    final RankingViewState state = container.read(rankingProvider);
    expect(state.query.type, RankingType.host);
    expect(state.entries, hasLength(3));
    expect(state.entries.first.user.displayName, 'Top Host');
    expect(state.podium, hasLength(3));
    expect(state.rest, isEmpty);
    expect(repository.lastQuery?.type, RankingType.host);
  });

  test('rankingProvider switches period and reloads', () async {
    final _FakeRankingRepository repository = _FakeRankingRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [rankingRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container
        .read(rankingProvider.notifier)
        .configure(type: RankingType.gifter);
    await container
        .read(rankingProvider.notifier)
        .setPeriod(RankingPeriod.weekly);

    expect(container.read(rankingProvider).query.period, RankingPeriod.weekly);
    expect(repository.lastQuery?.period, RankingPeriod.weekly);
    expect(repository.lastQuery?.type, RankingType.gifter);
  });

  test('rankingProvider applies country filter', () async {
    final _FakeRankingRepository repository = _FakeRankingRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [rankingRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container
        .read(rankingProvider.notifier)
        .configure(type: RankingType.pk);
    await container.read(rankingProvider.notifier).setCountry('tr');

    final RankingViewState state = container.read(rankingProvider);
    expect(state.query.scope, RankingScope.country);
    expect(state.query.country, 'TR');
    expect(repository.lastQuery?.effectiveCountry, 'TR');
  });

  test('rankingProvider surfaces load failures', () async {
    final _FakeRankingRepository repository = _FakeRankingRepository()
      ..fail = true;
    final ProviderContainer container = ProviderContainer(
      overrides: [rankingRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container
        .read(rankingProvider.notifier)
        .configure(type: RankingType.voice);

    expect(container.read(rankingProvider).entries, isEmpty);
    expect(container.read(rankingProvider).errorMessage, 'Rankings unavailable');
  });
}

final class _FakeRankingRepository implements RankingRepository {
  RankingQuery? lastQuery;
  bool fail = false;

  @override
  Future<RepositoryResult<List<RankingEntry>>> getRankings(
    RankingQuery query,
  ) async {
    lastQuery = query;
    if (fail) {
      return const RepositoryFailure<List<RankingEntry>>(
        AppFailure.network(message: 'Rankings unavailable'),
      );
    }
    return const RepositorySuccess<List<RankingEntry>>(
      <RankingEntry>[
        RankingEntry(
          rank: 1,
          score: 900,
          user: RankingUser(id: 'u-1', nickname: 'Top Host', isVip: true),
        ),
        RankingEntry(
          rank: 2,
          score: 500,
          user: RankingUser(id: 'u-2', nickname: 'Second Host'),
        ),
        RankingEntry(
          rank: 3,
          score: 120,
          user: RankingUser(id: 'u-3', nickname: 'Third Host'),
        ),
      ],
    );
  }
}
