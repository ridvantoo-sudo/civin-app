import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/challenges/data/repositories/challenge_repository_impl.dart';
import 'package:civin/features/challenges/domain/entities/challenge.dart';
import 'package:civin/features/challenges/domain/usecases/challenge_usecases.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<ListChallenges> listChallengesUseCaseProvider =
    Provider<ListChallenges>(
      (Ref ref) => ListChallenges(ref.watch(challengeRepositoryProvider)),
    );

final Provider<ClaimChallenge> claimChallengeUseCaseProvider =
    Provider<ClaimChallenge>(
      (Ref ref) => ClaimChallenge(ref.watch(challengeRepositoryProvider)),
    );

final NotifierProvider<ChallengeController, ChallengeViewState>
challengeProvider = NotifierProvider<ChallengeController, ChallengeViewState>(
  ChallengeController.new,
);

final class ChallengeViewState {
  const ChallengeViewState({
    this.daily = const <CivinChallenge>[],
    this.weekly = const <CivinChallenge>[],
    this.isLoading = false,
    this.claimingId,
    this.errorMessage,
    this.notice,
  });

  final List<CivinChallenge> daily;
  final List<CivinChallenge> weekly;
  final bool isLoading;
  final String? claimingId;
  final String? errorMessage;
  final String? notice;

  ChallengeViewState copyWith({
    List<CivinChallenge>? daily,
    List<CivinChallenge>? weekly,
    bool? isLoading,
    String? claimingId,
    String? errorMessage,
    String? notice,
    bool clearError = false,
    bool clearNotice = false,
    bool clearClaiming = false,
  }) => ChallengeViewState(
    daily: daily ?? this.daily,
    weekly: weekly ?? this.weekly,
    isLoading: isLoading ?? this.isLoading,
    claimingId: clearClaiming ? null : claimingId ?? this.claimingId,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
    notice: clearNotice ? null : notice ?? this.notice,
  );
}

final class ChallengeController extends Notifier<ChallengeViewState> {
  @override
  ChallengeViewState build() => const ChallengeViewState();

  Future<void> load() async {
    state = state.copyWith(
      isLoading: true,
      clearError: true,
      clearNotice: true,
    );
    final RepositoryResult<List<CivinChallenge>> daily = await ref.read(
      listChallengesUseCaseProvider,
    )(period: 'daily');
    final RepositoryResult<List<CivinChallenge>> weekly = await ref.read(
      listChallengesUseCaseProvider,
    )(period: 'weekly');
    if (!ref.mounted) return;
    String? error;
    List<CivinChallenge> dailyItems = state.daily;
    List<CivinChallenge> weeklyItems = state.weekly;
    daily.fold(
      onSuccess: (List<CivinChallenge> value) => dailyItems = value,
      onFailure: (failure) => error = failure.message,
    );
    weekly.fold(
      onSuccess: (List<CivinChallenge> value) => weeklyItems = value,
      onFailure: (failure) => error ??= failure.message,
    );
    state = state.copyWith(
      isLoading: false,
      daily: dailyItems,
      weekly: weeklyItems,
      errorMessage: error,
      clearError: error == null,
    );
  }

  Future<void> claim(String challengeId) async {
    state = state.copyWith(
      claimingId: challengeId,
      clearError: true,
      clearNotice: true,
    );
    final RepositoryResult<ChallengeClaim> result = await ref.read(
      claimChallengeUseCaseProvider,
    )(challengeId);
    if (!ref.mounted) return;
    result.fold(
      onSuccess: (ChallengeClaim value) {
        final String reward = <String>[
          if (value.coinsAwarded > 0) '${value.coinsAwarded} coins',
          if (value.diamondsAwarded > 0) '${value.diamondsAwarded} diamonds',
          if (value.xpAwarded > 0) '${value.xpAwarded} XP',
        ].join(' · ');
        state = state.copyWith(
          clearClaiming: true,
          notice: reward.isEmpty ? 'Reward claimed' : 'Claimed $reward',
        );
      },
      onFailure: (failure) {
        state = state.copyWith(
          clearClaiming: true,
          errorMessage: failure.message,
        );
      },
    );
    final String? notice = state.notice;
    await load();
    if (!ref.mounted || notice == null) return;
    state = state.copyWith(notice: notice);
  }
}
