import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/agency/data/repositories/agency_repository_impl.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:civin/features/agency/domain/repositories/agency_repository.dart';
import 'package:civin/features/agency/presentation/agency_providers.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('agencyProvider loads agency profile and statistics', () async {
    final _FakeAgencyRepository repository = _FakeAgencyRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [agencyRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container.read(agencyProvider.notifier).loadAgency('agency-1');

    final AgencyViewState state = container.read(agencyProvider);
    expect(state.agency?.name, 'Star Agency');
    expect(state.selectedAgencyId, 'agency-1');
    expect(state.statistics.hostsCount, 1);
    expect(state.statistics.totalCommission, 100);
  });

  test('agencyProvider creates agency', () async {
    final _FakeAgencyRepository repository = _FakeAgencyRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [agencyRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    final bool ok = await container.read(agencyProvider.notifier).createAgency(
      const CreateAgencyInput(name: 'Nova Live', commissionRate: 12),
    );

    expect(ok, isTrue);
    expect(repository.lastCreateName, 'Nova Live');
    expect(container.read(agencyProvider).agency?.name, 'Nova Live');
    expect(
      container.read(agencyProvider).actionMessage,
      contains('created'),
    );
  });

  test('agencyProvider applies and loads hosts/earnings', () async {
    final _FakeAgencyRepository repository = _FakeAgencyRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [agencyRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container.read(agencyProvider.notifier).loadAgency('agency-1');
    final bool applied = await container
        .read(agencyProvider.notifier)
        .apply(message: 'Join please');
    await container.read(agencyProvider.notifier).loadHosts();
    await container.read(agencyProvider.notifier).loadEarnings();

    expect(applied, isTrue);
    expect(container.read(agencyProvider).lastApplication?.isPending, isTrue);
    expect(container.read(agencyProvider).hosts, hasLength(1));
    expect(container.read(agencyProvider).earnings, hasLength(1));
    expect(container.read(agencyProvider).earnings.first.commissionAmount, 100);
  });

  test('agencyProvider surfaces load failures', () async {
    final _FakeAgencyRepository repository = _FakeAgencyRepository()
      ..fail = true;
    final ProviderContainer container = ProviderContainer(
      overrides: [agencyRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container.read(agencyProvider.notifier).loadAgency('agency-1');

    expect(container.read(agencyProvider).agency, isNull);
    expect(container.read(agencyProvider).errorMessage, 'Agency unavailable');
  });
}

final class _FakeAgencyRepository implements AgencyRepository {
  bool fail = false;
  String? lastCreateName;

  static const SocialUser owner = SocialUser(
    id: 'user-1',
    username: 'owner',
    nickname: 'Owner',
  );

  static const SocialUser host = SocialUser(
    id: 'host-1',
    username: 'host',
    nickname: 'Host One',
  );

  Agency agency = const Agency(
    id: 'agency-1',
    name: 'Star Agency',
    slug: 'star-agency',
    commissionRate: 10,
    membersCount: 2,
    hostsCount: 1,
    totalGrossEarnings: 1000,
    totalCommission: 100,
    owner: owner,
  );

  @override
  Future<RepositoryResult<Agency>> create(CreateAgencyInput input) async {
    if (fail) {
      return const RepositoryFailure<Agency>(
        AppFailure.network(message: 'Agency unavailable'),
      );
    }
    lastCreateName = input.name;
    agency = Agency(
      id: 'agency-new',
      name: input.name,
      slug: 'nova-live',
      commissionRate: input.commissionRate ?? 10,
      owner: owner,
    );
    return RepositorySuccess<Agency>(agency);
  }

  @override
  Future<RepositoryResult<Agency>> getAgency(String agencyId) async {
    if (fail) {
      return const RepositoryFailure<Agency>(
        AppFailure.network(message: 'Agency unavailable'),
      );
    }
    return RepositorySuccess<Agency>(agency);
  }

  @override
  Future<RepositoryResult<AgencyMember>> apply({
    required String agencyId,
    String? message,
  }) async {
    if (fail) {
      return const RepositoryFailure<AgencyMember>(
        AppFailure.network(message: 'Agency unavailable'),
      );
    }
    return RepositorySuccess<AgencyMember>(
      AgencyMember(
        id: 'member-pending',
        agencyId: agencyId,
        role: AgencyMemberRole.host,
        status: AgencyMemberStatus.pending,
        message: message,
        user: host,
      ),
    );
  }

  @override
  Future<RepositoryResult<AgencyMember>> approve({
    required String agencyId,
    required String userId,
  }) async => const RepositorySuccess<AgencyMember>(
    AgencyMember(
      id: 'member-1',
      agencyId: 'agency-1',
      role: AgencyMemberRole.host,
      status: AgencyMemberStatus.approved,
      user: host,
    ),
  );

  @override
  Future<RepositoryResult<AgencyMember>> reject({
    required String agencyId,
    required String userId,
  }) async => const RepositorySuccess<AgencyMember>(
    AgencyMember(
      id: 'member-1',
      agencyId: 'agency-1',
      role: AgencyMemberRole.host,
      status: AgencyMemberStatus.rejected,
      user: host,
    ),
  );

  @override
  Future<RepositoryResult<AgencyMember>> removeMember({
    required String agencyId,
    required String userId,
  }) async => const RepositorySuccess<AgencyMember>(
    AgencyMember(
      id: 'member-1',
      agencyId: 'agency-1',
      role: AgencyMemberRole.host,
      status: AgencyMemberStatus.removed,
      user: host,
    ),
  );

  @override
  Future<RepositoryResult<List<AgencyMember>>> getHosts(
    String agencyId,
  ) async {
    if (fail) {
      return const RepositoryFailure<List<AgencyMember>>(
        AppFailure.network(message: 'Agency unavailable'),
      );
    }
    return const RepositorySuccess<List<AgencyMember>>(<AgencyMember>[
      AgencyMember(
        id: 'member-1',
        agencyId: 'agency-1',
        role: AgencyMemberRole.host,
        status: AgencyMemberStatus.approved,
        grossEarnings: 1000,
        commissionPaid: 100,
        user: host,
      ),
    ]);
  }

  @override
  Future<RepositoryResult<List<AgencyCommission>>> getEarnings(
    String agencyId, {
    int perPage = 20,
  }) async {
    if (fail) {
      return const RepositoryFailure<List<AgencyCommission>>(
        AppFailure.network(message: 'Agency unavailable'),
      );
    }
    return const RepositorySuccess<List<AgencyCommission>>(<AgencyCommission>[
      AgencyCommission(
        id: 'comm-1',
        agencyId: 'agency-1',
        grossAmount: 1000,
        commissionRate: 10,
        commissionAmount: 100,
        hostNetAmount: 900,
        host: host,
      ),
    ]);
  }
}
