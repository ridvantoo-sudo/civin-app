import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/primary_button.dart';
import 'package:civin/core/widgets/text_field.dart';
import 'package:civin/features/authentication/domain/auth_validators.dart';
import 'package:civin/features/authentication/presentation/auth_controller.dart';
import 'package:civin/features/authentication/widgets/auth_widgets.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class LoginPage extends ConsumerStatefulWidget {
  const LoginPage({super.key});

  @override
  ConsumerState<LoginPage> createState() => _LoginPageState();
}

final class _LoginPageState extends ConsumerState<LoginPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _password = TextEditingController();
  bool _obscurePassword = true;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AuthViewState state = ref.watch(authControllerProvider);
    return AuthPageShell(
      title: 'Login',
      subtitle: 'Sign in to continue to Civin.',
      showBackButton: false,
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            const AuthFeedback(),
            AppTextField(
              key: const Key('login_email'),
              controller: _email,
              label: 'Email',
              prefixIcon: const Icon(Icons.email_outlined),
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              autofillHints: const <String>[AutofillHints.email],
              validator: AuthValidators.email,
            ),
            const SizedBox(height: 16),
            AppTextField(
              key: const Key('login_password'),
              controller: _password,
              label: 'Password',
              prefixIcon: const Icon(Icons.lock_outline),
              suffixIcon: IconButton(
                tooltip: _obscurePassword ? 'Show password' : 'Hide password',
                onPressed: () =>
                    setState(() => _obscurePassword = !_obscurePassword),
                icon: Icon(
                  _obscurePassword
                      ? Icons.visibility_outlined
                      : Icons.visibility_off_outlined,
                ),
              ),
              obscureText: _obscurePassword,
              textInputAction: TextInputAction.done,
              autofillHints: const <String>[AutofillHints.password],
              validator: (String? value) =>
                  (value ?? '').isEmpty ? 'Password is required.' : null,
              onSubmitted: (_) => _signIn(),
            ),
            Align(
              alignment: Alignment.centerRight,
              child: TextButton(
                onPressed: state.isLoading
                    ? null
                    : () => context.push(AppRoutes.forgotPassword),
                child: const Text('Forgot password?'),
              ),
            ),
            PrimaryButton(
              label: 'Sign in',
              isLoading: state.isLoading,
              onPressed: _signIn,
            ),
            const SizedBox(height: 16),
            const OrDivider(),
            const SizedBox(height: 16),
            OutlinedButton.icon(
              onPressed: state.isLoading
                  ? null
                  : () => _social(
                      ref
                          .read(authControllerProvider.notifier)
                          .signInWithGoogle,
                    ),
              icon: const Icon(Icons.g_mobiledata, size: 28),
              label: const Text('Continue with Google'),
            ),
            if (!kIsWeb && defaultTargetPlatform == TargetPlatform.iOS) ...[
              const SizedBox(height: 8),
              OutlinedButton.icon(
                onPressed: state.isLoading
                    ? null
                    : () => _social(
                        ref
                            .read(authControllerProvider.notifier)
                            .signInWithApple,
                      ),
                icon: const Icon(Icons.apple),
                label: const Text('Continue with Apple'),
              ),
            ],
            const SizedBox(height: 8),
            OutlinedButton.icon(
              onPressed: state.isLoading
                  ? null
                  : () => context.push(AppRoutes.phoneLogin),
              icon: const Icon(Icons.phone_outlined),
              label: const Text('Continue with phone'),
            ),
            const SizedBox(height: 8),
            TextButton.icon(
              onPressed: state.isLoading
                  ? null
                  : () => _social(
                      ref.read(authControllerProvider.notifier).signInAsGuest,
                    ),
              icon: const Icon(Icons.person_outline),
              label: const Text('Continue as guest'),
            ),
            TextButton.icon(
              onPressed: state.isLoading ? null : _biometricLogin,
              icon: const Icon(Icons.fingerprint),
              label: const Text('Sign in with biometrics'),
            ),
            Wrap(
              alignment: WrapAlignment.center,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: <Widget>[
                const Text("Don't have an account?"),
                TextButton(
                  onPressed: state.isLoading
                      ? null
                      : () => context.push(AppRoutes.register),
                  child: const Text('Create account'),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _signIn() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final bool success = await ref
        .read(authControllerProvider.notifier)
        .signInWithEmail(_email.text, _password.text);
    if (success) _finishAuthentication();
  }

  Future<void> _social(Future<bool> Function() operation) async {
    if (await operation()) _finishAuthentication();
  }

  Future<void> _biometricLogin() async {
    final bool success = await ref
        .read(authControllerProvider.notifier)
        .restoreSession();
    if (success) _finishAuthentication();
  }

  void _finishAuthentication() {
    if (!mounted) return;
    final user = ref.read(authControllerProvider).session?.user;
    if (user == null) return;
    if (!user.isAnonymous && user.email != null && !user.isEmailVerified) {
      context.go(AppRoutes.verifyEmail);
    } else if (!user.isAnonymous &&
        (user.displayName?.trim().isEmpty ?? true)) {
      context.go(AppRoutes.completeProfile);
    } else {
      context.go(AppRoutes.home);
    }
  }
}
