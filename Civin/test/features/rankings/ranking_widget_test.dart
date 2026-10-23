import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/rankings/data/repositories/ranking_repository_impl.dart';
import 'package:civin/features/rankings/domain/entities/ranking.dart';
import 'package:civin/features/rankings/domain/repositories/ranking_repository.dart';
import 'package:civin/features/rankings/presentation/screens/host_ranking_screen.dart';
import 'package:civin/features/rankings/presentation/screens/ranking_home_screen.dart';
import 'package:civin/features/rankings/presentation/widgets/ranking_list_tile.dart';
import 'package:civin/features/rankings/presentation/widgets/ranking_podium.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('RankingHome lists ranking categories', (WidgetTester tester) async {
    await tester.pumpWidget(
      const ProviderScope(
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          home: RankingHome(),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('Rankings'), findsOneWidget);
    expect(find.text('Host Ranking'), findsOneWidget);
    expect(find.text('Gifter Ranking'), findsOneWidget);
    expect(find.text('PK Ranking'), findsOneWidget);
    expect(find.text('Voice Ranking'), findsOneWidget);
  });

  testWidgets('HostRanking shows podium, tabs, and ranked rows', (
    WidgetTester tester,
  ) async {
    final _FakeRankingRepository repository = _FakeRankingRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [rankingRepositoryProvider.overrideWithValue(repository)],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData(
            useMaterial3: true,
            brightness: Brightness.dark,
            colorScheme: ColorScheme.fromSeed(
              seedColor: const Color(0xFF6C4DFF),
              brightness: Brightness.dark,
            ),
          ),
          home: const HostRanking(),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('Host Ranking'), findsOneWidget);
    expect(find.text('Daily'), findsOneWidget);
    expect(find.text('Weekly'), findsOneWidget);
    expect(find.text('Monthly'), findsOneWidget);
    expect(find.text('Global'), findsOneWidget);
    expect(find.text('Country'), findsOneWidget);
    expect(find.byType(RankingPodium), findsOneWidget);
    expect(find.text('Top Host'), findsWidgets);
    expect(find.text('VIP'), findsWidgets);
    expect(find.text('Fourth Host'), findsOneWidget);
    expect(find.byType(RankingListTile), findsOneWidget);
  });

  testWidgets('ranking podium and list tile render score and vip badge', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        themeMode: ThemeMode.dark,
        darkTheme: ThemeData.dark(useMaterial3: true),
        home: Scaffold(
          body: Column(
            children: [
              const RankingPodium(
                entries: <RankingEntry>[
                  RankingEntry(
                    rank: 1,
                    score: 900,
                    user: RankingUser(
                      id: '1',
                      nickname: 'Gold',
                      isVip: true,
                    ),
                  ),
                  RankingEntry(
                    rank: 2,
                    score: 500,
                    user: RankingUser(id: '2', nickname: 'Silver'),
                  ),
                  RankingEntry(
                    rank: 3,
                    score: 200,
                    user: RankingUser(id: '3', nickname: 'Bronze'),
                  ),
                ],
              ),
              RankingListTile(
                index: 0,
                entry: const RankingEntry(
                  rank: 4,
                  score: 88,
                  user: RankingUser(
                    id: '4',
                    nickname: 'Runner',
                    isVip: true,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );

    await tester.pumpAndSettle();
    expect(find.text('Gold'), findsOneWidget);
    expect(find.text('Silver'), findsOneWidget);
    expect(find.text('Bronze'), findsOneWidget);
    expect(find.text('Runner'), findsOneWidget);
    expect(find.text('#4'), findsOneWidget);
    expect(find.text('VIP'), findsWidgets);
  });
}

final class _FakeRankingRepository implements RankingRepository {
  @override
  Future<RepositoryResult<List<RankingEntry>>> getRankings(
    RankingQuery query,
  ) async {
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
        RankingEntry(
          rank: 4,
          score: 80,
          user: RankingUser(id: 'u-4', nickname: 'Fourth Host'),
        ),
      ],
    );
  }
}
