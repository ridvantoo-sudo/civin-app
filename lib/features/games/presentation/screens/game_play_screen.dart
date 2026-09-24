import 'dart:async';

import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/games/domain/entities/civin_game.dart';
import 'package:civin/features/games/presentation/game_providers.dart';
import 'package:civin/features/wallet/presentation/wallet_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class GamePlayScreen extends ConsumerStatefulWidget {
  const GamePlayScreen({required this.slug, this.seed, this.roomId, super.key});

  final String slug;
  final CivinGame? seed;
  final String? roomId;

  @override
  ConsumerState<GamePlayScreen> createState() => _GamePlayScreenState();
}

final class _GamePlayScreenState extends ConsumerState<GamePlayScreen> {
  final TextEditingController _envelopeId = TextEditingController();
  final TextEditingController _envelopeMessage = TextEditingController();
  final TextEditingController _matchId = TextEditingController();

  int _bet = 0;
  int _diceFace = 1;
  bool _exactDice = true;
  int _number = 1;
  String _tier = 'bronze';
  int? _answer;
  String _choice = 'rock';
  bool _creatingEnvelope = true;
  int _slots = 5;
  bool _booted = false;

  @override
  void initState() {
    super.initState();
    _bet = widget.seed?.minBetCoins ?? 0;
    _tier = widget.seed?.treasureTiers.first ?? 'bronze';
    WidgetsBinding.instance.addPostFrameCallback((_) {
      unawaited(_boot());
    });
  }

  @override
  void dispose() {
    _envelopeId.dispose();
    _envelopeMessage.dispose();
    _matchId.dispose();
    super.dispose();
  }

  Future<void> _boot() async {
    await ref
        .read(gamePlayProvider(widget.slug).notifier)
        .load(seeded: widget.seed);
    if (!mounted) return;
    final CivinGame? game = ref.read(gamePlayProvider(widget.slug)).game;
    setState(() {
      _booted = true;
      _bet = game?.minBetCoins ?? 0;
      _tier = game?.treasureTiers.first ?? 'bronze';
    });
  }

