import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/games/domain/entities/civin_game.dart';
import 'package:civin/features/games/presentation/game_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class GamesHomeScreen extends ConsumerStatefulWidget {
  const GamesHomeScreen({this.roomId, this.embedded = false, super.key});

  final String? roomId;
  final bool embedded;

  @override
  ConsumerState<GamesHomeScreen> createState() => _GamesHomeScreenState();
}

final class _GamesHomeScreenState extends ConsumerState<GamesHomeScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(gameCatalogProvider.notifier).load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final GameCatalogState catalog = ref.watch(gameCatalogProvider);
    final Widget body = _CatalogBody(
      catalog: catalog,
      onRetry: () => ref.read(gameCatalogProvider.notifier).load(),
      onOpen: (CivinGame game) {
        final String roomId = widget.roomId ?? '';
        final String path = roomId.isEmpty
            ? AppRoutes.gamePath(game.slug)
            : '${AppRoutes.gamePath(game.slug)}?roomId=$roomId';
        context.push(path, extra: game);
      },
    );

    if (widget.embedded) {
      return Material(
        color: Theme.of(context).colorScheme.surface,
        borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
        clipBehavior: Clip.antiAlias,
        child: Column(
          children: <Widget>[
            const SizedBox(height: 8),
            Container(
              width: 36,
              height: 4,
              decoration: BoxDecoration(
                color: Theme.of(context).colorScheme.outlineVariant,
                borderRadius: BorderRadius.circular(99),
              ),
            ),
            AppBar(
              title: const Text('Live games'),
              automaticallyImplyLeading: false,
              actions: <Widget>[
                IconButton(
                  tooltip: 'Close',
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close_rounded),
                ),
              ],
            ),
            Expanded(child: body),
          ],
        ),
      );
    }

    return Scaffold(
      appBar: AppBar(title: const Text('Games')),
      body: body,
    );
  }
}

final class _CatalogBody extends StatelessWidget {
  const _CatalogBody({
    required this.catalog,
    required this.onRetry,
    required this.onOpen,
  });

  final GameCatalogState catalog;
  final Future<void> Function() onRetry;
  final ValueChanged<CivinGame> onOpen;

  @override
  Widget build(BuildContext context) {
    if (catalog.isLoading && catalog.games.isEmpty) {
      return const AppLoadingWidget(message: 'Loading games');
    }
    if (catalog.errorMessage != null && catalog.games.isEmpty) {
      return AppErrorWidget(message: catalog.errorMessage!, onRetry: onRetry);
    }
    return RefreshIndicator.adaptive(
      onRefresh: onRetry,
      child: ListView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 28),
        children: <Widget>[
          Text(
            'Play with your coins. Winnings go straight back to your wallet.',
            style: Theme.of(context).textTheme.bodyMedium,
          ),
          const SizedBox(height: 16),
          ...catalog.games.map(
            (CivinGame game) =>
                _GameCard(game: game, onOpen: () => onOpen(game)),
          ),
          if (catalog.leaders.isNotEmpty) ...<Widget>[
            const SizedBox(height: 12),
            Text(
              'Today’s leaders',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 8),
            ...catalog.leaders
                .take(5)
                .map(
                  (GameLeaderEntry entry) => ListTile(
                    contentPadding: EdgeInsets.zero,
                    leading: CircleAvatar(child: Text('${entry.rank}')),
                    title: Text(entry.displayName),
                    subtitle: Text('${entry.wins} wins'),
                    trailing: Text('${entry.score}'),
                  ),
                ),
          ],
        ],
      ),
    );
  }
}

final class _GameCard extends StatelessWidget {
  const _GameCard({required this.game, required this.onOpen});

  final CivinGame game;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
        leading: CircleAvatar(
          backgroundColor: colors.primaryContainer,
          foregroundColor: colors.onPrimaryContainer,
          child: Icon(_iconFor(game.kind)),
        ),
        title: Text(game.name),
        subtitle: Text(
          game.description?.trim().isNotEmpty == true
              ? game.description!
              : '${game.minBetCoins}–${game.maxBetCoins} coins',
        ),
        trailing: const Icon(Icons.chevron_right_rounded),
        onTap: onOpen,
      ),
    );
  }
}

IconData _iconFor(CivinGameKind kind) => switch (kind) {
  CivinGameKind.luckyWheel => Icons.casino_rounded,
  CivinGameKind.luckyDice => Icons.casino_outlined,
  CivinGameKind.luckyNumber => Icons.pin_rounded,
  CivinGameKind.slotMachine => Icons.view_week_rounded,
  CivinGameKind.treasureBox => Icons.inventory_2_rounded,
  CivinGameKind.quiz => Icons.quiz_rounded,
  CivinGameKind.rockPaperScissors => Icons.back_hand_rounded,
  CivinGameKind.redEnvelope => Icons.redeem_rounded,
  CivinGameKind.lottery => Icons.confirmation_number_rounded,
  CivinGameKind.ludo => Icons.sports_esports_rounded,
  CivinGameKind.other => Icons.sports_esports_outlined,
};
