import 'package:civin/core/constants/app_sizes.dart';
import 'package:civin/core/network/network_checker.dart';
import 'package:civin/features/authentication/domain/auth_validators.dart';
import 'package:civin/features/authentication/presentation/auth_controller.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class AuthPageShell extends ConsumerWidget {
  const AuthPageShell({
    required this.title,
    required this.subtitle,
    required this.child,
    super.key,
    this.showBackButton = true,
  });

  final String title;
  final String subtitle;
  final Widget child;
  final bool showBackButton;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bool isOffline = ref.watch(connectivityProvider).value == false;
    return Scaffold(
      appBar: showBackButton ? AppBar() : null,
      body: SafeArea(
        child: Column(
          children: <Widget>[
            AnimatedSize(
              duration: const Duration(milliseconds: 200),
              child: isOffline
                  ? Material(
                      color: Theme.of(context).colorScheme.errorContainer,
                      child: const ListTile(
                        dense: true,
                        leading: Icon(Icons.cloud_off_outlined),
                        title: Text('You are offline'),
                        subtitle: Text('Connect to the internet to continue.'),
                      ),
                    )
                  : const SizedBox.shrink(),
            ),
            Expanded(
              child: Center(
                child: SingleChildScrollView(
                  padding: AppSizes.pagePadding,
                  child: ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 480),
                    child: AutofillGroup(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.stretch,
                        children: <Widget>[
                          Semantics(
                            header: true,
                            child: Text(
                              title,
                              style: Theme.of(context).textTheme.headlineMedium,
                              textAlign: TextAlign.center,
                            ),
                          ),
                          const SizedBox(height: AppSizes.space8),
                          Text(
                            subtitle,
                            style: Theme.of(context).textTheme.bodyLarge,
                            textAlign: TextAlign.center,
                          ),
                          const SizedBox(height: AppSizes.space32),
                          child,
                        ],
                      ),
                    ),
                  ),
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final class AuthFeedback extends ConsumerWidget {
  const AuthFeedback({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AuthViewState state = ref.watch(authControllerProvider);
    final String? text = state.error ?? state.message;
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 180),
      child: text == null
          ? const SizedBox.shrink()
          : Container(
              key: ValueKey<String>(text),
              margin: const EdgeInsets.only(bottom: AppSizes.space16),
              padding: const EdgeInsets.all(AppSizes.space12),
              decoration: BoxDecoration(
                color: state.error == null
                    ? Theme.of(context).colorScheme.primaryContainer
                    : Theme.of(context).colorScheme.errorContainer,
                borderRadius: BorderRadius.circular(AppSizes.radiusMedium),
              ),
              child: Semantics(liveRegion: true, child: Text(text)),
            ),
    );
  }
}

final class PasswordStrengthMeter extends StatelessWidget {
  const PasswordStrengthMeter({required this.password, super.key});

  final String password;

  @override
  Widget build(BuildContext context) {
    final PasswordStrength strength = AuthValidators.passwordStrength(password);
    final double value = switch (strength) {
      PasswordStrength.empty => 0,
      PasswordStrength.weak => .25,
      PasswordStrength.fair => .6,
      PasswordStrength.strong => 1,
    };
    final String label = switch (strength) {
      PasswordStrength.empty => 'Enter a secure password',
      PasswordStrength.weak => 'Weak password',
      PasswordStrength.fair => 'Fair password',
      PasswordStrength.strong => 'Strong password',
    };
    final Color color = switch (strength) {
      PasswordStrength.empty => Theme.of(context).colorScheme.outline,
      PasswordStrength.weak => Theme.of(context).colorScheme.error,
      PasswordStrength.fair => Colors.orange,
      PasswordStrength.strong => Colors.green,
    };
    return Semantics(
      label: label,
      value: '${(value * 100).round()} percent',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          LinearProgressIndicator(
            value: value,
            color: color,
            borderRadius: BorderRadius.circular(AppSizes.radiusSmall),
          ),
          const SizedBox(height: AppSizes.space4),
          Text(label, style: Theme.of(context).textTheme.bodySmall),
        ],
      ),
    );
  }
}

final class OrDivider extends StatelessWidget {
  const OrDivider({super.key});

  @override
  Widget build(BuildContext context) => const Row(
    children: <Widget>[
      Expanded(child: Divider()),
      Padding(
        padding: EdgeInsets.symmetric(horizontal: AppSizes.space12),
        child: Text('or'),
      ),
      Expanded(child: Divider()),
    ],
  );
}