  void _showHint(String message) {
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(message)));
  }

  Map<String, Object?>? get _roomMetadata {
    final String? roomId = widget.roomId;
    if (roomId == null || roomId.isEmpty) return null;
    return <String, Object?>{'room_id': roomId};
  }

  Future<void> _syncWallet() => ref.read(walletProvider.notifier).refresh();

  Future<void> _play() async {
    final GamePlayState view = ref.read(gamePlayProvider(widget.slug));
    final CivinGame? game = view.game;
    if (game == null) return;
    if (game.kind == CivinGameKind.quiz && _answer == null) {
      _showHint('Choose an answer first.');
      return;
    }
    if (game.kind == CivinGameKind.redEnvelope &&
        !_creatingEnvelope &&
        _envelopeId.text.trim().isEmpty) {
      _showHint('Enter the envelope id to claim it.');
      return;
    }
    final Map<String, Object?> input = _inputFor(game, view);
    final int bet = switch (game.kind) {
      CivinGameKind.quiz => view.quiz?.betCoins ?? game.minBetCoins,
      CivinGameKind.lottery => view.lottery?.ticketPrice ?? game.minBetCoins,
      CivinGameKind.redEnvelope when _creatingEnvelope => _bet,
      _ => _bet,
    };
    final bool ok = await ref
        .read(gamePlayProvider(widget.slug).notifier)
        .play(betCoins: bet, input: input, metadata: _roomMetadata);
    if (!mounted || !ok) return;
    final GameRoundResult? round = ref
        .read(gamePlayProvider(widget.slug))
        .lastRound;
    if (round?.envelopeId != null) {
      _envelopeId.text = round!.envelopeId!;
    }
    if (game.kind == CivinGameKind.quiz) {
      setState(() => _answer = null);
      await ref.read(gamePlayProvider(widget.slug).notifier).loadQuiz();
    }
    await _syncWallet();
  }

  Map<String, Object?> _inputFor(CivinGame game, GamePlayState view) {
    return switch (game.kind) {
      CivinGameKind.luckyDice =>
        _exactDice
            ? <String, Object?>{'guess': _diceFace}
            : <String, Object?>{'prediction': _diceFace >= 4 ? 'high' : 'low'},
      CivinGameKind.luckyNumber => <String, Object?>{'number': _number},
      CivinGameKind.treasureBox => <String, Object?>{'box_tier': _tier},
      CivinGameKind.quiz => <String, Object?>{
        'question_id': view.quiz?.id ?? '',
        'answer_index': _answer ?? -1,
      },
      CivinGameKind.rockPaperScissors => <String, Object?>{'choice': _choice},
      CivinGameKind.redEnvelope =>
        _creatingEnvelope
            ? <String, Object?>{
                'action': 'create',
                'total_coins': _bet,
                'total_slots': _slots,
                'message': _envelopeMessage.text.trim(),
              }
            : <String, Object?>{
                'action': 'claim',
                'envelope_id': _envelopeId.text.trim(),
              },
      CivinGameKind.lottery => const <String, Object?>{'action': 'buy'},
      _ => const <String, Object?>{},
    };
  }

  @override
  Widget build(BuildContext context) {
    final GamePlayState view = ref.watch(gamePlayProvider(widget.slug));
    final CivinGame? game = view.game;
    return Scaffold(
      appBar: AppBar(title: Text(game?.name ?? 'Game')),
      body: !_booted && view.isLoading
          ? const AppLoadingWidget(message: 'Loading game')
          : game == null
          ? AppErrorWidget(
              message: view.errorMessage ?? 'This game is unavailable.',
              onRetry: _boot,
            )
          : ListView(
              padding: const EdgeInsets.fromLTRB(16, 12, 16, 32),
              children: <Widget>[
                if (game.description?.trim().isNotEmpty == true)
                  Text(game.description!),
                const SizedBox(height: 12),
                if (view.errorMessage != null)
                  Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: Text(
                      view.errorMessage!,
                      style: TextStyle(
                        color: Theme.of(context).colorScheme.error,
                      ),
                    ),
                  ),
                if (view.lastRound != null) _ResultCard(round: view.lastRound!),
                if (game.kind == CivinGameKind.ludo)
                  _LudoPanel(
                    view: view,
                    stake: _bet,
                    minBet: game.minBetCoins,
                    maxBet: game.maxBetCoins,
                    matchId: _matchId,
                    onStake: (int value) => setState(() => _bet = value),
                    onCreate: () async {
                      final bool ok = await ref
                          .read(gamePlayProvider(widget.slug).notifier)
                          .createMatch(
                            stakeCoins: _bet,
                            metadata: _roomMetadata,
                          );
                      if (ok) await _syncWallet();
                    },
                    onJoin: () async {
                      final bool ok = await ref
                          .read(gamePlayProvider(widget.slug).notifier)
                          .joinMatch(_matchId.text.trim());
                      if (ok) await _syncWallet();
                    },
                    onRoll: () async {
                      final bool ok = await ref
                          .read(gamePlayProvider(widget.slug).notifier)
                          .roll();
                      if (ok) await _syncWallet();
                    },
                  )
                else ...<Widget>[
                  _PlayForm(
                    game: game,
                    view: view,
                    bet: _bet,
                    diceFace: _diceFace,
                    exactDice: _exactDice,
                    number: _number,
                    tier: _tier,
                    answer: _answer,
                    choice: _choice,
                    creatingEnvelope: _creatingEnvelope,
                    slots: _slots,
                    envelopeId: _envelopeId,
                    envelopeMessage: _envelopeMessage,
                    onBet: (int value) => setState(() => _bet = value),
                    onDiceFace: (int value) =>
                        setState(() => _diceFace = value),
                    onExactDice: (bool value) =>
                        setState(() => _exactDice = value),
                    onNumber: (int value) => setState(() => _number = value),
                    onTier: (String value) => setState(() => _tier = value),
                    onAnswer: (int value) => setState(() => _answer = value),
                    onChoice: (String value) => setState(() => _choice = value),
                    onCreatingEnvelope: (bool value) =>
                        setState(() => _creatingEnvelope = value),
                    onSlots: (int value) => setState(() => _slots = value),
                  ),
                  const SizedBox(height: 16),
                  FilledButton.icon(
                    onPressed: view.isPlaying ? null : () => unawaited(_play()),
                    icon: view.isPlaying
                        ? const SizedBox.square(
                            dimension: 18,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : const Icon(Icons.play_arrow_rounded),
                    label: Text(_buttonLabel(game)),
                  ),
                ],
                if (view.history.isNotEmpty) ...<Widget>[
                  const SizedBox(height: 24),
                  Text(
                    'Recent rounds',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  ...view.history
                      .take(8)
                      .map(
                        (GameRoundResult round) => ListTile(
                          contentPadding: EdgeInsets.zero,
                          title: Text(round.headline),
                          subtitle: Text(
                            round.detail.isEmpty
                                ? '${round.betCoins} coins bet'
                                : round.detail,
                          ),
                        ),
                      ),
                ],
              ],
            ),
    );
  }

  String _buttonLabel(CivinGame game) => switch (game.kind) {
    CivinGameKind.luckyWheel => 'Spin',
    CivinGameKind.slotMachine => 'Spin reels',
    CivinGameKind.quiz => 'Submit answer',
    CivinGameKind.redEnvelope =>
      _creatingEnvelope ? 'Send envelope' : 'Claim envelope',
    CivinGameKind.lottery => 'Buy ticket',
    _ => 'Play',
  };
}

