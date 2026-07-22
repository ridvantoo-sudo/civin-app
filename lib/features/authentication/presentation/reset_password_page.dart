import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/primary_button.dart';
import 'package:civin/core/widgets/text_field.dart';
import 'package:civin/features/authentication/domain/auth_validators.dart';
import 'package:civin/features/authentication/presentation/auth_controller.dart';
import 'package:civin/features/authentication/widgets/auth_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class ResetPasswordPage extends ConsumerStatefulWidget {
  const ResetPasswordPage({required this.code, super.key});

  final String code;

  @override
  ConsumerState<ResetPasswordPage> createState() => _ResetPasswordPageState();
}

final class _ResetPasswordPageState extends ConsumerState<ResetPasswordPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _password = TextEditingController();
  final TextEditingController _confirm = TextEditingController();
  bool _obscure = true;

  @override
  void dispose() {
    _password.dispose();
    _confirm.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AuthViewState state = ref.watch(authControllerProvider);
    return AuthPageShell(
      title: 'Reset password',
      subtitle: 'Choose a new password for your account.',
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            const AuthFeedback(),
            if (widget.code.isEmpty)
              Text(
                'This reset link is invalid. Request a new link.',
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            AppTextField(
              controller: _password,
              label: 'New password',
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
              validator: AuthValidators.password,
              onChanged: (_) => setState(() {}),
            ),
            const SizedBox(height: 8),
            PasswordStrengthMeter(password: _password.text),
            const SizedBox(height: 16),
            AppTextField(
              controller: _confirm,
              label: 'Confirm new password',
              obscureText: _obscure,
              textInputAction: TextInputAction.done,
              prefixIcon: const Icon(Icons.lock_outline),
              validator: (String? value) =>
                  AuthValidators.confirmPassword(value, _password.text),
              onSubmitted: (_) => _submit(),
            ),
            const SizedBox(height: 24),
            PrimaryButton(
              label: 'Reset password',
              isLoading: state.isLoading,
              onPressed: widget.code.isEmpty ? null : _submit,
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final bool success = await ref
        .read(authControllerProvider.notifier)
        .confirmPasswordReset(widget.code, _password.text);
    if (mounted && success) context.go(AppRoutes.login);
  }
}
