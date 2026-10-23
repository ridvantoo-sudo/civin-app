import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/primary_button.dart';
import 'package:civin/features/authentication/presentation/auth_controller.dart';
import 'package:civin/features/authentication/services/biometric_service.dart';
import 'package:civin/features/authentication/widgets/auth_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final FutureProvider<bool> biometricEnabledProvider = FutureProvider<bool>(
  (Ref ref) => ref.watch(biometricServiceProvider).isEnabled,
);

final class AccountSecurityPage extends ConsumerWidget {
  const AccountSecurityPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AuthViewState state = ref.watch(authControllerProvider);
    final AsyncValue<bool> biometricEnabled = ref.watch(
      biometricEnabledProvider,
    );
    return AuthPageShell(
      title: 'Account security',
      subtitle: 'Manage sign-in and account access.',
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const AuthFeedback(),
          SwitchListTile.adaptive(
            contentPadding: EdgeInsets.zero,
            value: biometricEnabled.value ?? false,
            onChanged: state.isLoading || biometricEnabled.isLoading
                ? null
                : (bool enabled) => _setBiometrics(ref, enabled),
            secondary: const Icon(Icons.fingerprint),
            title: const Text('Biometric login'),
            subtitle: const Text('Protect automatic login on this device.'),
          ),
          const Divider(),
          ListTile(
            contentPadding: EdgeInsets.zero,
            leading: const Icon(Icons.refresh),
            title: const Text('Refresh secure session'),
            subtitle: const Text('Request a fresh Firebase identity token.'),
            onTap: state.isLoading
                ? null
                : () => ref
                      .read(authControllerProvider.notifier)
                      .restoreSession(forceRefresh: true),
          ),
          const SizedBox(height: 24),
          PrimaryButton(
            label: 'Log out',
            isLoading: state.isLoading,
            onPressed: () => _logout(context, ref),
          ),
          const SizedBox(height: 12),
          OutlinedButton.icon(
            style: OutlinedButton.styleFrom(
              foregroundColor: Theme.of(context).colorScheme.error,
            ),
            onPressed: state.isLoading
                ? null
                : () => _confirmDelete(context, ref),
            icon: const Icon(Icons.delete_forever_outlined),
            label: const Text('Delete account'),
          ),
        ],
      ),
    );
  }

  Future<void> _setBiometrics(WidgetRef ref, bool enabled) async {
    final AuthController controller = ref.read(authControllerProvider.notifier);
    if (enabled) {
      await controller.enableBiometrics();
    } else {
      await controller.disableBiometrics();
    }
    ref.invalidate(biometricEnabledProvider);
  }

  Future<void> _logout(BuildContext context, WidgetRef ref) async {
    final bool success = await ref
        .read(authControllerProvider.notifier)
        .signOut();
    if (context.mounted && success) context.go(AppRoutes.login);
  }

  Future<void> _confirmDelete(BuildContext context, WidgetRef ref) async {
    final bool confirmed =
        await showDialog<bool>(
          context: context,
          builder: (BuildContext dialogContext) => AlertDialog(
            title: const Text('Delete account permanently?'),
            content: const Text(
              'Your authentication account will be permanently deleted. '
              'This action cannot be undone.',
            ),
            actions: <Widget>[
              TextButton(
                onPressed: () => Navigator.pop(dialogContext, false),
                child: const Text('Cancel'),
              ),
              FilledButton(
                onPressed: () => Navigator.pop(dialogContext, true),
                child: const Text('Delete'),
              ),
            ],
          ),
        ) ??
        false;
    if (!confirmed || !context.mounted) return;
    final bool deleted = await ref
        .read(authControllerProvider.notifier)
        .deleteAccount();
    if (context.mounted && deleted) context.go(AppRoutes.login);
  }
}
