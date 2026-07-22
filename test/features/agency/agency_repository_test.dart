import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/agency/data/datasources/agency_remote_data_source.dart';
import 'package:civin/features/agency/data/models/agency_model.dart';
import 'package:civin/features/agency/data/repositories/agency_repository_impl.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late _FakeRemote remote;
  late AgencyRepositoryImpl repository;

  setUp(() {
    remote = _FakeRemote();
    repository = AgencyRepositoryImpl(remote);
  });

  test('creates agency through remote datasource', () async {
    final RepositoryResult<Agency> result = await repository.create(
      const CreateAgencyInput(
        name: 'Star Agency',
        commissionRate: 10,
      ),
    );

    expect(result, isA<RepositorySuccess<Agency>>());
    final Agency agency = (result as RepositorySuccess<Agency>).data;
    expect(agency.name, 'Star Agency');
    expect(agency.commissionRate, 10);
    expect(remote.lastCreateName, 'Star Agency');
  });

  test('loads agency profile', () async {
    final RepositoryResult<Agency> result = await repository.getAgency(
      'agency-1',
    );

    expect(result, isA<RepositorySuccess<Agency>>());
    expect((result as RepositorySuccess<Agency>).data.id, 'agency-1');
    expect(result.data.hostsCount, 1);
  });

  test('applies to agency', () async {
    final RepositoryResult<AgencyMember> result = await repository.apply(
      agencyId: 'agency-1',
      message: 'Looking to join',
    );

    expect(result, isA<RepositorySuccess<AgencyMember>>());
    expect(remote.lastApplyAgencyId, 'agency-1');
    expect(
      (result as RepositorySuccess<AgencyMember>).data.status,
      AgencyMemberStatus.pending,
    );
  });

  test('lists hosts and earnings', () async {
    final RepositoryResult<List<AgencyMember>> hosts = await repository
        .getHosts('agency-1');
    final RepositoryResult<List<AgencyCommission>> earnings = await repository
        .getEarnings('agency-1');

    expect(hosts, isA<RepositorySuccess<List<AgencyMember>>>());
    expect(
      (hosts as RepositorySuccess<List<AgencyMember>>).data,
      hasLength(1),
    );
    expect(hosts.data.first.grossEarnings, 1000);

    expect(earnings, isA<RepositorySuccess<List<AgencyCommission>>>());
    expect(
      (earnings as RepositorySuccess<List<AgencyCommission>>).data.first
          .commissionAmount,
      100,
    );
  });

  test('maps remote failures to repository failure', () async {
    remote.error = DioException(
      requestOptions: RequestOptions(path: '/api/v1/agencies/create'),
      response: Response<dynamic>(
        requestOptions: RequestOptions(path: '/api/v1/agencies/create'),
        statusCode: 422,
        data: <String, dynamic>{'message': 'Agency unavailable.'},
      ),
    );

    final RepositoryResult<Agency> failed = await repository.create(
      const CreateAgencyInput(name: 'Fail'),
    );

    expect(failed, isA<RepositoryFailure<Agency>>());
    expect(
      (failed as RepositoryFailure<Agency>).failure,
      isA<NetworkFailure>(),
    );
    expect(failed.failure.message, 'Agency unavailable.');
  });

  test('parses agency, member, and commission json', () {
    final Agency agency = AgencyModel.fromJson(<String, dynamic>{
      'id': 'agency-1',
      'name': 'Nova Live',
      'slug': 'nova-live',
      'description': 'Top hosts',
      'logo': 'https://cdn.example.com/logo.png',
      'commission_rate': 12.5,
      'status': 'active',
      'members_count': 4,
      'hosts_count': 3,
      'total_gross_earnings': 5000,
      'total_commission': 625,
      'owner': <String, dynamic>{
        'id': 'user-1',
        'username': 'owner',
        'nickname': 'Owner',
      },
      'created_at': '2026-07-01T00:00:00.000000Z',
    });

    expect(agency.name, 'Nova Live');
    expect(agency.commissionRateLabel, '12.50%');
    expect(agency.owner?.username, 'owner');
    expect(agency.isActive, isTrue);

    final AgencyMember member = AgencyModel.memberFromJson(<String, dynamic>{
      'id': 'member-1',
      'agency_id': 'agency-1',
      'role': 'host',
      'status': 'approved',
      'gross_earnings': 1000,
      'commission_paid': 100,
      'user': <String, dynamic>{
        'id': 'host-1',
        'username': 'host',
        'nickname': 'Host One',
      },
    });

    expect(member.isHost, isTrue);
    expect(member.isApproved, isTrue);
    expect(member.user?.displayName, 'Host One');

    final AgencyCommission commission = AgencyModel.commissionFromJson(
      <String, dynamic>{
        'id': 'comm-1',
        'agency_id': 'agency-1',
        'gross_amount': 1000,
        'commission_rate': 10,
        'commission_amount': 100,
        'host_net_amount': 900,
        'currency': 'coins',
        'host': <String, dynamic>{
          'id': 'host-1',
          'username': 'host',
        },
      },
    );

    expect(commission.commissionAmount, 100);
    expect(commission.commissionRateLabel, '10%');
  });
}

