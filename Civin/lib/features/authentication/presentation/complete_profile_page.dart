import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/primary_button.dart';
import 'package:civin/core/widgets/text_field.dart';
import 'package:civin/features/authentication/domain/auth_validators.dart';
import 'package:civin/features/authentication/presentation/auth_controller.dart';
import 'package:civin/features/authentication/widgets/auth_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class CompleteProfilePage extends ConsumerStatefulWidget {
  const CompleteProfilePage({super.key});

  @override
  ConsumerState<CompleteProfilePage> createState() =>
      _CompleteProfilePageState();
}

final class _CompleteProfilePageState
    extends ConsumerState<CompleteProfilePage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _displayName = TextEditingController();
  final TextEditingController _photoUrl = TextEditingController();
  bool _enableBiometrics = false;

  @override
  void dispose() {
    _displayName.dispose();
    _photoUrl.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AuthViewState state = ref.watch(authControllerProvider);
    return PopScope(
      canPop: false,
      child: AuthPageShell(
        title: 'Complete your profile',
        subtitle: 'Choose how people will recognize you on Civin.',
        showBackButton: false,
        child: Form(
          key: _formKey,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: <Widget>[
              const AuthFeedback(),
              AppTextField(
                key: const Key('profile_display_name'),
                controller: _displayName,
                label: 'Display name',
                prefixIcon: const Icon(Icons.person_outline),
                textInputAction: TextInputAction.next,
                autofillHints: const <String>[AutofillHints.nickname],
                validator: AuthValidators.displayName,
              ),
              const SizedBox(height: 16),
              AppTextField(
                controller: _photoUrl,
                label: 'Profile photo URL (optional)',
                prefixIcon: const Icon(Icons.image_outlined),
                keyboardType: TextInputType.url,
                textInputAction: TextInputAction.done,
                validator: _validateUrl,
                onSubmitted: (_) => _submit(),
              ),
              const SizedBox(height: 16),
              SwitchListTile.adaptive(
                contentPadding: EdgeInsets.zero,
                value: _enableBiometrics,
                onChanged: state.isLoading
                    ? null
                    : (bool value) => setState(() => _enableBiometrics = value),
                title: const Text('Enable biometric login'),
                subtitle: const Text(
                  'Require Face ID, Touch ID, or fingerprint on this device.',
                ),
                secondary: const Icon(Icons.fingerprint),
              ),
              const SizedBox(height: 24),
              PrimaryButton(
                label: 'Continue',
                isLoading: state.isLoading,
                onPressed: _submit,
              ),
            ],
          ),
        ),
      ),
    );
  }

  String? _validateUrl(String? value) {
    final String input = value?.trim() ?? '';
    if (input.isEmpty) return null;
    final Uri? uri = Uri.tryParse(input);
    if (uri == null ||
        !uri.hasAbsolutePath ||
        (uri.scheme != 'https' && uri.scheme != 'http')) {
      return 'Enter a valid http or https URL.';
    }
    return null;
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final AuthController controller = ref.read(authControllerProvider.notifier);
    final bool profileUpdated = await controller.updateProfile(
      _displayName.text,
      photoUrl: _photoUrl.text.trim().isEmpty ? null : _photoUrl.text.trim(),
    );
    if (!profileUpdated || !mounted) return;
    if (_enableBiometrics && !await controller.enableBiometrics()) return;
    if (mounted) context.go(AppRoutes.home);
  }
}
