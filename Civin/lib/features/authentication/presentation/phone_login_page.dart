import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/primary_button.dart';
import 'package:civin/core/widgets/text_field.dart';
import 'package:civin/features/authentication/domain/auth_validators.dart';
import 'package:civin/features/authentication/presentation/auth_controller.dart';
import 'package:civin/features/authentication/widgets/auth_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class PhoneLoginPage extends ConsumerStatefulWidget {
  const PhoneLoginPage({super.key});

  @override
  ConsumerState<PhoneLoginPage> createState() => _PhoneLoginPageState();
}

final class _PhoneLoginPageState extends ConsumerState<PhoneLoginPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _phone = TextEditingController();

  @override
  void dispose() {
    _phone.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AuthViewState state = ref.watch(authControllerProvider);
    return AuthPageShell(
      title: 'Sign in with phone',
      subtitle: 'We will send a one-time code by SMS.',
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            const AuthFeedback(),
            AppTextField(
              key: const Key('phone_number'),
              controller: _phone,
              label: 'Phone number',
              hint: '+15551234567',
              prefixIcon: const Icon(Icons.phone_outlined),
              keyboardType: TextInputType.phone,
              textInputAction: TextInputAction.done,
              autofillHints: const <String>[AutofillHints.telephoneNumber],
              validator: AuthValidators.phone,
              onSubmitted: (_) async {
                await _submit();
              },
            ),
            const SizedBox(height: 8),
            Text(
              'Use international format including the country code.',
              style: Theme.of(context).textTheme.bodySmall,
            ),
            const SizedBox(height: 24),
            PrimaryButton(
              label: 'Send code',
              isLoading: state.isLoading,
              onPressed: _submit,
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
        .sendPhoneCode(AuthValidators.normalizePhone(_phone.text));
    if (mounted && success) await context.push(AppRoutes.otpVerification);
  }
}
