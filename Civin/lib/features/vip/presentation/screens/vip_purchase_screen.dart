import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/vip/domain/entities/vip.dart';
import 'package:civin/features/vip/presentation/vip_providers.dart';
import 'package:civin/features/vip/presentation/widgets/vip_theme.dart';
import 'package:civin/features/vip/presentation/widgets/vip_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class VipPurchase extends ConsumerStatefulWidget {
  const VipPurchase({this.initialLevelId, super.key});

  final String? initialLevelId;

  @override
  ConsumerState<VipPurchase> createState() => _VipPurchaseState();
}

final class _VipPurchaseState extends ConsumerState<VipPurchase> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final VipController controller = ref.read(vipProvider.notifier);
      await controller.load();
      if (!mounted) return;
      final String? initial = widget.initialLevelId;
      if (initial != null && initial.isNotEmpty) {
        controller.selectLevel(initial);
        return;
      }
      final VipViewState state = ref.read(vipProvider);
      if (state.selectedLevelId == null && state.levels.isNotEmpty) {
        controller.selectLevel(state.levels.first.id);
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final VipViewState state = ref.watch(vipProvider);
    final VipLevel? selected = state.selectedLevel;
    final bool upgrading =
        state.isVip && selected != null && state.canUpgradeTo(selected);
    final bool purchasing =
        !state.isVip && selected != null && state.canPurchase(selected);

    return Theme(
      data: vipDarkTheme(context),
      child: Builder(
        builder: (BuildContext context) {
          final ColorScheme colors = Theme.of(context).colorScheme;
          return Scaffold(
            appBar: AppBar(
              title: Text(state.isVip ? 'Upgrade VIP' : 'Purchase VIP'),
            ),
            body: DecoratedBox(
              decoration: vipAtmosphere(colors),
              child: state.isLoading && state.levels.isEmpty
                  ? const AppLoadingWidget(message: 'Loading VIP store')
                  : state.errorMessage != null && state.levels.isEmpty
                  ? AppErrorWidget(
                      message: state.errorMessage!,
                      onRetry: () => ref.read(vipProvider.notifier).load(),
                    )
                  : Column(
                      children: <Widget>[
                        Expanded(
                          child: ListView(
                            padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
                            children: <Widget>[
                              VipStatusCard(subscription: state.subscription),
                              const SizedBox(height: 18),
                              Text(
                                'Choose a level',
                                style: Theme.of(context).textTheme.titleMedium
                                    ?.copyWith(fontWeight: FontWeight.w800),
                              ),
                              const SizedBox(height: 12),
                              for (final VipLevel level
                                  in state.levels) ...<Widget>[
                                VipLevelCard(
                                  level: level,
                                  selected: state.selectedLevelId == level.id,
                                  isCurrent:
                                      state.subscription.level?.id == level.id,
                                  onTap: () => ref
                                      .read(vipProvider.notifier)
                                      .selectLevel(level.id),
                                ),
                                const SizedBox(height: 12),
                              ],
                              if (selected != null) ...<Widget>[
                                const SizedBox(height: 8),
                                Text(
                                  'Benefits',
                                  style: Theme.of(context).textTheme.titleMedium
                                      ?.copyWith(fontWeight: FontWeight.w800),
                                ),
                                const SizedBox(height: 10),
                                VipBenefitsList(
                                  benefits: selected.privileges.benefitLabels,
                                ),
                              ],
                              if (state.errorMessage != null) ...<Widget>[
                                const SizedBox(height: 14),
                                Text(
                                  state.errorMessage!,
                                  style: TextStyle(color: colors.error),
                                ),
                              ],
                            ],
                          ),
                        ),
                        SafeArea(
                          top: false,
                          child: Padding(
                            padding: const EdgeInsets.fromLTRB(20, 8, 20, 16),
                            child: SizedBox(
                              width: double.infinity,
                              child: FilledButton(
                                onPressed: selected == null || state.isBusy
                                    ? null
                                    : () async {
                                        final bool ok = upgrading
                                            ? await ref
                                                  .read(vipProvider.notifier)
                                                  .upgradeSelected(
                                                    metadata:
                                                        const <String, dynamic>{
                                                          'source':
                                                              'vip_purchase',
                                                        },
                                                  )
                                            : purchasing
                                            ? await ref
                                                  .read(vipProvider.notifier)
                                                  .purchaseSelected(
                                                    metadata:
                                                        const <String, dynamic>{
                                                          'source':
                                                              'vip_purchase',
                                                        },
                                                  )
                                            : false;
                                        if (!context.mounted) return;
                                        if (ok) {
                                          ScaffoldMessenger.of(
                                            context,
                                          ).showSnackBar(
                                            SnackBar(
                                              content: Text(
                                                upgrading
                                                    ? 'VIP upgraded to ${selected.name}.'
                                                    : 'VIP ${selected.name} activated.',
                                              ),
                                            ),
                                          );
                                        }
                                      },
                                child: state.isBusy
                                    ? const SizedBox(
                                        width: 18,
                                        height: 18,
                                        child: CircularProgressIndicator(
                                          strokeWidth: 2,
                                        ),
                                      )
                                    : Text(
                                        selected == null
                                            ? 'Select a VIP level'
                                            : upgrading
                                            ? 'Upgrade - ${selected.priceLabel}'
                                            : purchasing
                                            ? 'Purchase - ${selected.priceLabel}'
                                            : state.subscription.level?.id ==
                                                  selected.id
                                            ? 'Current level'
                                            : 'Select a higher level',
                                      ),
                              ),
                            ),
                          ),
                        ),
                      ],
                    ),
            ),
          );
        },
      ),
    );
  }
}
