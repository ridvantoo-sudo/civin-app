import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/games/data/repositories/game_repository_impl.dart';
import 'package:civin/features/games/domain/entities/civin_game.dart';
import 'package:civin/features/games/domain/repositories/game_repository.dart';
import 'package:civin/features/games/domain/usecases/game_usecases.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<ListGames> listGamesUseCaseProvider = Provider<ListGames>(
  (Ref ref) => ListGames(ref.watch(gameRepositoryProvider)),
);

final Provider<GetGame> getGameUseCaseProvider = Provider<GetGame>(
  (Ref ref) => GetGame(ref.watch(gameRepositoryProvider)),
);

final Provider<PlayCivinGame> playGameUseCaseProvider = Provider<PlayCivinGame>(
  (Ref ref) => PlayCivinGame(ref.watch(gameRepositoryProvider)),
);

final Provider<GetGameHistory> gameHistoryUseCaseProvider =
    Provider<GetGameHistory>(
      (Ref ref) => GetGameHistory(ref.watch(gameRepositoryProvider)),
    );

final Provider<GetGameLeaderboard> gameLeaderboardUseCaseProvider =
    Provider<GetGameLeaderboard>(
      (Ref ref) => GetGameLeaderboard(ref.watch(gameRepositoryProvider)),
    );

final Provider<GetQuizQuestion> quizQuestionUseCaseProvider =
    Provider<GetQuizQuestion>(
      (Ref ref) => GetQuizQuestion(ref.watch(gameRepositoryProvider)),
    );

final Provider<GetCurrentLottery> currentLotteryUseCaseProvider =
    Provider<GetCurrentLottery>(
      (Ref ref) => GetCurrentLottery(ref.watch(gameRepositoryProvider)),
    );

final Provider<CreateCivinMatch> createMatchUseCaseProvider =
    Provider<CreateCivinMatch>(
      (Ref ref) => CreateCivinMatch(ref.watch(gameRepositoryProvider)),
    );

final Provider<JoinCivinMatch> joinMatchUseCaseProvider =
    Provider<JoinCivinMatch>(
      (Ref ref) => JoinCivinMatch(ref.watch(gameRepositoryProvider)),
    );

final Provider<ActOnCivinMatch> matchActionUseCaseProvider =
    Provider<ActOnCivinMatch>(
      (Ref ref) => ActOnCivinMatch(ref.watch(gameRepositoryProvider)),
    );

final NotifierProvider<GameCatalogController, GameCatalogState>
gameCatalogProvider = NotifierProvider<GameCatalogController, GameCatalogState>(
  GameCatalogController.new,
);

final gamePlayProvider =
    NotifierProvider.family<GamePlayController, GamePlayState, String>(
      GamePlayController.new,
    );

final class GameCatalogState {
  const GameCatalogState({
    this.games = const <CivinGame>[],
    this.leaders = const <GameLeaderEntry>[],
    this.isLoading = false,
    this.errorMessage,
  });

  final List<CivinGame> games;
  final List<GameLeaderEntry> leaders;
  final bool isLoading;
  final String? errorMessage;

  GameCatalogState copyWith({
    List<CivinGame>? games,
    List<GameLeaderEntry>? leaders,
    bool? isLoading,
    String? errorMessage,
    bool clearError = false,
  }) => GameCatalogState(
    games: games ?? this.games,
    leaders: leaders ?? this.leaders,
    isLoading: isLoading ?? this.isLoading,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
  );
}

final class GameCatalogController extends Notifier<GameCatalogState> {
  @override
  GameCatalogState build() => const GameCatalogState();

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    final RepositoryResult<List<CivinGame>> games = await ref.read(
      listGamesUseCaseProvider,
    )();
    final RepositoryResult<List<GameLeaderEntry>> leaders = await ref.read(
      gameLeaderboardUseCaseProvider,
    )();
    if (!ref.mounted) return;
    games.fold(
      onSuccess: (List<CivinGame> value) {
        state = state.copyWith(
          isLoading: false,
          games: value,
          clearError: true,
        );
      },
      onFailure: (failure) {
        state = state.copyWith(isLoading: false, errorMessage: failure.message);
      },
    );
    leaders.fold(
      onSuccess: (List<GameLeaderEntry> value) {
        state = state.copyWith(leaders: value);
      },
      onFailure: (_) {},
    );
  }
}

final class GamePlayState {
  const GamePlayState({
    required this.slug,
    this.game,
    this.quiz,
    this.lottery,
    this.lastRound,
    this.history = const <GameRoundResult>[],
    this.leaders = const <GameLeaderEntry>[],
    this.match,
    this.isLoading = false,
    this.isPlaying = false,
    this.errorMessage,
  });

  final String slug;
  final CivinGame? game;
  final QuizPrompt? quiz;
  final LotteryRoundInfo? lottery;
  final GameRoundResult? lastRound;
  final List<GameRoundResult> history;
  final List<GameLeaderEntry> leaders;
  final CivinMatch? match;
  final bool isLoading;
  final bool isPlaying;
  final String? errorMessage;

