import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/core/widgets/text_field.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:civin/features/profile/presentation/social_providers.dart';
import 'package:civin/features/profile/repository/user_social_repository_impl.dart';
import 'package:civin/features/profile/widgets/social_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class ReportUserPage extends ConsumerStatefulWidget {
  const ReportUserPage({required this.userId, super.key, this.userName});

  final String userId;
  final String? userName;

  @override
  ConsumerState<ReportUserPage> createState() => _ReportUserPageState();
}

final class _ReportUserPageState extends ConsumerState<ReportUserPage> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  final TextEditingController _details = TextEditingController();
  String? _category;
  bool _submitting = false;

  @override
  void dispose() {
    _details.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AsyncValue<List<String>> categories = ref.watch(
      reportCategoriesProvider,
    );
    return Scaffold(
      appBar: AppBar(title: const Text('Report user')),
      body: SocialPageWidth(
        child: categories.when(
          loading: () => const AppLoadingWidget(),
          error: (Object error, StackTrace stackTrace) => AppErrorWidget(
            message: error.toString(),
            onRetry: () => ref.invalidate(reportCategoriesProvider),
          ),
          data: (List<String> values) => Form(
            key: _formKey,
            child: ListView(
              padding: const EdgeInsets.all(20),
              children: <Widget>[
                Text(
                  'Why are you reporting ${widget.userName ?? 'this user'}?',
                  style: Theme.of(context).textTheme.titleLarge,
                ),
                const SizedBox(height: 8),
                Text(
                  'Reports are confidential and reviewed by the Civin team.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: 24),
                DropdownButtonFormField<String>(
                  initialValue: _category,
                  decoration: const InputDecoration(labelText: 'Reason'),
                  items: values
                      .map(
                        (String value) => DropdownMenuItem<String>(
                          value: value,
                          child: Text(_categoryLabel(value)),
                        ),
                      )
                      .toList(growable: false),
                  onChanged: _submitting
                      ? null
                      : (String? value) => setState(() => _category = value),
                  validator: (String? value) =>
                      value == null ? 'Select a reason.' : null,
                ),
                const SizedBox(height: 16),
                AppTextField(
                  controller: _details,
                  label: 'Details',
                  hint: 'Tell us what happened',
                  maxLines: 5,
                  enabled: !_submitting,
                  validator: (String? value) {
                    final String text = value?.trim() ?? '';
                    if (_category == 'other' && text.isEmpty) {
                      return 'Details are required for Other.';
                    }
                    if (text.length > 1000) {
                      return 'Use 1000 characters or fewer.';
                    }
                    return null;
                  },
                ),
                const SizedBox(height: 24),
                FilledButton.icon(
                  onPressed: _submitting ? null : _submit,
                  icon: _submitting
                      ? const SizedBox.square(
                          dimension: 18,
                          child: CircularProgressIndicator.adaptive(
                            strokeWidth: 2,
                          ),
                        )
                      : const Icon(Icons.flag_rounded),
                  label: Text(_submitting ? 'Submitting…' : 'Submit report'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    setState(() => _submitting = true);
    final RepositoryResult<UserReport> result = await ref
        .read(userSocialRepositoryProvider)
        .reportUser(
          widget.userId,
          category: _category!,
          details: _details.text,
        );
    if (!mounted) return;
    setState(() => _submitting = false);
    result.fold(
      onSuccess: (UserReport report) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Report submitted. Thank you.')),
        );
        Navigator.pop(context);
      },
      onFailure: (failure) => ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(failure.message))),
    );
  }
}

String _categoryLabel(String value) => value
    .split('_')
    .map(
      (String word) =>
          word.isEmpty ? word : '${word[0].toUpperCase()}${word.substring(1)}',
    )
    .join(' ');
