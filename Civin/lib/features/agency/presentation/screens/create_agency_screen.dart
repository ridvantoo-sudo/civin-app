import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:civin/features/agency/presentation/agency_providers.dart';
import 'package:civin/features/agency/presentation/widgets/agency_theme.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class CreateAgency extends ConsumerStatefulWidget {
  const CreateAgency({super.key});

  @override
  ConsumerState<CreateAgency> createState() => _CreateAgencyState();
}

final class _CreateAgencyState extends ConsumerState<CreateAgency> {
  final GlobalKey<FormState> _formKey = GlobalKey<FormState>();
  late final TextEditingController _nameController;
  late final TextEditingController _descriptionController;
  late final TextEditingController _logoController;
  late final TextEditingController _commissionController;

  @override
  void initState() {
    super.initState();
    _nameController = TextEditingController();
    _descriptionController = TextEditingController();
    _logoController = TextEditingController();
    _commissionController = TextEditingController(text: '10');
  }

  @override
  void dispose() {
    _nameController.dispose();
    _descriptionController.dispose();
    _logoController.dispose();
    _commissionController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!_formKey.currentState!.validate()) return;
    final double? rate = double.tryParse(_commissionController.text.trim());
    final bool ok = await ref.read(agencyProvider.notifier).createAgency(
      CreateAgencyInput(
        name: _nameController.text,
        description: _descriptionController.text,
        logo: _logoController.text,
        commissionRate: rate,
      ),
    );
    if (!mounted) return;
    if (ok) {
      context.go(AppRoutes.agencyProfile);
    }
  }

  @override
  Widget build(BuildContext context) {
    final AgencyViewState state = ref.watch(agencyProvider);

    return Theme(
      data: agencyDarkTheme(context),
      child: Builder(
        builder: (BuildContext context) {
          final ColorScheme colors = Theme.of(context).colorScheme;
          return Scaffold(
            appBar: AppBar(title: const Text('Create agency')),
            body: DecoratedBox(
              decoration: agencyAtmosphere(colors),
              child: Form(
                key: _formKey,
                child: ListView(
                  padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
                  children: <Widget>[
                    Text(
                      'Launch your agency',
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Set a name, optional logo, and commission rate for hosts.',
                      style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                    const SizedBox(height: 24),
                    TextFormField(
                      controller: _nameController,
                      decoration: const InputDecoration(
                        labelText: 'Agency name',
                        border: OutlineInputBorder(),
                      ),
                      textCapitalization: TextCapitalization.words,
                      validator: (String? value) {
                        final String trimmed = value?.trim() ?? '';
                        if (trimmed.length < 2) {
                          return 'Enter at least 2 characters.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _descriptionController,
                      decoration: const InputDecoration(
                        labelText: 'Description (optional)',
                        border: OutlineInputBorder(),
                      ),
                      maxLines: 4,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _logoController,
                      decoration: const InputDecoration(
                        labelText: 'Logo URL (optional)',
                        border: OutlineInputBorder(),
                      ),
                      keyboardType: TextInputType.url,
                    ),
                    const SizedBox(height: 14),
                    TextFormField(
                      controller: _commissionController,
                      decoration: const InputDecoration(
                        labelText: 'Commission rate %',
                        border: OutlineInputBorder(),
                      ),
                      keyboardType: const TextInputType.numberWithOptions(
                        decimal: true,
                      ),
                      validator: (String? value) {
                        final double? rate = double.tryParse(
                          value?.trim() ?? '',
                        );
                        if (rate == null || rate < 0 || rate > 100) {
                          return 'Enter a rate between 0 and 100.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: state.isCreating ? null : _submit,
                      child: state.isCreating
                          ? const SizedBox(
                              width: 18,
                              height: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Text('Create agency'),
                    ),
                    if (state.errorMessage != null) ...<Widget>[
                      const SizedBox(height: 16),
                      AppErrorWidget(message: state.errorMessage!),
                    ],
                  ],
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}
