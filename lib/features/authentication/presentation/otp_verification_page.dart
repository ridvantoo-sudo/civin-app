import 'dart:async';

import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/primary_button.dart';
import 'package:civin/core/widgets/text_field.dart';
import 'package:civin/features/authentication/domain/auth_validators.dart';
import 'package:civin/features/authentication/presentation/auth_controller.dart';
import 'package:civin/features/authentication/widgets/auth_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class OtpVerificationPage extends ConsumerStatefulWidget {
  const OtpVerificationPage({super.key});

  @override
  ConsumerState<OtpVerificationPage> createState() =>
      _OtpVerificationPageState();
}

final class _OtpVerificationPageState
    extends ConsumerState<OtpVerificationPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _code = TextEditingController();
  Timer? _timer;
  int _secondsRemaining = 60;

  @override
  void initState() {
    super.initState();
    _timer = Timer.periodic(const Duration(seconds: 1), (Timer timer) {
      if (!mounted) return;
      if (_secondsRemaining == 0) {
        timer.cancel();
      } else {
        setState(() => _secondsRemaining--);
      }
    });
  }

  @override
  void dispose() {
    _timer?.cancel();
    _code.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AuthViewState state = ref.watch(authControllerProvider);
    return AuthPageShell(
      title: 'Enter verification code',
      subtitle: 'Enter the 6-digit code sent to your phone.',
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: <Widget>[
            const AuthFeedback(),
            AppTextField(
              key: const Key('otp_code'),
              controller: _code,
              label: 'Verification code',
              prefixIcon: const Icon(Icons.password_outlined),
              keyboardType: TextInputType.number,
              textInputAction: TextInputAction.done,
              autofillHints: const <String>[AutofillHints.oneTimeCode],
              validator: AuthValidators.otp,
              onSubmitted: (_) => _verify(),
            ),
            const SizedBox(height: 8),
            Text(
              _secondsRemaining > 0
                  ? 'You can request another code in $_secondsRemaining seconds.'
                  : 'Did not receive a code? Go back and request another.',
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: 24),
            PrimaryButton(
              label: 'Verify code',
              isLoading: state.isLoading,
              onPressed: _verify,
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _verify() async {
    TextInput.finishAutofillContext();
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final bool success = await ref
        .read(authControllerProvider.notifier)
        .verifyPhoneCode(_code.text);
    if (!mounted || !success) return;
    final user = ref.read(authControllerProvider).session?.user;
    context.go(
      (user?.displayName?.trim().isEmpty ?? true)
          ? AppRoutes.completeProfile
          : AppRoutes.home,
    );
  }
}