final class _ResultCard extends StatelessWidget {
  const _ResultCard({required this.round});

  final GameRoundResult round;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final Color background = round.won
        ? colors.primaryContainer
        : colors.surfaceContainerHighest;
    return Card(
      color: background,
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: <Widget>[
            Text(
              round.headline,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            if (round.detail.isNotEmpty) ...<Widget>[
              const SizedBox(height: 6),
              Text(round.detail),
            ],
            const SizedBox(height: 6),
            Text(
              'Wallet ${round.coinsBalance} coins · ${round.diamondsBalance} diamonds',
            ),
          ],
        ),
      ),
    );
  }
}

final class _PlayForm extends StatelessWidget {
  const _PlayForm({
    required this.game,
    required this.view,
    required this.bet,
    required this.diceFace,
    required this.exactDice,
    required this.number,
    required this.tier,
    required this.answer,
    required this.choice,
    required this.creatingEnvelope,
    required this.slots,
    required this.envelopeId,
    required this.envelopeMessage,
    required this.onBet,
    required this.onDiceFace,
    required this.onExactDice,
    required this.onNumber,
    required this.onTier,
    required this.onAnswer,
    required this.onChoice,
    required this.onCreatingEnvelope,
    required this.onSlots,
  });

  final CivinGame game;
  final GamePlayState view;
  final int bet;
  final int diceFace;
  final bool exactDice;
  final int number;
  final String tier;
  final int? answer;
  final String choice;
  final bool creatingEnvelope;
  final int slots;
  final TextEditingController envelopeId;
  final TextEditingController envelopeMessage;
  final ValueChanged<int> onBet;
  final ValueChanged<int> onDiceFace;
  final ValueChanged<bool> onExactDice;
  final ValueChanged<int> onNumber;
  final ValueChanged<String> onTier;
  final ValueChanged<int> onAnswer;
  final ValueChanged<String> onChoice;
  final ValueChanged<bool> onCreatingEnvelope;
  final ValueChanged<int> onSlots;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        if (game.kind.usesBet &&
            !(game.kind == CivinGameKind.redEnvelope && !creatingEnvelope))
          _BetSlider(
            min: game.minBetCoins,
            max: game.maxBetCoins,
            value: bet,
            onChanged: onBet,
          ),
        switch (game.kind) {
          CivinGameKind.luckyDice => Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              SegmentedButton<bool>(
                segments: const <ButtonSegment<bool>>[
                  ButtonSegment<bool>(value: true, label: Text('Exact')),
                  ButtonSegment<bool>(value: false, label: Text('High / low')),
                ],
                selected: <bool>{exactDice},
                onSelectionChanged: (Set<bool> value) =>
                    onExactDice(value.first),
              ),
              const SizedBox(height: 12),
              if (exactDice)
                Wrap(
                  spacing: 8,
                  children: <Widget>[
                    for (int face = 1; face <= 6; face++)
                      ChoiceChip(
                        label: Text('$face'),
                        selected: diceFace == face,
                        onSelected: (_) => onDiceFace(face),
                      ),
                  ],
                )
              else
                SegmentedButton<int>(
                  segments: const <ButtonSegment<int>>[
                    ButtonSegment<int>(value: 1, label: Text('Low 1–3')),
                    ButtonSegment<int>(value: 6, label: Text('High 4–6')),
                  ],
                  selected: <int>{diceFace >= 4 ? 6 : 1},
                  onSelectionChanged: (Set<int> value) =>
                      onDiceFace(value.first),
                ),
            ],
          ),
          CivinGameKind.luckyNumber => Builder(
            builder: (BuildContext context) {
              final int maxNumber = game
                  .configInt('max_number', fallback: 10)
                  .clamp(1, 100);
              final int picked = number.clamp(1, maxNumber);
              return Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Text('Pick 1–$maxNumber'),
                  Slider(
                    min: 1,
                    max: maxNumber.toDouble(),
                    divisions: maxNumber <= 1 ? null : maxNumber - 1,
                    label: '$picked',
                    value: picked.toDouble(),
                    onChanged: maxNumber <= 1
                        ? null
                        : (double value) => onNumber(value.round()),
                  ),
                ],
              );
            },
          ),
          CivinGameKind.treasureBox => Wrap(
            spacing: 8,
            children: <Widget>[
              for (final String item in game.treasureTiers)
                ChoiceChip(
                  label: Text(item),
                  selected: tier == item,
                  onSelected: (_) => onTier(item),
                ),
            ],
          ),
          CivinGameKind.quiz => _QuizFields(
            prompt: view.quiz,
            answer: answer,
            onAnswer: onAnswer,
          ),
          CivinGameKind.rockPaperScissors => SegmentedButton<String>(
            segments: const <ButtonSegment<String>>[
              ButtonSegment<String>(value: 'rock', label: Text('Rock')),
              ButtonSegment<String>(value: 'paper', label: Text('Paper')),
              ButtonSegment<String>(value: 'scissors', label: Text('Scissors')),
            ],
            selected: <String>{choice},
            onSelectionChanged: (Set<String> value) => onChoice(value.first),
          ),
          CivinGameKind.redEnvelope => Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: <Widget>[
              SegmentedButton<bool>(
                segments: const <ButtonSegment<bool>>[
                  ButtonSegment<bool>(value: true, label: Text('Send')),
                  ButtonSegment<bool>(value: false, label: Text('Claim')),
                ],
                selected: <bool>{creatingEnvelope},
                onSelectionChanged: (Set<bool> value) =>
                    onCreatingEnvelope(value.first),
              ),
              const SizedBox(height: 12),
              if (creatingEnvelope) ...<Widget>[
                Text('Slots: $slots'),
                Slider(
                  min: 1,
                  max: 20,
                  divisions: 19,
                  label: '$slots',
                  value: slots.toDouble(),
                  onChanged: (double value) => onSlots(value.round()),
                ),
                TextField(
                  controller: envelopeMessage,
                  decoration: const InputDecoration(
                    labelText: 'Message',
                    border: OutlineInputBorder(),
                  ),
                ),
              ] else
                TextField(
                  controller: envelopeId,
                  decoration: const InputDecoration(
                    labelText: 'Envelope id',
                    border: OutlineInputBorder(),
                  ),
                ),
            ],
          ),
          CivinGameKind.lottery => Text(
            view.lottery == null
                ? 'Loading the current draw…'
                : 'Ticket ${view.lottery!.ticketPrice} coins · pick ${view.lottery!.picks} numbers up to ${view.lottery!.maxNumber}. A quick pick is chosen for you.',
          ),
          _ => const SizedBox.shrink(),
        },
      ],
    );
  }
}