  GamePlayState copyWith({
    CivinGame? game,
    QuizPrompt? quiz,
    LotteryRoundInfo? lottery,
    GameRoundResult? lastRound,
    List<GameRoundResult>? history,
    List<GameLeaderEntry>? leaders,
    CivinMatch? match,
    bool? isLoading,
    bool? isPlaying,
    String? errorMessage,
    bool clearError = false,
    bool clearQuiz = false,
  }) => GamePlayState(
    slug: slug,
    game: game ?? this.game,
    quiz: clearQuiz ? quiz : quiz ?? this.quiz,
    lottery: lottery ?? this.lottery,
    lastRound: lastRound ?? this.lastRound,
    history: history ?? this.history,
    leaders: leaders ?? this.leaders,
    match: match ?? this.match,
    isLoading: isLoading ?? this.isLoading,
    isPlaying: isPlaying ?? this.isPlaying,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
  );
}

final class GamePlayController extends Notifier<GamePlayState> {
  GamePlayController(this.slug);

  final String slug;

  @override
  GamePlayState build() => GamePlayState(slug: slug);

  Future<void> load({CivinGame? seeded}) async {
    state = state.copyWith(
      isLoading: state.game == null && seeded == null,
      game: seeded,
      clearError: true,
    );
    if (seeded == null || seeded.slug != slug) {
      final RepositoryResult<CivinGame> game = await ref.read(
        getGameUseCaseProvider,
      )(slug);
      if (!ref.mounted) return;
      game.fold(
        onSuccess: (CivinGame value) {
          state = state.copyWith(
            game: value,
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
    } else {
      state = state.copyWith(isLoading: false);
    }
    if (!ref.mounted || state.game == null) return;
    await Future.wait(<Future<void>>[
      _loadHistory(),
      _loadLeaders(),
      if (state.game!.kind == CivinGameKind.quiz) loadQuiz(),
      if (state.game!.kind == CivinGameKind.lottery) loadLottery(),
    ]);
  }

  Future<bool> play({
    required int betCoins,
    Map<String, Object?> input = const <String, Object?>{},
    Map<String, Object?>? metadata,
  }) async {
    state = state.copyWith(isPlaying: true, clearError: true);
    final RepositoryResult<GameRoundResult> result =
        await ref.read(playGameUseCaseProvider)(
          PlayGameCommand(
            gameSlug: slug,
            betCoins: betCoins,
            input: input,
            metadata: metadata,
          ),
        );
    if (!ref.mounted) return false;
    return result.fold(
      onSuccess: (GameRoundResult value) {
        state = state.copyWith(
          isPlaying: false,
          lastRound: value,
          history: <GameRoundResult>[value, ...state.history],
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isPlaying: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<void> loadQuiz() async {
    final RepositoryResult<QuizPrompt> result = await ref.read(
      quizQuestionUseCaseProvider,
    )();
    if (!ref.mounted) return;
    result.fold(
      onSuccess: (QuizPrompt value) {
        state = state.copyWith(quiz: value, clearError: true);
      },
      onFailure: (failure) {
        state = state.copyWith(errorMessage: failure.message);
      },
    );
  }

  Future<void> loadLottery() async {
    final RepositoryResult<LotteryRoundInfo> result = await ref.read(
      currentLotteryUseCaseProvider,
    )();
    if (!ref.mounted) return;
    result.fold(
      onSuccess: (LotteryRoundInfo value) {
        state = state.copyWith(lottery: value, clearError: true);
      },
      onFailure: (failure) {
        state = state.copyWith(errorMessage: failure.message);
      },
    );
  }

  Future<bool> createMatch({
    required int stakeCoins,
    int maxPlayers = 4,
    Map<String, Object?>? metadata,
  }) async {
    state = state.copyWith(isPlaying: true, clearError: true);
    final RepositoryResult<CivinMatch> result =
        await ref.read(createMatchUseCaseProvider)(
          gameSlug: slug,
          stakeCoins: stakeCoins,
          maxPlayers: maxPlayers,
          metadata: metadata,
        );
    return _storeMatch(result);
  }

  Future<bool> joinMatch(String matchId) async {
    state = state.copyWith(isPlaying: true, clearError: true);
    final RepositoryResult<CivinMatch> result = await ref.read(
      joinMatchUseCaseProvider,
    )(matchId);
    return _storeMatch(result);
  }

  Future<bool> roll() async {
    final String? matchId = state.match?.id;
    if (matchId == null) return false;
    state = state.copyWith(isPlaying: true, clearError: true);
    final RepositoryResult<CivinMatch> result = await ref.read(
      matchActionUseCaseProvider,
    )(matchId, action: 'roll');
    return _storeMatch(result);
  }

  bool _storeMatch(RepositoryResult<CivinMatch> result) {
    if (!ref.mounted) return false;
    return result.fold(
      onSuccess: (CivinMatch value) {
        state = state.copyWith(
          isPlaying: false,
          match: value,
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isPlaying: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<void> _loadHistory() async {
    final RepositoryResult<List<GameRoundResult>> result = await ref.read(
      gameHistoryUseCaseProvider,
    )(gameSlug: slug);
    if (!ref.mounted) return;
    result.fold(
      onSuccess: (List<GameRoundResult> value) {
        state = state.copyWith(history: value);
      },
      onFailure: (_) {},
    );
  }

  Future<void> _loadLeaders() async {
    final RepositoryResult<List<GameLeaderEntry>> result = await ref.read(
      gameLeaderboardUseCaseProvider,
    )(gameSlug: slug);
    if (!ref.mounted) return;
    result.fold(
      onSuccess: (List<GameLeaderEntry> value) {
        state = state.copyWith(leaders: value);
      },
      onFailure: (_) {},
    );
  }
}
