import 'package:civin/core/utils/responsive_helper.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:civin/features/wallet/presentation/wallet_providers.dart';
import 'package:civin/features/wallet/presentation/widgets/recharge_package_card.dart';
import 'package:civin/features/wallet/presentation/widgets/wallet_balance_card.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class RechargeScreen extends ConsumerStatefulWidget {
  const RechargeScreen({super.key});

  @override
  ConsumerState<RechargeScreen> createState() => _RechargeScreenState();
}

final class _RechargeScreenState extends ConsumerState<RechargeScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (ref.read(walletProvider).balance == null) {
        ref.read(walletProvider.notifier).load();
      }
    });
  }

  @override
  Widget build(BuildContext context) {
    final RechargeState recharge = ref.watch(rechargeProvider);
    final WalletViewState wallet = ref.watch(walletProvider);
    final int crossAxisCount = ResponsiveHelper.deviceTypeOf(context) ==
            DeviceType.mobile
        ? 2
        : 3;
    final double horizontal = ResponsiveHelper.value(
      context,
      mobile: 16,
      tablet: 28,
      desktop: 40,
    );
    final double maxWidth = ResponsiveHelper.value(
      context,
      mobile: 720,
      tablet: 900,
      desktop: 980,
    );

    return Scaffold(
      appBar: AppBar(title: const Text('Recharge coins')),
      body: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxWidth),
          child: Column(
            children: [
              Expanded(
                child: ListView(
                  padding: EdgeInsets.fromLTRB(horizontal, 16, horizontal, 16),
                  children: [
                    WalletBalanceCard(balance: wallet.balance),
                    const SizedBox(height: 20),
                    Text(
                      'Choose a package',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    const SizedBox(height: 12),
                    GridView.builder(
                      shrinkWrap: true,
                      physics: const NeverScrollableScrollPhysics(),
                      itemCount: recharge.packages.length,
                      gridDelegate: SliverGridDelegateWithFixedCrossAxisCount(
                        crossAxisCount: crossAxisCount,
                        mainAxisSpacing: 12,
                        crossAxisSpacing: 12,
                        childAspectRatio: 0.92,
                      ),
                      itemBuilder: (BuildContext context, int index) {
                        final RechargePackage package = recharge.packages[index];
                        return RechargePackageCard(
                          package: package,
                          selected: package.id == recharge.selectedPackageId,
                          onTap: () => ref
                              .read(rechargeProvider.notifier)
                              .selectPackage(package.id),
                        );
                      },
                    ),
                    if (recharge.errorMessage != null) ...[
                      const SizedBox(height: 16),
                      Text(
                        recharge.errorMessage!,
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: Theme.of(context).colorScheme.error,
                        ),
                      ),
                    ],
                    if (recharge.lastOrder != null) ...[
                      const SizedBox(height: 16),
                      Text(
                        'Last order: ${recharge.lastOrder!.packageName} · '
                        '${recharge.lastOrder!.coins} coins',
                        style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                          color: const Color(0xFF21C17A),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              SafeArea(
                top: false,
                child: Padding(
                  padding: EdgeInsets.fromLTRB(horizontal, 8, horizontal, 16),
                  child: FilledButton(
                    onPressed: recharge.isSubmitting
                        ? null
                        : () async {
                            final bool ok = await ref
                                .read(rechargeProvider.notifier)
                                .purchase();
                            if (!context.mounted) return;
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(
                                  ok
                                      ? 'Recharge completed'
                                      : (ref
                                                .read(rechargeProvider)
                                                .errorMessage ??
                                            'Recharge failed'),
                                ),
                              ),
                            );
                          },
                    child: recharge.isSubmitting
                        ? const SizedBox(
                            width: 22,
                            height: 22,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : Text(
                            recharge.selectedPackage == null
                                ? 'Select a package'
                                : 'Buy ${recharge.selectedPackage!.totalCoins} coins',
                          ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
