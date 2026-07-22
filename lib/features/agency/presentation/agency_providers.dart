import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/agency/data/repositories/agency_repository_impl.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:civin/features/agency/domain/usecases/agency_usecases.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<CreateAgency> createAgencyUseCaseProvider = Provider<CreateAgency>(
  (Ref ref) => CreateAgency(ref.watch(agencyRepositoryProvider)),
);

final Provider<GetAgency> getAgencyUseCaseProvider = Provider<GetAgency>(
  (Ref ref) => GetAgency(ref.watch(agencyRepositoryProvider)),
);

final Provider<ApplyToAgency> applyToAgencyUseCaseProvider =
    Provider<ApplyToAgency>(
      (Ref ref) => ApplyToAgency(ref.watch(agencyRepositoryProvider)),
    );

final Provider<ApproveAgencyApplication> approveAgencyApplicationUseCaseProvider =
    Provider<ApproveAgencyApplication>(
      (Ref ref) =>
          ApproveAgencyApplication(ref.watch(agencyRepositoryProvider)),
    );

final Provider<RejectAgencyApplication> rejectAgencyApplicationUseCaseProvider =
    Provider<RejectAgencyApplication>(
      (Ref ref) => RejectAgencyApplication(ref.watch(agencyRepositoryProvider)),
    );

final Provider<RemoveAgencyMember> removeAgencyMemberUseCaseProvider =
    Provider<RemoveAgencyMember>(
      (Ref ref) => RemoveAgencyMember(ref.watch(agencyRepositoryProvider)),
    );

final Provider<ListAgencyHosts> listAgencyHostsUseCaseProvider =
    Provider<ListAgencyHosts>(
      (Ref ref) => ListAgencyHosts(ref.watch(agencyRepositoryProvider)),
    );

final Provider<ListAgencyEarnings> listAgencyEarningsUseCaseProvider =
    Provider<ListAgencyEarnings>(
      (Ref ref) => ListAgencyEarnings(ref.watch(agencyRepositoryProvider)),
    );

/// Agency profile, hosts, earnings, and application state.
final NotifierProvider<AgencyController, AgencyViewState> agencyProvider =
    NotifierProvider<AgencyController, AgencyViewState>(AgencyController.new);

final class AgencyController extends Notifier<AgencyViewState> {
  @override
  AgencyViewState build() => const AgencyViewState();

  void selectAgencyId(String? agencyId) {
    final String? trimmed = agencyId?.trim();
    if (trimmed == null || trimmed.isEmpty) {
      state = state.copyWith(clearSelection: true);
      return;
    }
    if (state.selectedAgencyId == trimmed) return;
    state = state.copyWith(selectedAgencyId: trimmed, clearAction: true);
  }

