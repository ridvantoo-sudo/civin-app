import 'package:civin/core/utils/responsive_helper.dart';
import 'package:civin/core/widgets/text_field.dart';
import 'package:civin/features/wallet/domain/entities/wallet.dart';
import 'package:civin/features/wallet/presentation/wallet_providers.dart';
import 'package:civin/features/wallet/presentation/widgets/wallet_balance_card.dart';
import 'package:civin/features/wallet/presentation/widgets/withdraw_status_chip.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class WithdrawScreen extends ConsumerStatefulWidget {
  const WithdrawScreen({super.key});

  @override
  ConsumerState<WithdrawScreen> createState() => _WithdrawScreenState();
}

final class _WithdrawScreenState extends ConsumerState<WithdrawScreen> {
  late final TextEditingController _diamondsController;

  @override
  void initState() {
    super.initState();
    _diamondsController = TextEditingController();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (ref.read(walletProvider).balance == null) {
        ref.read(walletProvider.notifier).load();
      }
    });
  }

  @override
  void dispose() {
    _diamondsController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final WithdrawFormState form = ref.watch(withdrawProvider);
    final WalletViewState wallet = ref.watch(walletProvider);
    final double horizontal = ResponsiveHelper.value(
      context,
      mobile: 20,
      tablet: 32,
      desktop: 48,
    );
    final double maxWidth = ResponsiveHelper.value(
      context,
      mobile: 560,
      tablet: 640,
      desktop: 720,
    );

    return Scaffold(
      appBar: AppBar(title: const Text('Withdraw diamonds')),
      body: Align(
        alignment: Alignment.topCenter,
        child: ConstrainedBox(
          constraints: BoxConstraints(maxWidth: maxWidth),
          child: ListView(
            padding: EdgeInsets.fromLTRB(horizontal, 16, horizontal, 28),
            children: [
              WalletBalanceCard(balance: wallet.balance),
              const SizedBox(height: 24),
              Text(
                'Request payout',
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              Text(
                'Withdrawals require admin approval. Diamonds are deducted only after approval.',
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                  color: Theme.of(
                    context,
                  ).colorScheme.onSurface.withValues(alpha: 0.65),
                ),
              ),
              const SizedBox(height: 20),
              AppTextField(
                controller: _diamondsController,
                label: 'Diamonds',
                hint: 'Enter amount',
                keyboardType: TextInputType.number,
                onChanged: (String value) {
                  final String digits = value.replaceAll(RegExp(r'[^0-9]'), '');
                  if (digits != value) {
                    _diamondsController.value = TextEditingValue(
                      text: digits,
                      selection: TextSelection.collapsed(offset: digits.length),
                    );
                  }
                  final int diamonds = int.tryParse(digits) ?? 0;
                  ref.read(withdrawProvider.notifier).setDiamonds(diamonds);
                },
              ),
              const SizedBox(height: 16),
              AnimatedSwitcher(
                duration: const Duration(milliseconds: 240),
                child: Align(
                  key: ValueKey<int>(form.amount),
                  alignment: Alignment.centerLeft,
                  child: Text(
                    'Estimated payout: \$${(form.amount / 100).toStringAsFixed(2)}',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ),
              if (form.errorMessage != null) ...[
                const SizedBox(height: 12),
                Text(
                  form.errorMessage!,
                  style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                    color: Theme.of(context).colorScheme.error,
                  ),
                ),
              ],
              const SizedBox(height: 24),
              FilledButton(
                onPressed: form.isSubmitting
                    ? null
                    : () async {
                        final bool ok = await ref
                            .read(withdrawProvider.notifier)
                            .submit();
                        if (!context.mounted) return;
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              ok
                                  ? 'Withdrawal submitted for review'
                                  : (ref
                                            .read(withdrawProvider)
                                            .errorMessage ??
                                        'Withdrawal failed'),
                            ),
                          ),
                        );
                      },
                child: form.isSubmitting
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Text('Submit withdrawal'),
              ),
              if (wallet.recentWithdrawals.isNotEmpty) ...[
                const SizedBox(height: 32),
                Text(
                  'Withdrawal status',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                ...wallet.recentWithdrawals.map(
                  (WithdrawRequest request) =>
                      WithdrawRequestTile(request: request),
                ),
              ] else if (form.lastRequest != null) ...[
                const SizedBox(height: 32),
                Text(
                  'Withdrawal status',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(height: 8),
                WithdrawRequestTile(request: form.lastRequest!),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