final class _FakeRemote implements AgencyRemoteDataSource {
  Object? error;
  String? lastCreateName;
  String? lastApplyAgencyId;

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

  static const Agency sampleAgency = Agency(
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
  Future<Agency> create(CreateAgencyInput input) async {
    if (error != null) throw error!;
    lastCreateName = input.name;
    return Agency(
      id: 'agency-new',
      name: input.name,
      slug: 'star-agency',
      description: input.description,
      logo: input.logo,
      commissionRate: input.commissionRate ?? 10,
      owner: owner,
    );
  }

  @override
  Future<Agency> getAgency(String agencyId) async {
    if (error != null) throw error!;
    return sampleAgency;
  }

  @override
  Future<AgencyMember> apply({
    required String agencyId,
    String? message,
  }) async {
    if (error != null) throw error!;
    lastApplyAgencyId = agencyId;
    return AgencyMember(
      id: 'member-pending',
      agencyId: agencyId,
      role: AgencyMemberRole.host,
      status: AgencyMemberStatus.pending,
      message: message,
      user: host,
    );
  }

  @override
  Future<AgencyMember> approve({
    required String agencyId,
    required String userId,
  }) async {
    if (error != null) throw error!;
    return AgencyMember(
      id: 'member-1',
      agencyId: agencyId,
      role: AgencyMemberRole.host,
      status: AgencyMemberStatus.approved,
      user: host,
    );
  }

  @override
  Future<AgencyMember> reject({
    required String agencyId,
    required String userId,
  }) async {
    if (error != null) throw error!;
    return AgencyMember(
      id: 'member-1',
      agencyId: agencyId,
      role: AgencyMemberRole.host,
      status: AgencyMemberStatus.rejected,
      user: host,
    );
  }

  @override
  Future<AgencyMember> removeMember({
    required String agencyId,
    required String userId,
  }) async {
    if (error != null) throw error!;
    return AgencyMember(
      id: 'member-1',
      agencyId: agencyId,
      role: AgencyMemberRole.host,
      status: AgencyMemberStatus.removed,
      user: host,
    );
  }

  @override
  Future<List<AgencyMember>> getHosts(String agencyId) async {
    if (error != null) throw error!;
    return const <AgencyMember>[
      AgencyMember(
        id: 'member-1',
        agencyId: 'agency-1',
        role: AgencyMemberRole.host,
        status: AgencyMemberStatus.approved,
        grossEarnings: 1000,
        commissionPaid: 100,
        user: host,
      ),
    ];
  }

  @override
  Future<List<AgencyCommission>> getEarnings(
    String agencyId, {
    int perPage = 20,
  }) async {
    if (error != null) throw error!;
    return const <AgencyCommission>[
      AgencyCommission(
        id: 'comm-1',
        agencyId: 'agency-1',
        grossAmount: 1000,
        commissionRate: 10,
        commissionAmount: 100,
        hostNetAmount: 900,
        host: host,
      ),
    ];
  }
}
