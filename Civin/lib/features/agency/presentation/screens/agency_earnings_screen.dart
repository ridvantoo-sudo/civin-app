import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:civin/features/agency/presentation/agency_providers.dart';
import 'package:civin/features/agency/presentation/widgets/agency_theme.dart';
import 'package:civin/features/agency/presentation/widgets/agency_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class AgencyEarnings extends ConsumerStatefulWidget {
  const AgencyEarnings({this.agencyId, super.key});

  final String? agencyId;

  @override
  ConsumerState<AgencyEarnings> createState() => _AgencyEarningsState();
}

final class _AgencyEarningsState extends ConsumerState<AgencyEarnings> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final AgencyController controller = ref.read(agencyProvider.notifier);
      final String? id =
          widget.agencyId ?? ref.read(agencyProvider).activeAgencyId;
      if (id == null || id.isEmpty) return;
      if (ref.read(agencyProvider).agency == null) {
        await controller.loadAgency(id);
      }
      await controller.loadEarnings(id);
    });
  }

  @override
  Widget build(BuildContext context) {
    final AgencyViewState state = ref.watch(agencyProvider);

    return Theme(
      data: agencyDarkTheme(context),
      child: Builder(
        builder: (BuildContext context) {
          final ColorScheme colors = Theme.of(context).colorScheme;
          return Scaffold(
            appBar: AppBar(title: const Text('Agency earnings')),
            body: DecoratedBox(
              decoration: agencyAtmosphere(colors),
              child: RefreshIndicator.adaptive(
                onRefresh: () async {
                  await ref.read(agencyProvider.notifier).loadAgency();
                  await ref.read(agencyProvider.notifier).loadEarnings();
                },
                child: state.isLoadingEarnings &&
                        state.earnings.isEmpty &&
                        state.agency == null
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const <Widget>[
                          SizedBox(height: 120),
                          AppLoadingWidget(message: 'Loading earnings'),
                        ],
                      )
                    : state.errorMessage != null &&
                          state.earnings.isEmpty &&
                          state.agency == null
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: <Widget>[
                          const SizedBox(height: 48),
                          AppErrorWidget(
                            message: state.errorMessage!,
                            onRetry: () => ref
                                .read(agencyProvider.notifier)
                                .loadEarnings(),
                          ),
                        ],
                      )
                    : ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
                        children: <Widget>[
                          Text(
                            'Commission view',
                            style: Theme.of(context).textTheme.headlineSmall
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Agency take from host gift earnings, with live totals.',
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(color: colors.onSurfaceVariant),
                          ),
                          const SizedBox(height: 18),
                          AgencyStatisticsDashboard(
                            statistics: state.statistics,
                          ),
                          const SizedBox(height: 20),
                          Text(
                            'Recent commissions',
                            style: Theme.of(context).textTheme.titleMedium
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 12),
                          if (state.isLoadingEarnings && state.earnings.isEmpty)
                            const Padding(
                              padding: EdgeInsets.symmetric(vertical: 24),
                              child: AppLoadingWidget(
                                message: 'Loading commissions',
                              ),
                            )
                          else if (state.earnings.isEmpty)
                            Text(
                              'No commission records yet.',
                              style: Theme.of(context).textTheme.bodyMedium
                                  ?.copyWith(color: colors.onSurfaceVariant),
                            )
                          else
                            for (final AgencyCommission commission
                                in state.earnings) ...<Widget>[
                              CommissionTile(commission: commission),
                              const SizedBox(height: 10),
                            ],
                          if (state.errorMessage != null) ...<Widget>[
                            const SizedBox(height: 12),
                            Text(
                              state.errorMessage!,
                              style: TextStyle(color: colors.error),
                            ),
                          ],
                        ],
                      ),
              ),
            ),
          );
        },
      ),
    );
  }
}
