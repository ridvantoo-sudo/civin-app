import 'package:civin/core/widgets/app_network_image.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:flutter/material.dart';

final class AgencyCard extends StatelessWidget {
  const AgencyCard({
    required this.agency,
    this.onTap,
    this.trailing,
    super.key,
  });

  final Agency agency;
  final VoidCallback? onTap;
  final Widget? trailing;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return Material(
      color: colors.surfaceContainerHighest.withValues(alpha: 0.55),
      borderRadius: BorderRadius.circular(22),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Row(
            children: <Widget>[
              _AgencyAvatar(logo: agency.logo, name: agency.name),
              const SizedBox(width: 14),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      agency.name,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 2),
                    Text(
                      '${agency.statusLabel} · ${agency.commissionRateLabel} commission',
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      '${agency.hostsCount} hosts · ${agency.membersCount} members',
                      style: Theme.of(context).textTheme.labelMedium?.copyWith(
                        color: colors.secondary,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              ?trailing,
            ],
          ),
        ),
      ),
    );
  }
}

final class HostCard extends StatelessWidget {
  const HostCard({
    required this.member,
    this.onRemove,
    this.onApprove,
    this.onReject,
    super.key,
  });

  final AgencyMember member;
  final VoidCallback? onRemove;
  final VoidCallback? onApprove;
  final VoidCallback? onReject;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final String name = member.user?.displayName ?? 'Host';
    final String username = member.user?.username ?? '';

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHighest.withValues(alpha: 0.5),
        borderRadius: BorderRadius.circular(20),
        border: Border.all(
          color: colors.outlineVariant.withValues(alpha: 0.3),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              CircleAvatar(
                radius: 22,
                backgroundColor: colors.primary.withValues(alpha: 0.2),
                backgroundImage: member.user?.avatarUrl?.isNotEmpty == true
                    ? NetworkImage(member.user!.avatarUrl!)
                    : null,
                child: member.user?.avatarUrl?.isNotEmpty == true
                    ? null
                    : Icon(Icons.person_rounded, color: colors.primary),
              ),
              const SizedBox(width: 12),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(
                      name,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (username.isNotEmpty)
                      Text(
                        '@$username',
                        style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: colors.onSurfaceVariant,
                        ),
                      ),
                  ],
                ),
              ),
              _StatusChip(label: member.statusLabel),
            ],
          ),
          const SizedBox(height: 12),
          Row(
            children: <Widget>[
              _MiniStat(
                label: 'Gross',
                value: '${member.grossEarnings}',
              ),
              const SizedBox(width: 12),
              _MiniStat(
                label: 'Commission',
                value: '${member.commissionPaid}',
              ),
              const Spacer(),
              Text(
                member.roleLabel,
                style: Theme.of(context).textTheme.labelMedium?.copyWith(
                  color: colors.secondary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
          if (onApprove != null || onReject != null || onRemove != null) ...<
            Widget
          >[
            const SizedBox(height: 12),
            Wrap(
              spacing: 8,
              runSpacing: 8,
              children: <Widget>[
                if (onApprove != null)
                  FilledButton.tonal(
                    onPressed: onApprove,
                    child: const Text('Approve'),
                  ),
                if (onReject != null)
                  OutlinedButton(
                    onPressed: onReject,
                    child: const Text('Reject'),
                  ),
                if (onRemove != null)
                  TextButton(
                    onPressed: onRemove,
                    child: const Text('Remove'),
                  ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

final class AgencyStatisticsDashboard extends StatelessWidget {
  const AgencyStatisticsDashboard({required this.statistics, super.key});

  final AgencyStatistics statistics;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(24),
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: <Color>[
            colors.primary.withValues(alpha: 0.45),
            colors.secondary.withValues(alpha: 0.18),
          ],
        ),
        border: Border.all(
          color: colors.outlineVariant.withValues(alpha: 0.3),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            'Statistics',
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 14),
          Row(
            children: <Widget>[
              Expanded(
                child: _StatTile(
                  label: 'Hosts',
                  value: '${statistics.hostsCount}',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _StatTile(
                  label: 'Members',
                  value: '${statistics.membersCount}',
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          Row(
            children: <Widget>[
              Expanded(
                child: _StatTile(
                  label: 'Gross',
                  value: '${statistics.totalGrossEarnings}',
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: _StatTile(
                  label: 'Commission',
                  value: '${statistics.totalCommission}',
                ),
              ),
            ],
          ),
          const SizedBox(height: 10),
          _StatTile(
            label: 'Rate',
            value:
                '${statistics.commissionRate == statistics.commissionRate.roundToDouble() ? statistics.commissionRate.toStringAsFixed(0) : statistics.commissionRate.toStringAsFixed(2)}%',
            wide: true,
          ),
        ],
      ),
    );
  }
}

final class CommissionTile extends StatelessWidget {
  const CommissionTile({required this.commission, super.key});

  final AgencyCommission commission;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final String hostName = commission.host?.displayName ?? 'Host';
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: colors.surfaceContainerHighest.withValues(alpha: 0.45),
        borderRadius: BorderRadius.circular(18),
        border: Border.all(
          color: colors.outlineVariant.withValues(alpha: 0.28),
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  hostName,
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ),
              Text(
                '+${commission.commissionAmount}',
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: colors.secondary,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            'Gross ${commission.grossAmount} · ${commission.commissionRateLabel} · Host net ${commission.hostNetAmount}',
            style: Theme.of(context).textTheme.bodySmall?.copyWith(
              color: colors.onSurfaceVariant,
            ),
          ),
        ],
      ),
    );
  }
}

final class _AgencyAvatar extends StatelessWidget {
  const _AgencyAvatar({required this.name, this.logo});

  final String? logo;
  final String name;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    if (logo != null && logo!.trim().isNotEmpty) {
      return ClipRRect(
        borderRadius: BorderRadius.circular(16),
        child: SizedBox(
          width: 52,
          height: 52,
          child: AppNetworkImage(url: logo!),
        ),
      );
    }
    return Container(
      width: 52,
      height: 52,
      decoration: BoxDecoration(
        color: colors.primary.withValues(alpha: 0.2),
        borderRadius: BorderRadius.circular(16),
      ),
      alignment: Alignment.center,
      child: Text(
        name.isEmpty ? 'A' : name.characters.first.toUpperCase(),
        style: Theme.of(context).textTheme.titleLarge?.copyWith(
          color: colors.primary,
          fontWeight: FontWeight.w800,
        ),
      ),
    );
  }
}

final class _StatusChip extends StatelessWidget {
  const _StatusChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: colors.primary.withValues(alpha: 0.18),
        borderRadius: BorderRadius.circular(999),
      ),
      child: Text(
        label,
        style: Theme.of(context).textTheme.labelSmall?.copyWith(
          color: colors.secondary,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }
}

final class _MiniStat extends StatelessWidget {
  const _MiniStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        Text(
          value,
          style: Theme.of(context).textTheme.titleSmall?.copyWith(
            fontWeight: FontWeight.w800,
          ),
        ),
        Text(
          label,
          style: Theme.of(context).textTheme.labelSmall?.copyWith(
            color: colors.onSurfaceVariant,
          ),
        ),
      ],
    );
  }
}

final class _StatTile extends StatelessWidget {
  const _StatTile({
    required this.label,
    required this.value,
    this.wide = false,
  });

  final String label;
  final String value;
  final bool wide;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return Container(
      width: wide ? double.infinity : null,
      padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 12),
      decoration: BoxDecoration(
        color: colors.surface.withValues(alpha: 0.28),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          Text(
            value,
            style: Theme.of(context).textTheme.titleMedium?.copyWith(
              fontWeight: FontWeight.w800,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: Theme.of(context).textTheme.labelMedium?.copyWith(
              color: colors.onSurface.withValues(alpha: 0.8),
            ),
          ),
        ],
      ),
    );
  }
}
