import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/settings/presentation/preferences_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class PreferencesScreen extends ConsumerStatefulWidget {
  const PreferencesScreen({super.key});

  @override
  ConsumerState<PreferencesScreen> createState() => _PreferencesScreenState();
}

final class _PreferencesScreenState extends ConsumerState<PreferencesScreen> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(preferencesProvider.notifier).load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final PreferencesViewState view = ref.watch(preferencesProvider);
    final UserPreferences prefs = view.preferences;
    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: view.isLoading
          ? const AppLoadingWidget(message: 'Loading settings')
          : view.errorMessage != null && !view.isSaving
          ? AppErrorWidget(
              message: view.errorMessage!,
              onRetry: () => ref.read(preferencesProvider.notifier).load(),
            )
          : ListView(
              children: <Widget>[
                const ListTile(title: Text('Appearance')),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: SegmentedButton<String>(
                    segments: const <ButtonSegment<String>>[
                      ButtonSegment<String>(
                        value: 'system',
                        label: Text('System'),
                      ),
                      ButtonSegment<String>(
                        value: 'light',
                        label: Text('Light'),
                      ),
                      ButtonSegment<String>(value: 'dark', label: Text('Dark')),
                    ],
                    selected: <String>{prefs.theme},
                    onSelectionChanged: view.isSaving
                        ? null
                        : (Set<String> value) {
                            ref
                                .read(preferencesProvider.notifier)
                                .update(prefs.copyWith(theme: value.first));
                          },
                  ),
                ),
                SwitchListTile(
                  title: const Text('Push notifications'),
                  value: prefs.pushEnabled,
                  onChanged: view.isSaving
                      ? null
                      : (bool value) {
                          ref
                              .read(preferencesProvider.notifier)
                              .update(prefs.copyWith(pushEnabled: value));
                        },
                ),
                ListTile(
                  title: const Text('Language'),
                  subtitle: Text(prefs.locale),
                  trailing: DropdownButton<String>(
                    value: prefs.locale,
                    items: civinLocales
                        .map(
                          (String code) => DropdownMenuItem<String>(
                            value: code,
                            child: Text(code),
                          ),
                        )
                        .toList(growable: false),
                    onChanged: view.isSaving
                        ? null
                        : (String? value) {
                            if (value == null) return;
                            ref
                                .read(preferencesProvider.notifier)
                                .update(prefs.copyWith(locale: value));
                          },
                  ),
                ),
                SwitchListTile(
                  title: const Text('Share location'),
                  subtitle: const Text('Used for nearby discovery'),
                  value: prefs.locationSharing,
                  onChanged: view.isSaving
                      ? null
                      : (bool value) {
                          ref
                              .read(preferencesProvider.notifier)
                              .update(prefs.copyWith(locationSharing: value));
                        },
                ),
                SwitchListTile(
                  title: const Text('Show distance'),
                  value: prefs.showDistance,
                  onChanged: view.isSaving
                      ? null
                      : (bool value) {
                          ref
                              .read(preferencesProvider.notifier)
                              .update(prefs.copyWith(showDistance: value));
                        },
                ),
                ListTile(
                  title: const Text('Nearby visibility'),
                  trailing: DropdownButton<String>(
                    value: prefs.nearbyVisibility,
                    items: const <DropdownMenuItem<String>>[
                      DropdownMenuItem<String>(
                        value: 'everyone',
                        child: Text('Everyone'),
                      ),
                      DropdownMenuItem<String>(
                        value: 'followers',
                        child: Text('Followers'),
                      ),
                      DropdownMenuItem<String>(
                        value: 'nobody',
                        child: Text('Nobody'),
                      ),
                    ],
                    onChanged: view.isSaving
                        ? null
                        : (String? value) {
                            if (value == null) return;
                            ref
                                .read(preferencesProvider.notifier)
                                .update(
                                  prefs.copyWith(nearbyVisibility: value),
                                );
                          },
                  ),
                ),
              ],
            ),
    );
  }
}
