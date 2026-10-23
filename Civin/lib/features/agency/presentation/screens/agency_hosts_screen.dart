import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:civin/features/agency/presentation/agency_providers.dart';
import 'package:civin/features/agency/presentation/widgets/agency_theme.dart';
import 'package:civin/features/agency/presentation/widgets/agency_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class AgencyHosts extends ConsumerStatefulWidget {
  const AgencyHosts({this.agencyId, super.key});

  final String? agencyId;

  @override
  ConsumerState<AgencyHosts> createState() => _AgencyHostsState();
}

final class _AgencyHostsState extends ConsumerState<AgencyHosts> {
  late final TextEditingController _userIdController;

  @override
  void initState() {
    super.initState();
    _userIdController = TextEditingController();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final String? id =
          widget.agencyId ?? ref.read(agencyProvider).activeAgencyId;
      if (id != null && id.isNotEmpty) {
        ref.read(agencyProvider.notifier).loadHosts(id);
      }
    });
  }

  @override
  void dispose() {
    _userIdController.dispose();
    super.dispose();
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
            appBar: AppBar(title: const Text('Agency hosts')),
            body: DecoratedBox(
              decoration: agencyAtmosphere(colors),
              child: RefreshIndicator.adaptive(
                onRefresh: () =>
                    ref.read(agencyProvider.notifier).loadHosts(),
                child: state.isLoadingHosts && state.hosts.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: const <Widget>[
                          SizedBox(height: 120),
                          AppLoadingWidget(message: 'Loading hosts'),
                        ],
                      )
                    : state.errorMessage != null && state.hosts.isEmpty
                    ? ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        children: <Widget>[
                          const SizedBox(height: 48),
                          AppErrorWidget(
                            message: state.errorMessage!,
                            onRetry: () =>
                                ref.read(agencyProvider.notifier).loadHosts(),
                          ),
                        ],
                      )
                    : ListView(
                        physics: const AlwaysScrollableScrollPhysics(),
                        padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
                        children: <Widget>[
                          Text(
                            'Host list',
                            style: Theme.of(context).textTheme.headlineSmall
                                ?.copyWith(fontWeight: FontWeight.w800),
                          ),
                          const SizedBox(height: 6),
                          Text(
                            'Approved hosts under this agency and owner tools.',
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(color: colors.onSurfaceVariant),
                          ),
                          const SizedBox(height: 18),
                          TextField(
                            controller: _userIdController,
                            decoration: const InputDecoration(
                              labelText: 'Applicant user ID',
                              hintText: 'Approve or reject by user UUID',
                              border: OutlineInputBorder(),
                            ),
                          ),
                          const SizedBox(height: 10),
                          Row(
                            children: <Widget>[
                              Expanded(
                                child: FilledButton.tonal(
                                  onPressed: state.isManagingHosts
                                      ? null
                                      : () {
                                          final String userId =
                                              _userIdController.text.trim();
                                          if (userId.isEmpty) return;
                                          ref
                                              .read(agencyProvider.notifier)
                                              .approveHost(userId);
                                        },
                                  child: const Text('Approve'),
                                ),
                              ),
                              const SizedBox(width: 10),
                              Expanded(
                                child: OutlinedButton(
                                  onPressed: state.isManagingHosts
                                      ? null
                                      : () {
                                          final String userId =
                                              _userIdController.text.trim();
                                          if (userId.isEmpty) return;
                                          ref
                                              .read(agencyProvider.notifier)
                                              .rejectHost(userId);
                                        },
                                  child: const Text('Reject'),
                                ),
                              ),
                            ],
                          ),
                          const SizedBox(height: 20),
                          if (state.hosts.isEmpty)
                            Text(
                              'No hosts yet.',
                              style: Theme.of(context).textTheme.bodyMedium
                                  ?.copyWith(color: colors.onSurfaceVariant),
                            )
                          else
                            for (final AgencyMember host in state.hosts) ...<
                              Widget
                            >[
                              HostCard(
                                member: host,
                                onRemove: host.user == null
                                    ? null
                                    : () => ref
                                          .read(agencyProvider.notifier)
                                          .removeHost(host.user!.id),
                              ),
                              const SizedBox(height: 12),
                            ],
                          if (state.errorMessage != null) ...<Widget>[
                            const SizedBox(height: 8),
                            Text(
                              state.errorMessage!,
                              style: TextStyle(color: colors.error),
                            ),
                          ],
                          if (state.actionMessage != null) ...<Widget>[
                            const SizedBox(height: 8),
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
