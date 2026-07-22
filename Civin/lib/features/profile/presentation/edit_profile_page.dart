import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/core/widgets/text_field.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:civin/features/profile/presentation/social_providers.dart';
import 'package:civin/features/profile/widgets/social_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class EditProfilePage extends ConsumerStatefulWidget {
  const EditProfilePage({super.key});

  @override
  ConsumerState<EditProfilePage> createState() => _EditProfilePageState();
}

final class _EditProfilePageState extends ConsumerState<EditProfilePage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _nickname = TextEditingController();
  final TextEditingController _bio = TextEditingController();
  final TextEditingController _avatar = TextEditingController();
  final TextEditingController _cover = TextEditingController();
  bool _initialized = false;
  bool _private = false;
  String? _gender;
  DateTime? _birthday;
  String? _countryId;

  @override
  void dispose() {
    _nickname.dispose();
    _bio.dispose();
    _avatar.dispose();
    _cover.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AsyncValue<UserProfile> profile = ref.watch(currentProfileProvider);
    return Scaffold(
      appBar: AppBar(title: const Text('Edit profile')),
      body: SocialPageWidth(
        child: profile.when(
          loading: () => const AppLoadingWidget(),
          error: (Object error, StackTrace stackTrace) => AppErrorWidget(
            message: error.toString(),
            onRetry: () => ref.read(currentProfileProvider.notifier).refresh(),
          ),
          data: (UserProfile value) {
            _initialize(value);
            return Form(
              key: _formKey,
              child: ListView(
                padding: const EdgeInsets.all(20),
                children: <Widget>[
                  AppTextField(
                    controller: _nickname,
                    label: 'Nickname',
                    textInputAction: TextInputAction.next,
                    validator: (String? text) {
                      final int length = text?.trim().length ?? 0;
                      if (length < 2) return 'Enter at least 2 characters.';
                      if (length > 80) return 'Use 80 characters or fewer.';
                      return null;
                    },
                  ),
                  const SizedBox(height: 16),
                  AppTextField(
                    controller: _bio,
                    label: 'Bio',
                    maxLines: 4,
                    validator: (String? text) => (text?.length ?? 0) > 500
                        ? 'Use 500 characters or fewer.'
                        : null,
                  ),
                  const SizedBox(height: 16),
                  AppTextField(
                    controller: _avatar,
                    label: 'Avatar URL',
                    keyboardType: TextInputType.url,
                    textInputAction: TextInputAction.next,
                  ),
                  const SizedBox(height: 16),
                  AppTextField(
                    controller: _cover,
                    label: 'Cover image URL',
                    keyboardType: TextInputType.url,
                  ),
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _gender,
                    decoration: const InputDecoration(labelText: 'Gender'),
                    items: const <DropdownMenuItem<String>>[
                      DropdownMenuItem(value: 'male', child: Text('Male')),
                      DropdownMenuItem(value: 'female', child: Text('Female')),
                      DropdownMenuItem(
                        value: 'non_binary',
                        child: Text('Non-binary'),
                      ),
                      DropdownMenuItem(
                        value: 'prefer_not_to_say',
                        child: Text('Prefer not to say'),
                      ),
                    ],
                    onChanged: (String? value) =>
                        setState(() => _gender = value),
                  ),
                  const SizedBox(height: 16),
                  ListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Birthday'),
                    subtitle: Text(
                      _birthday == null
                          ? 'Not set'
                          : MaterialLocalizations.of(
                              context,
                            ).formatMediumDate(_birthday!),
                    ),
                    trailing: const Icon(Icons.calendar_month_rounded),
                    onTap: _selectBirthday,
                  ),
                  SwitchListTile.adaptive(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Private account'),
                    subtitle: const Text(
                      'New followers will need your approval.',
                    ),
                    value: _private,
                    onChanged: (bool value) => setState(() => _private = value),
                  ),
                  const SizedBox(height: 24),
                  FilledButton.icon(
                    onPressed: _save,
                    icon: const Icon(Icons.save_rounded),
                    label: const Text('Save changes'),
                  ),
                ],
              ),
            );
          },
        ),
      ),
    );
  }

  void _initialize(UserProfile profile) {
    if (_initialized) return;
    _initialized = true;
    _nickname.text = profile.nickname ?? '';
    _bio.text = profile.bio ?? '';
    _avatar.text = profile.avatarUrl ?? '';
    _cover.text = profile.coverImageUrl ?? '';
    _private = profile.isPrivate;
    _gender = profile.gender;
    _birthday = profile.birthday;
    _countryId = profile.country?.id;
  }

  Future<void> _selectBirthday() async {
    final DateTime now = DateTime.now();
    final DateTime? value = await showDatePicker(
      context: context,
      initialDate: _birthday ?? DateTime(now.year - 18),
      firstDate: DateTime(1900),
      lastDate: now,
    );
    if (value != null && mounted) setState(() => _birthday = value);
  }

  Future<void> _save() async {
    if (!_formKey.currentState!.validate()) return;
    final bool saved = await ref
        .read(currentProfileProvider.notifier)
        .saveProfile(
          ProfileUpdate(
            nickname: _nickname.text,
            bio: _bio.text,
            avatarUrl: _avatar.text,
            coverImageUrl: _cover.text,
            countryId: _countryId,
            gender: _gender,
            birthday: _birthday,
            isPrivate: _private,
          ),
        );
    if (!mounted) return;
    if (saved) {
      Navigator.of(context).pop();
      return;
    }
    final Object error = ref.read(currentProfileProvider).error!;
    ScaffoldMessenger.of(
      context,
    ).showSnackBar(SnackBar(content: Text(error.toString())));
  }
}
