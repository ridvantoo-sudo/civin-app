import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/primary_button.dart';
import 'package:civin/core/widgets/text_field.dart';
import 'package:civin/features/authentication/domain/auth_validators.dart';
import 'package:civin/features/authentication/presentation/auth_controller.dart';
import 'package:civin/features/authentication/widgets/auth_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class RegisterPage extends ConsumerStatefulWidget {
  const RegisterPage({super.key});

  @override
  ConsumerState<RegisterPage> createState() => _RegisterPageState();
}

final class _RegisterPageState extends ConsumerState<RegisterPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _email = TextEditingController();
  final TextEditingController _password = TextEditingController();
  final TextEditingController _confirmPassword = TextEditingController();
  bool _obscure = true;

  @override
  void dispose() {
    _email.dispose();
    _password.dispose();
    _confirmPassword.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AuthViewState state = ref.watch(authControllerProvider);
    return AuthPageShell(
      title: 'Create your account',
      subtitle: 'Join Civin and start connecting.',
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            const AuthFeedback(),
            AppTextField(
              key: const Key('register_email'),
              controller: _email,
              label: 'Email',
              keyboardType: TextInputType.emailAddress,
              textInputAction: TextInputAction.next,
              prefixIcon: const Icon(Icons.email_outlined),
              autofillHints: const <String>[AutofillHints.newUsername],
              validator: AuthValidators.email,
            ),
            const SizedBox(height: 16),
            AppTextField(
              key: const Key('register_password'),
              controller: _password,
              label: 'Password',
              obscureText: _obscure,
              textInputAction: TextInputAction.next,
              prefixIcon: const Icon(Icons.lock_outline),
              suffixIcon: IconButton(
                tooltip: _obscure ? 'Show passwords' : 'Hide passwords',
                onPressed: () => setState(() => _obscure = !_obscure),
                icon: Icon(
                  _obscure ? Icons.visibility_outlined : Icons.visibility_off,
                ),
              ),
              autofillHints: const <String>[AutofillHints.newPassword],
              validator: AuthValidators.password,
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 8),
            PasswordStrengthMeter(password: _password.text),
            const SizedBox(height: 16),
            AppTextField(
              key: const Key('register_confirm_password'),
              controller: _confirmPassword,
              label: 'Confirm password',
              obscureText: _obscure,
              textInputAction: TextInputAction.done,
              prefixIcon: const Icon(Icons.lock_outline),
              autofillHints: const <String>[AutofillHints.newPassword],
              validator: (String? value) =>
                  AuthValidators.confirmPassword(value, _password.text),
              onSubmitted: (_) => _register(),
            ),
            const SizedBox(height: 24),
            PrimaryButton(
              label: 'Create account',
              isLoading: state.isLoading,
              onPressed: _register,
            ),
            TextButton(
              onPressed: state.isLoading ? null : () => context.pop(),
              child: const Text('Already have an account? Sign in'),
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _register() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final bool success = await ref
        .read(authControllerProvider.notifier)
        .register(_email.text, _password.text);
    if (!mounted || !success) return;
    await ref.read(authControllerProvider.notifier).sendEmailVerification();
    if (mounted) context.go(AppRoutes.verifyEmail);
  }
}
