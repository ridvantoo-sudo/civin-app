import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/challenges/domain/entities/challenge.dart';
import 'package:civin/features/challenges/presentation/challenge_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class ChallengesScreen extends ConsumerStatefulWidget {
  const ChallengesScreen({super.key});

  @override
  ConsumerState<ChallengesScreen> createState() => _ChallengesScreenState();
}

final class _ChallengesScreenState extends ConsumerState<ChallengesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(challengeProvider.notifier).load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final ChallengeViewState view = ref.watch(challengeProvider);
    return DefaultTabController(
      length: 2,
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Challenges'),
          bottom: const TabBar(
            tabs: <Widget>[
              Tab(text: 'Daily'),
              Tab(text: 'Weekly'),
            ],
          ),
        ),
        body: Column(
          children: <Widget>[
            if (view.notice != null)
              MaterialBanner(
                content: Text(view.notice!),
                actions: <Widget>[
                  TextButton(
                    onPressed: () =>
                        ref.read(challengeProvider.notifier).load(),
                    child: const Text('OK'),
                  ),
                ],
              ),
            if (view.errorMessage != null &&
                view.daily.isEmpty &&
                view.weekly.isEmpty)
              Expanded(
                child: AppErrorWidget(
                  message: view.errorMessage!,
                  onRetry: () => ref.read(challengeProvider.notifier).load(),
                ),
              )
            else if (view.isLoading &&
                view.daily.isEmpty &&
                view.weekly.isEmpty)
              const Expanded(
                child: AppLoadingWidget(message: 'Loading challenges'),
              )
            else
              Expanded(
                child: TabBarView(
                  children: <Widget>[
                    _ChallengeList(
                      items: view.daily,
                      claimingId: view.claimingId,
                      onRefresh: () =>
                          ref.read(challengeProvider.notifier).load(),
                      onClaim: (String id) =>
                          ref.read(challengeProvider.notifier).claim(id),
                    ),
                    _ChallengeList(
                      items: view.weekly,
                      claimingId: view.claimingId,
                      onRefresh: () =>
                          ref.read(challengeProvider.notifier).load(),
                      onClaim: (String id) =>
                          ref.read(challengeProvider.notifier).claim(id),
                    ),
                  ],
                ),
              ),
          ],
        ),
      ),
    );
  }
}

final class _ChallengeList extends StatelessWidget {
  const _ChallengeList({
    required this.items,
    required this.claimingId,
    required this.onRefresh,
    required this.onClaim,
  });

  final List<CivinChallenge> items;
  final String? claimingId;
  final Future<void> Function() onRefresh;
  final Future<void> Function(String id) onClaim;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) {
      return const Center(child: Text('No challenges in this period yet.'));
    }
    return RefreshIndicator.adaptive(
      onRefresh: onRefresh,
      child: ListView.separated(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        itemCount: items.length,
        separatorBuilder: (BuildContext context, int index) =>
            const SizedBox(height: 12),
        itemBuilder: (BuildContext context, int index) {
          final CivinChallenge item = items[index];
          final String reward = <String>[
            if (item.rewardCoins > 0) '${item.rewardCoins} coins',
            if (item.rewardDiamonds > 0) '${item.rewardDiamonds} diamonds',
            if (item.rewardXp > 0) '${item.rewardXp} XP',
          ].join(' · ');
          return Card(
            child: Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text(
                    item.title,
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  if (item.description?.trim().isNotEmpty == true)
                    Text(item.description!),
                  const SizedBox(height: 8),
                  LinearProgressIndicator(value: item.fraction),
                  const SizedBox(height: 6),
                  Text('${item.progress}/${item.target} · $reward'),
                  const SizedBox(height: 8),
                  Align(
                    alignment: Alignment.centerRight,
                    child: FilledButton(
                      onPressed: !item.canClaim || claimingId == item.id
                          ? null
                          : () => onClaim(item.id),
                      child: Text(item.isClaimed ? 'Claimed' : 'Claim'),
                    ),
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
