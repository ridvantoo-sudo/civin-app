import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:civin/features/agency/presentation/agency_providers.dart';
import 'package:civin/features/agency/presentation/widgets/agency_theme.dart';
import 'package:civin/features/agency/presentation/widgets/agency_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class AgencyHome extends ConsumerStatefulWidget {
  const AgencyHome({super.key});

  @override
  ConsumerState<AgencyHome> createState() => _AgencyHomeState();
}

final class _AgencyHomeState extends ConsumerState<AgencyHome> {
  late final TextEditingController _agencyIdController;

  @override
  void initState() {
    super.initState();
    _agencyIdController = TextEditingController(
      text: ref.read(agencyProvider).selectedAgencyId ?? '',
    );
  }

  @override
  void dispose() {
    _agencyIdController.dispose();
    super.dispose();
  }

  Future<void> _openAgency() async {
    final String id = _agencyIdController.text.trim();
    ref.read(agencyProvider.notifier).selectAgencyId(id);
    await ref.read(agencyProvider.notifier).loadAgency(id);
    if (!mounted) return;
    final AgencyViewState state = ref.read(agencyProvider);
    if (state.agency != null) {
      await context.push(AppRoutes.agencyProfile);
    }
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
            appBar: AppBar(title: const Text('Agency')),
            body: DecoratedBox(
              decoration: agencyAtmosphere(colors),
              child: ListView(
                padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
                children: <Widget>[
                  Text(
                    'Grow with hosts',
                    style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    'Create an agency, invite hosts, and track commission earnings.',
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: colors.onSurfaceVariant,
                    ),
                  ),
                  const SizedBox(height: 20),
                  if (state.agency != null) ...<Widget>[
                    AgencyCard(
                      agency: state.agency!,
                      onTap: () => context.push(AppRoutes.agencyProfile),
                      trailing: Icon(
                        Icons.chevron_right_rounded,
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 16),
                    AgencyStatisticsDashboard(statistics: state.statistics),
                    const SizedBox(height: 20),
                  ],
                  TextField(
                    controller: _agencyIdController,
                    decoration: const InputDecoration(
                      labelText: 'Agency ID',
                      hintText: 'Paste an agency UUID',
                      border: OutlineInputBorder(),
                    ),
                    textInputAction: TextInputAction.done,
                    onSubmitted: (_) => _openAgency(),
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: <Widget>[
                      Expanded(
                        child: FilledButton(
                          onPressed: state.isLoading ? null : _openAgency,
                          child: state.isLoading
                              ? const SizedBox(
                                  width: 18,
                                  height: 18,
                                  child: CircularProgressIndicator(strokeWidth: 2),
                                )
                              : const Text('Open agency'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () => context.push(AppRoutes.createAgency),
                          child: const Text('Create'),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 24),
                  _HomeActionCard(
                    icon: Icons.badge_outlined,
                    title: 'Agency profile',
                    subtitle: 'Stats, commission rate, and apply',
                    onTap: () => context.push(AppRoutes.agencyProfile),
                  ),
                  const SizedBox(height: 12),
                  _HomeActionCard(
                    icon: Icons.groups_outlined,
                    title: 'Host list',
                    subtitle: 'Approved hosts and management',
                    onTap: () => context.push(AppRoutes.agencyHosts),
                  ),
                  const SizedBox(height: 12),
                  _HomeActionCard(
                    icon: Icons.payments_outlined,
                    title: 'Earnings & commission',
                    subtitle: 'Statistics dashboard and payout view',
                    onTap: () => context.push(AppRoutes.agencyEarnings),
                  ),
                  if (state.errorMessage != null) ...<Widget>[
                    const SizedBox(height: 16),
                    AppErrorWidget(message: state.errorMessage!),
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
          );
        },
      ),
    );
  }
}

final class _HomeActionCard extends StatelessWidget {
  const _HomeActionCard({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return Material(
      color: colors.surfaceContainerHighest.withValues(alpha: 0.5),
      borderRadius: BorderRadius.circular(20),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(20),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Row(
            children: <Widget>[
              Container(
                width: 48,
                height: 48,
                decoration: BoxDecoration(
                  color: colors.primary.withValues(alpha: 0.18),
                  borderRadius: BorderRadius.circular(14),
                ),
                child: Icon(icon, color: colors.primary),
              ),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      subtitle,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(Icons.chevron_right_rounded, color: colors.onSurfaceVariant),
            ],
          ),
        ),
      ),
    );
  }
}
