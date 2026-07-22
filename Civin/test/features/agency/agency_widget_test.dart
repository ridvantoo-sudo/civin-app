import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/agency/data/repositories/agency_repository_impl.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:civin/features/agency/domain/repositories/agency_repository.dart';
import 'package:civin/features/agency/presentation/screens/agency_earnings_screen.dart';
import 'package:civin/features/agency/presentation/screens/agency_home_screen.dart';
import 'package:civin/features/agency/presentation/screens/agency_hosts_screen.dart';
import 'package:civin/features/agency/presentation/screens/agency_profile_screen.dart';
import 'package:civin/features/agency/presentation/screens/create_agency_screen.dart';
import 'package:civin/features/agency/presentation/widgets/agency_widgets.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  ThemeData darkTheme() => ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    colorScheme: ColorScheme.fromSeed(
      seedColor: const Color(0xFF1FA2A6),
      brightness: Brightness.dark,
    ),
  );

  Future<void> pumpAgency(
    WidgetTester tester,
    Widget home, {
    AgencyRepository? repository,
  }) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          agencyRepositoryProvider.overrideWithValue(
            repository ?? _FakeAgencyRepository(preloadAgency: true),
          ),
        ],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: darkTheme(),
          home: home,
        ),
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
  }

  testWidgets('AgencyHome shows create and host/earnings actions', (
    WidgetTester tester,
  ) async {
    await pumpAgency(tester, const AgencyHome());

    expect(find.text('Agency'), findsWidgets);
    expect(find.text('Grow with hosts'), findsOneWidget);
    expect(find.text('Create'), findsOneWidget);
    expect(find.text('Host list'), findsOneWidget);
    expect(find.text('Earnings & commission'), findsOneWidget);
  });

  testWidgets('CreateAgency form renders required fields', (
    WidgetTester tester,
  ) async {
    await pumpAgency(tester, const CreateAgency());

    expect(find.text('Create agency'), findsWidgets);
    expect(find.text('Agency name'), findsOneWidget);
    expect(find.text('Commission rate %'), findsOneWidget);
  });

  testWidgets('AgencyProfile shows card, stats, and apply', (
    WidgetTester tester,
  ) async {
    final _FakeAgencyRepository repository = _FakeAgencyRepository(
      preloadAgency: true,
    );
    await pumpAgency(
      tester,
      const AgencyProfile(agencyId: 'agency-1'),
      repository: repository,
    );
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text('Agency profile'), findsOneWidget);
    expect(find.text('Star Agency'), findsWidgets);
    expect(find.text('Statistics'), findsOneWidget);
    expect(find.byType(AgencyCard), findsOneWidget);
    expect(find.byType(AgencyStatisticsDashboard), findsOneWidget);

    await tester.scrollUntilVisible(
      find.text('Apply'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Apply'), findsOneWidget);
    expect(find.text('Apply to join'), findsOneWidget);
  });

  testWidgets('AgencyHosts lists host cards', (WidgetTester tester) async {
    await pumpAgency(
      tester,
      const AgencyHosts(agencyId: 'agency-1'),
    );
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text('Agency hosts'), findsOneWidget);
    expect(find.text('Host One'), findsOneWidget);
    expect(find.byType(HostCard), findsOneWidget);
    expect(find.text('Approve'), findsOneWidget);
  });

  testWidgets('AgencyEarnings shows commission dashboard', (
    WidgetTester tester,
  ) async {
    await pumpAgency(
      tester,
      const AgencyEarnings(agencyId: 'agency-1'),
    );
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text('Agency earnings'), findsOneWidget);
    expect(find.text('Commission view'), findsOneWidget);
    expect(find.text('Statistics'), findsOneWidget);
    expect(find.text('Recent commissions'), findsOneWidget);
    expect(find.byType(CommissionTile), findsOneWidget);
    expect(find.text('Host One'), findsOneWidget);
  });

  testWidgets('AgencyCard and HostCard display labels', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        themeMode: ThemeMode.dark,
        darkTheme: darkTheme(),
        home: Scaffold(
          body: ListView(
            children: const <Widget>[
              AgencyCard(
                agency: Agency(
                  id: 'agency-1',
                  name: 'Star Agency',
                  slug: 'star-agency',
                  commissionRate: 10,
                  hostsCount: 2,
                  membersCount: 3,
                ),
              ),
              HostCard(
                member: AgencyMember(
                  id: 'member-1',
                  agencyId: 'agency-1',
                  role: AgencyMemberRole.host,
                  status: AgencyMemberStatus.approved,
                  grossEarnings: 500,
                  commissionPaid: 50,
                  user: SocialUser(
                    id: 'host-1',
                    username: 'host',
                    nickname: 'Host One',
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );

    await tester.pump();
    expect(find.text('Star Agency'), findsOneWidget);
    expect(find.text('Host One'), findsOneWidget);
    expect(find.text('Approved'), findsOneWidget);
  });
}

final class _FakeAgencyRepository implements AgencyRepository {
  _FakeAgencyRepository({this.preloadAgency = false});

  final bool preloadAgency;

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

  static const Agency sample = Agency(
    id: 'agency-1',
    name: 'Star Agency',
    slug: 'star-agency',
    description: 'Top streamers',
    commissionRate: 10,
    membersCount: 2,
    hostsCount: 1,
    totalGrossEarnings: 1000,
    totalCommission: 100,
    owner: owner,
  );

  @override
  Future<RepositoryResult<Agency>> create(CreateAgencyInput input) async =>
      RepositorySuccess<Agency>(
        Agency(
          id: 'agency-new',
          name: input.name,
          slug: 'created',
          commissionRate: input.commissionRate ?? 10,
          owner: owner,
        ),
      );

  @override
  Future<RepositoryResult<Agency>> getAgency(String agencyId) async =>
      const RepositorySuccess<Agency>(sample);

  @override
  Future<RepositoryResult<AgencyMember>> apply({
    required String agencyId,
    String? message,
  }) async => RepositorySuccess<AgencyMember>(
    AgencyMember(
      id: 'member-pending',
      agencyId: agencyId,
      role: AgencyMemberRole.host,
      status: AgencyMemberStatus.pending,
      message: message,
      user: host,
    ),
  );

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
  ) async => const RepositorySuccess<List<AgencyMember>>(<AgencyMember>[
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

  @override
  Future<RepositoryResult<List<AgencyCommission>>> getEarnings(
    String agencyId, {
    int perPage = 20,
  }) async =>
      const RepositorySuccess<List<AgencyCommission>>(<AgencyCommission>[
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
