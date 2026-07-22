import 'dart:async';

import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/primary_button.dart';
import 'package:civin/features/authentication/presentation/auth_controller.dart';
import 'package:civin/features/authentication/widgets/auth_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class VerifyEmailPage extends ConsumerStatefulWidget {
  const VerifyEmailPage({super.key});

  @override
  ConsumerState<VerifyEmailPage> createState() => _VerifyEmailPageState();
}

final class _VerifyEmailPageState extends ConsumerState<VerifyEmailPage> {
  Timer? _timer;
  bool _checking = false;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(
      const Duration(seconds: 5),
      (_) => _check(silent: true),
    );
  }

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AuthViewState state = ref.watch(authControllerProvider);
    return PopScope(
      canPop: false,
      child: AuthPageShell(
        title: 'Verify your email',
        subtitle: 'Open the link we sent to your email, then return here.',
        showBackButton: false,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            const Center(
              child: Icon(Icons.mark_email_unread_outlined, size: 72),
            ),
            const SizedBox(height: 24),
            const AuthFeedback(),
            PrimaryButton(
              label: 'I have verified my email',
              isLoading: state.isLoading || _checking,
              onPressed: _check,
            ),
            const SizedBox(height: 8),
            OutlinedButton(
              onPressed: state.isLoading
                  ? null
                  : () => ref
                        .read(authControllerProvider.notifier)
                        .sendEmailVerification(),
              child: const Text('Resend verification email'),
            ),
            TextButton(
              onPressed: state.isLoading ? null : _signOut,
              child: const Text('Use a different account'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _check({bool silent = false}) async {
    if (_checking) return;
    setState(() => _checking = true);
    final user = await ref.read(authControllerProvider.notifier).reloadUser();
    if (mounted) setState(() => _checking = false);
    if (!mounted || user == null) return;
    if (user.isEmailVerified) {
      _timer?.cancel();
      context.go(
        (user.displayName?.trim().isEmpty ?? true)
            ? AppRoutes.completeProfile
            : AppRoutes.home,
      );
    } else if (!silent) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Your email is not verified yet.')),
      );
    }
  }

  Future<void> _signOut() async {
    await ref.read(authControllerProvider.notifier).signOut();
    if (mounted) context.go(AppRoutes.login);
  }
}