  Future<void> loadAgency([String? agencyId]) async {
    final String? id = (agencyId ?? state.activeAgencyId)?.trim();
    if (id == null || id.isEmpty) {
      state = state.copyWith(errorMessage: 'Enter an agency ID to open.');
      return;
    }

    state = state.copyWith(
      isLoading: true,
      selectedAgencyId: id,
      clearError: true,
      clearAction: true,
    );

    final RepositoryResult<Agency> result = await ref.read(
      getAgencyUseCaseProvider,
    )(id);

    if (!ref.mounted) return;

    result.fold(
      onSuccess: (Agency agency) {
        state = state.copyWith(
          agency: agency,
          selectedAgencyId: agency.id,
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

  Future<void> refresh() async {
    final String? id = state.activeAgencyId;
    if (id == null) return;
    await Future.wait(<Future<void>>[
      loadAgency(id),
      loadHosts(id),
      loadEarnings(id),
    ]);
  }

  Future<bool> createAgency(CreateAgencyInput input) async {
    final String name = input.name.trim();
    if (name.length < 2) {
      state = state.copyWith(
        errorMessage: 'Agency name must be at least 2 characters.',
      );
      return false;
    }

    state = state.copyWith(
      isCreating: true,
      clearError: true,
      clearAction: true,
    );

    final RepositoryResult<Agency> result = await ref.read(
      createAgencyUseCaseProvider,
    )(
      CreateAgencyInput(
        name: name,
        description: input.description?.trim().isEmpty == true
            ? null
            : input.description?.trim(),
        logo: input.logo?.trim().isEmpty == true ? null : input.logo?.trim(),
        commissionRate: input.commissionRate,
      ),
    );

    if (!ref.mounted) return false;

    return result.fold(
      onSuccess: (Agency agency) {
        state = state.copyWith(
          agency: agency,
          selectedAgencyId: agency.id,
          isCreating: false,
          actionMessage: 'Agency created successfully.',
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(
          isCreating: false,
          errorMessage: failure.message,
        );
        return false;
      },
    );
  }

  Future<bool> apply({String? agencyId, String? message}) async {
    final String? id = (agencyId ?? state.activeAgencyId)?.trim();
    if (id == null || id.isEmpty) {
      state = state.copyWith(errorMessage: 'Select an agency to apply.');
      return false;
    }

    state = state.copyWith(
      isApplying: true,
      selectedAgencyId: id,
      clearError: true,
      clearAction: true,
    );

    final RepositoryResult<AgencyMember> result = await ref.read(
      applyToAgencyUseCaseProvider,
    )(
      agencyId: id,
      message: message?.trim().isEmpty == true ? null : message?.trim(),
    );

    if (!ref.mounted) return false;

    return result.fold(
      onSuccess: (AgencyMember member) {
        state = state.copyWith(
          lastApplication: member,
          isApplying: false,
          actionMessage: 'Application submitted.',
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(
          isApplying: false,
          errorMessage: failure.message,
        );
        return false;
      },
    );
  }

  Future<void> loadHosts([String? agencyId]) async {
    final String? id = (agencyId ?? state.activeAgencyId)?.trim();
    if (id == null || id.isEmpty) {
      state = state.copyWith(errorMessage: 'Select an agency to view hosts.');
      return;
    }

    state = state.copyWith(
      isLoadingHosts: true,
      selectedAgencyId: id,
      clearError: true,
    );

    final RepositoryResult<List<AgencyMember>> result = await ref.read(
      listAgencyHostsUseCaseProvider,
    )(id);

    if (!ref.mounted) return;

    result.fold(
      onSuccess: (List<AgencyMember> hosts) {
        state = state.copyWith(
          hosts: List<AgencyMember>.unmodifiable(hosts),
          isLoadingHosts: false,
          clearError: true,
        );
      },
      onFailure: (failure) {
        state = state.copyWith(
          isLoadingHosts: false,
          errorMessage: failure.message,
        );
      },
    );
  }

  Future<void> loadEarnings([String? agencyId, int perPage = 20]) async {
    final String? id = (agencyId ?? state.activeAgencyId)?.trim();
    if (id == null || id.isEmpty) {
      state = state.copyWith(
        errorMessage: 'Select an agency to view earnings.',
      );
      return;
    }

    state = state.copyWith(
      isLoadingEarnings: true,
      selectedAgencyId: id,
      clearError: true,
    );

    final RepositoryResult<List<AgencyCommission>> result = await ref.read(
      listAgencyEarningsUseCaseProvider,
    )(id, perPage: perPage);

    if (!ref.mounted) return;

    result.fold(
      onSuccess: (List<AgencyCommission> earnings) {
        state = state.copyWith(
          earnings: List<AgencyCommission>.unmodifiable(earnings),
          isLoadingEarnings: false,
          clearError: true,
        );
      },
      onFailure: (failure) {
        state = state.copyWith(
          isLoadingEarnings: false,
          errorMessage: failure.message,
        );
      },
    );
  }

  Future<bool> approveHost(String userId) async {
    final String? id = state.activeAgencyId;
    if (id == null) return false;
    return _manageHost(
      () => ref.read(approveAgencyApplicationUseCaseProvider)(
        agencyId: id,
        userId: userId,
      ),
      successMessage: 'Host approved.',
      reloadHosts: true,
    );
  }

  Future<bool> rejectHost(String userId) async {
    final String? id = state.activeAgencyId;
    if (id == null) return false;
    return _manageHost(
      () => ref.read(rejectAgencyApplicationUseCaseProvider)(
        agencyId: id,
        userId: userId,
      ),
      successMessage: 'Application rejected.',
    );
  }

  Future<bool> removeHost(String userId) async {
    final String? id = state.activeAgencyId;
    if (id == null) return false;
    return _manageHost(
      () => ref.read(removeAgencyMemberUseCaseProvider)(
        agencyId: id,
        userId: userId,
      ),
      successMessage: 'Host removed.',
      reloadHosts: true,
      reloadAgency: true,
    );
  }

  Future<bool> _manageHost(
    Future<RepositoryResult<AgencyMember>> Function() action, {
    required String successMessage,
    bool reloadHosts = false,
    bool reloadAgency = false,
  }) async {
    state = state.copyWith(
      isManagingHosts: true,
      clearError: true,
      clearAction: true,
    );

    final RepositoryResult<AgencyMember> result = await action();
    if (!ref.mounted) return false;

    final bool ok = result.fold(
      onSuccess: (AgencyMember member) {
        state = state.copyWith(
          isManagingHosts: false,
          actionMessage: successMessage,
          clearError: true,
        );
        return true;
      },
      onFailure: (failure) {
        state = state.copyWith(
          isManagingHosts: false,
          errorMessage: failure.message,
        );
        return false;
      },
    );

    if (ok && reloadHosts) await loadHosts();
    if (ok && reloadAgency) await loadAgency();
    return ok;
  }
}