final class _BetSlider extends StatelessWidget {
  const _BetSlider({
    required this.min,
    required this.max,
    required this.value,
    required this.onChanged,
  });

  final int min;
  final int max;
  final int value;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) {
    final int safeMax = max < min ? min : max;
    final int safeValue = value.clamp(min, safeMax);
    final int span = safeMax - min;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text('Bet $safeValue coins'),
        Slider(
          min: min.toDouble(),
          max: safeMax.toDouble(),
          divisions: span == 0 ? null : span.clamp(1, 100),
          label: '$safeValue',
          value: safeValue.toDouble(),
          onChanged: span == 0
              ? null
              : (double next) => onChanged(next.round()),
        ),
      ],
    );
  }
}

final class _QuizFields extends StatelessWidget {
  const _QuizFields({
    required this.prompt,
    required this.answer,
    required this.onAnswer,
  });

  final QuizPrompt? prompt;
  final int? answer;
  final ValueChanged<int> onAnswer;

  @override
  Widget build(BuildContext context) {
    final QuizPrompt? question = prompt;
    if (question == null) {
      return const Text('Loading a question…');
    }
    return RadioGroup<int>(
      groupValue: answer,
      onChanged: (int? value) {
        if (value != null) onAnswer(value);
      },
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            question.question,
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 8),
          for (int index = 0; index < question.options.length; index++)
            RadioListTile<int>(
              value: index,
              title: Text(question.options[index]),
            ),
        ],
      ),
    );
  }
}

