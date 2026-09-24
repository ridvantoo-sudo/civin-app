import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/family/data/repositories/family_repository_impl.dart';
import 'package:civin/features/family/domain/entities/family_club.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final NotifierProvider<FamilyController, FamilyViewState> familyProvider =
    NotifierProvider<FamilyController, FamilyViewState>(FamilyController.new);

final class FamilyViewState {
  const FamilyViewState({
    this.family,
    this.rankings = const <FamilyClub>[],
    this.members = const <FamilyMemberView>[],
    this.messages = const <FamilyMessageView>[],
    this.missions = const <FamilyMissionView>[],
    this.inviteCode,
    this.isLoading = false,
    this.isSaving = false,
    this.errorMessage,
  });

  final FamilyClub? family;
  final List<FamilyClub> rankings;
  final List<FamilyMemberView> members;
  final List<FamilyMessageView> messages;
  final List<FamilyMissionView> missions;
  final String? inviteCode;
  final bool isLoading;
  final bool isSaving;
  final String? errorMessage;

  FamilyViewState copyWith({
    FamilyClub? family,
    List<FamilyClub>? rankings,
    List<FamilyMemberView>? members,
    List<FamilyMessageView>? messages,
    List<FamilyMissionView>? missions,
    String? inviteCode,
    bool? isLoading,
    bool? isSaving,
    String? errorMessage,
    bool clearFamily = false,
    bool clearError = false,
    bool clearInvite = false,
  }) => FamilyViewState(
    family: clearFamily ? null : family ?? this.family,
    rankings: rankings ?? this.rankings,
    members: members ?? this.members,
    messages: messages ?? this.messages,
    missions: missions ?? this.missions,
    inviteCode: clearInvite ? null : inviteCode ?? this.inviteCode,
    isLoading: isLoading ?? this.isLoading,
    isSaving: isSaving ?? this.isSaving,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
  );
}

final class FamilyController extends Notifier<FamilyViewState> {
  @override
  FamilyViewState build() => const FamilyViewState();

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    final repository = ref.read(familyRepositoryProvider);
    final RepositoryResult<FamilyClub?> mine = await repository.mine();
    final RepositoryResult<List<FamilyClub>> rankings = await repository
        .rankings();
    if (!ref.mounted) return;
    FamilyClub? club;
    String? error;
    mine.fold(
      onSuccess: (FamilyClub? value) => club = value,
      onFailure: (failure) => error = failure.message,
    );
    List<FamilyClub> boards = state.rankings;
    rankings.fold(
      onSuccess: (List<FamilyClub> value) => boards = value,
      onFailure: (failure) => error ??= failure.message,
    );
    state = state.copyWith(
      isLoading: false,
      family: club,
      clearFamily: club == null,
      rankings: boards,
      errorMessage: error,
      clearError: error == null,
    );
    final String? familyId = club?.id;
    if (familyId != null) await _loadDetails(familyId);
  }

  Future<bool> create({required String name, String? description}) async {
    state = state.copyWith(isSaving: true, clearError: true);
    final RepositoryResult<FamilyClub> result = await ref
        .read(familyRepositoryProvider)
        .create(name: name, description: description);
    if (!ref.mounted) return false;
    final bool created = result.fold(
      onSuccess: (FamilyClub value) {
        state = state.copyWith(
          isSaving: false,
          family: value,
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isSaving: false, errorMessage: failure.message);
        return false;
      },
    );
    final String? familyId = state.family?.id;
    if (created && familyId != null) await _loadDetails(familyId);
    return created;
  }

  Future<bool> join(String code) async {
    state = state.copyWith(isSaving: true, clearError: true);
    final RepositoryResult<void> result = await ref
        .read(familyRepositoryProvider)
        .join(code);
    if (!ref.mounted) return false;
    final bool ok = result.fold(
      onSuccess: (_) {
        state = state.copyWith(isSaving: false, clearError: true);
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isSaving: false, errorMessage: failure.message);
        return false;
      },
    );
    if (ok) await load();
    return ok;
  }

  Future<bool> sendMessage(String body) async {
    final String? familyId = state.family?.id;
    if (familyId == null) return false;
    state = state.copyWith(isSaving: true, clearError: true);
    final RepositoryResult<FamilyMessageView> result = await ref
        .read(familyRepositoryProvider)
        .sendMessage(familyId, body);
    if (!ref.mounted) return false;
    return result.fold(
      onSuccess: (FamilyMessageView value) {
        state = state.copyWith(
          isSaving: false,
          messages: <FamilyMessageView>[value, ...state.messages],
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(isSaving: false, errorMessage: failure.message);
        return false;
      },
    );
  }

  Future<void> claimMission(String missionId) async {
    final String? familyId = state.family?.id;
    if (familyId == null) return;
    state = state.copyWith(isSaving: true, clearError: true);
    final RepositoryResult<void> result = await ref
        .read(familyRepositoryProvider)
        .claimMission(familyId, missionId);
    if (!ref.mounted) return;
    result.fold(
      onSuccess: (_) {
        state = state.copyWith(isSaving: false, clearError: true);
      },
      onFailure: (failure) {
        state = state.copyWith(isSaving: false, errorMessage: failure.message);
      },
    );
    await _loadDetails(familyId);
  }

  Future<void> createInvite() async {
    final String? familyId = state.family?.id;
    if (familyId == null) return;
    state = state.copyWith(isSaving: true, clearError: true, clearInvite: true);
    final RepositoryResult<FamilyInviteView> result = await ref
        .read(familyRepositoryProvider)
        .invite(familyId);
    if (!ref.mounted) return;
    result.fold(
      onSuccess: (FamilyInviteView value) {
        state = state.copyWith(
          isSaving: false,
          inviteCode: value.code,
          clearError: true,
        );
      },
      onFailure: (failure) {
        state = state.copyWith(isSaving: false, errorMessage: failure.message);
      },
    );
  }

  Future<void> _loadDetails(String familyId) async {
    final repository = ref.read(familyRepositoryProvider);
    final RepositoryResult<List<FamilyMemberView>> members = await repository
        .members(familyId);
    final RepositoryResult<List<FamilyMessageView>> messages = await repository
        .messages(familyId);
    final RepositoryResult<List<FamilyMissionView>> missions = await repository
        .missions(familyId);
    if (!ref.mounted) return;
    members.fold(
      onSuccess: (List<FamilyMemberView> value) {
        state = state.copyWith(members: value);
      },
      onFailure: (_) {},
    );
    messages.fold(
      onSuccess: (List<FamilyMessageView> value) {
        state = state.copyWith(messages: value);
      },
      onFailure: (_) {},
    );
    missions.fold(
      onSuccess: (List<FamilyMissionView> value) {
        state = state.copyWith(missions: value);
      },
      onFailure: (_) {},
    );
  }
}
