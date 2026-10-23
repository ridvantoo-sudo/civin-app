import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:civin/features/agency/presentation/agency_providers.dart';
import 'package:civin/features/agency/presentation/widgets/agency_theme.dart';
import 'package:civin/features/agency/presentation/widgets/agency_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class AgencyProfile extends ConsumerStatefulWidget {
  const AgencyProfile({this.agencyId, super.key});

  final String? agencyId;

  @override
  ConsumerState<AgencyProfile> createState() => _AgencyProfileState();
}

final class _AgencyProfileState extends ConsumerState<AgencyProfile> {
  late final TextEditingController _messageController;

  @override
  void initState() {
    super.initState();
    _messageController = TextEditingController();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final String? id =
          widget.agencyId ?? ref.read(agencyProvider).activeAgencyId;
      if (id != null && id.isNotEmpty) {
        ref.read(agencyProvider.notifier).loadAgency(id);
      }
    });
  }

  @override
  void dispose() {
    _messageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AgencyViewState state = ref.watch(agencyProvider);
    final Agency? agency = state.agency;

    return Theme(
      data: agencyDarkTheme(context),
      child: Builder(
        builder: (BuildContext context) {
          final ColorScheme colors = Theme.of(context).colorScheme;
          return Scaffold(
            appBar: AppBar(
              title: const Text('Agency profile'),
              actions: <Widget>[
                IconButton(
                  tooltip: 'Hosts',
                  onPressed: () => context.push(AppRoutes.agencyHosts),
                  icon: const Icon(Icons.groups_outlined),
                ),
                IconButton(
                  tooltip: 'Earnings',
                  onPressed: () => context.push(AppRoutes.agencyEarnings),
                  icon: const Icon(Icons.payments_outlined),
                ),
              ],
            ),
            body: DecoratedBox(
              decoration: agencyAtmosphere(colors),
              child: RefreshIndicator.adaptive(
                onRefresh: () => ref.read(agencyProvider.notifier).refresh(),
                child: state.isLoading && agency == null
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const <Widget>[
                          SizedBox(height: 120),
                          AppLoadingWidget(message: 'Loading agency'),
                        ],
                      )
                    : agency == null
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: <Widget>[
                          const SizedBox(height: 48),
                          AppErrorWidget(
                            message:
                                state.errorMessage ??
                                'Open an agency from Agency home first.',
                            onRetry: state.activeAgencyId == null
                                ? null
                                : () => ref
                                      .read(agencyProvider.notifier)
                                      .loadAgency(),
                          ),
                          const SizedBox(height: 16),
                          Center(
                            child: FilledButton(
                              onPressed: () => context.go(AppRoutes.agency),
                              child: const Text('Back to Agency'),
                            ),
                          ),
                        ],
                      )
                    : ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
                        children: <Widget>[
                          AgencyCard(agency: agency),
                          const SizedBox(height: 16),
                          if (agency.description?.trim().isNotEmpty ==
                              true) ...<Widget>[
                            Text(
                              agency.description!,
                              style: Theme.of(context).textTheme.bodyMedium,
                            ),
                            const SizedBox(height: 16),
                          ],
                          AgencyStatisticsDashboard(
                            statistics: state.statistics,
                          ),
                          const SizedBox(height: 20),
                          Text(
                            'Apply to join',
                            style: Theme.of(context).textTheme.titleMedium
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 8),
                          TextField(
                            controller: _messageController,
                            decoration: const InputDecoration(
                              labelText: 'Message (optional)',
                              border: OutlineInputBorder(),
                            ),
                            maxLines: 3,
                          ),
                          const SizedBox(height: 12),
                          FilledButton(
                            onPressed: state.isApplying
                                ? null
                                : () => ref
                                      .read(agencyProvider.notifier)
                                      .apply(message: _messageController.text),
                            child: state.isApplying
                                ? const SizedBox(
                                    width: 18,
                                    height: 18,
                                    child: CircularProgressIndicator(
                                      strokeWidth: 2,
                                    ),
                                  )
                                : const Text('Apply'),
                          ),
                          if (state.lastApplication != null) ...<Widget>[
                            const SizedBox(height: 12),
                            Text(
                              'Last application: ${state.lastApplication!.statusLabel}',
                              style: TextStyle(color: colors.secondary),
                            ),
                          ],
                          const SizedBox(height: 20),
                          Row(
                            children: <Widget>[
                              Expanded(
                                child: OutlinedButton(
                                  onPressed: () =>
                                      context.push(AppRoutes.agencyHosts),
                                  child: const Text('Host list'),
                                ),
                              ),
                              const SizedBox(width: 12),
                              Expanded(
                                child: OutlinedButton(
                                  onPressed: () =>
                                      context.push(AppRoutes.agencyEarnings),
                                  child: const Text('Commission'),
                                ),
                              ),
                            ],
                          ),
                          if (state.errorMessage != null) ...<Widget>[
                            const SizedBox(height: 16),
                            Text(
                              state.errorMessage!,
                              style: TextStyle(color: colors.error),
                            ),
                          ],
                          if (state.actionMessage != null) ...<Widget>[
                            const SizedBox(height: 16),
                            Text(
                              state.actionMessage!,
                              style: TextStyle(color: colors.secondary),
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