final class _LudoPanel extends StatelessWidget {
  const _LudoPanel({
    required this.view,
    required this.stake,
    required this.minBet,
    required this.maxBet,
    required this.matchId,
    required this.onStake,
    required this.onCreate,
    required this.onJoin,
    required this.onRoll,
  });

  final GamePlayState view;
  final int stake;
  final int minBet;
  final int maxBet;
  final TextEditingController matchId;
  final ValueChanged<int> onStake;
  final Future<void> Function() onCreate;
  final Future<void> Function() onJoin;
  final Future<void> Function() onRoll;

  @override
  Widget build(BuildContext context) {
    final CivinMatch? match = view.match;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        const Text(
          'Open a table or join with a match id. Each roll moves you toward the finish.',
        ),
        _BetSlider(min: minBet, max: maxBet, value: stake, onChanged: onStake),
        FilledButton(
          onPressed: view.isPlaying ? null : () => unawaited(onCreate()),
          child: const Text('Create table'),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: matchId,
          decoration: const InputDecoration(
            labelText: 'Match id',
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 8),
        OutlinedButton(
          onPressed: view.isPlaying ? null : () => unawaited(onJoin()),
          child: const Text('Join table'),
        ),
        if (match != null) ...<Widget>[
          const SizedBox(height: 16),
          ListTile(
            contentPadding: EdgeInsets.zero,
            title: Text('Table ${match.id}'),
            subtitle: Text(
              '${match.status} · stake ${match.stakeCoins}${match.lastRollLabel == null ? '' : ' · ${match.lastRollLabel}'}',
            ),
            trailing: IconButton(
              tooltip: 'Copy match id',
              onPressed: () {
                unawaited(Clipboard.setData(ClipboardData(text: match.id)));
              },
              icon: const Icon(Icons.copy_rounded),
            ),
          ),
          ...match.players.map(
            (CivinMatchPlayer player) => ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(player.displayName),
              subtitle: Text('Seat ${player.seat} · ${player.status}'),
              trailing: Text('${player.score}'),
            ),
          ),
          FilledButton.icon(
            onPressed: view.isPlaying ? null : () => unawaited(onRoll()),
            icon: const Icon(Icons.casino_rounded),
            label: const Text('Roll'),
          ),
        ],
      ],
    );
  }
}
