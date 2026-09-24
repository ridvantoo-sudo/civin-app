import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/network/dio_client.dart';
import 'package:civin/core/theme/theme.dart';
import 'package:civin/core/utils/json_values.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

const List<String> civinLocales = <String>[
  'en',
  'ar',
  'ckb',
  'kmr',
  'tr',
  'fa',
  'de',
  'fr',
];

final class UserPreferences {
  const UserPreferences({
    this.theme = 'system',
    this.pushEnabled = true,
    this.locale = 'en',
    this.locationSharing = false,
    this.showDistance = true,
    this.nearbyVisibility = 'everyone',
  });

  final String theme;
  final bool pushEnabled;
  final String locale;
  final bool locationSharing;
  final bool showDistance;
  final String nearbyVisibility;

  ThemeMode get themeMode => switch (theme) {
    'light' => ThemeMode.light,
    'dark' => ThemeMode.dark,
    _ => ThemeMode.system,
  };

  UserPreferences copyWith({
    String? theme,
    bool? pushEnabled,
    String? locale,
    bool? locationSharing,
    bool? showDistance,
    String? nearbyVisibility,
  }) => UserPreferences(
    theme: theme ?? this.theme,
    pushEnabled: pushEnabled ?? this.pushEnabled,
    locale: locale ?? this.locale,
    locationSharing: locationSharing ?? this.locationSharing,
    showDistance: showDistance ?? this.showDistance,
    nearbyVisibility: nearbyVisibility ?? this.nearbyVisibility,
  );

  Map<String, Object?> toJson() => <String, Object?>{
    'theme': theme,
    'push_enabled': pushEnabled,
    'locale': locale,
    'location_sharing_enabled': locationSharing,
    'show_distance': showDistance,
    'nearby_visibility': nearbyVisibility,
  };
}

final NotifierProvider<PreferencesController, PreferencesViewState>
preferencesProvider =
    NotifierProvider<PreferencesController, PreferencesViewState>(
      PreferencesController.new,
    );

final class PreferencesViewState {
  const PreferencesViewState({
    this.preferences = const UserPreferences(),
    this.isLoading = false,
    this.isSaving = false,
    this.errorMessage,
  });

  final UserPreferences preferences;
  final bool isLoading;
  final bool isSaving;
  final String? errorMessage;

  PreferencesViewState copyWith({
    UserPreferences? preferences,
    bool? isLoading,
    bool? isSaving,
    String? errorMessage,
    bool clearError = false,
  }) => PreferencesViewState(
    preferences: preferences ?? this.preferences,
    isLoading: isLoading ?? this.isLoading,
    isSaving: isSaving ?? this.isSaving,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
  );
}

final class PreferencesController extends Notifier<PreferencesViewState> {
  @override
  PreferencesViewState build() => const PreferencesViewState();

  Future<void> load() async {
    state = state.copyWith(isLoading: true, clearError: true);
    final RepositoryResult<UserPreferences> result = await const _SettingsRepo()
        .execute(_fetch);
    if (!ref.mounted) return;
    result.fold(
      onSuccess: (UserPreferences value) {
        state = state.copyWith(
          isLoading: false,
          preferences: value,
          clearError: true,
        );
        ref.read(themeModeProvider.notifier).setMode(value.themeMode);
      },
      onFailure: (failure) {
        state = state.copyWith(isLoading: false, errorMessage: failure.message);
      },
    );
  }

  Future<void> update(UserPreferences next) async {
    state = state.copyWith(isSaving: true, preferences: next, clearError: true);
    ref.read(themeModeProvider.notifier).setMode(next.themeMode);
    final RepositoryResult<UserPreferences> result = await const _SettingsRepo()
        .execute(() => _save(next));
    if (!ref.mounted) return;
    result.fold(
      onSuccess: (UserPreferences value) {
        state = state.copyWith(
          isSaving: false,
          preferences: value,
          clearError: true,
        );
        ref.read(themeModeProvider.notifier).setMode(value.themeMode);
      },
      onFailure: (failure) {
        state = state.copyWith(isSaving: false, errorMessage: failure.message);
      },
    );
  }

  Future<UserPreferences> _fetch() async {
    final Response<dynamic> response = await ref
        .read(dioClientProvider)
        .get<dynamic>('/api/v1/user-settings');
    final Object? data = response.data;
    if (data is! Map<String, dynamic>) {
      throw const FormatException('Invalid settings envelope.');
    }
    return _parse(data['data']);
  }

  Future<UserPreferences> _save(UserPreferences next) async {
    final Response<dynamic> response = await ref
        .read(dioClientProvider)
        .put<dynamic>(
          '/api/v1/user-settings',
          data: <String, dynamic>{'settings': next.toJson()},
        );
    final Object? data = response.data;
    if (data is! Map<String, dynamic>) {
      throw const FormatException('Invalid settings envelope.');
    }
    return _parse(data['data']);
  }

  UserPreferences _parse(Object? data) {
    final Map<String, Object?> values = JsonValues.asMap(data);
    final String locale = JsonValues.asString(values['locale'], fallback: 'en');
    return UserPreferences(
      theme: JsonValues.asString(values['theme'], fallback: 'system'),
      pushEnabled: JsonValues.asBool(values['push_enabled'], fallback: true),
      locale: civinLocales.contains(locale) ? locale : 'en',
      locationSharing: JsonValues.asBool(values['location_sharing_enabled']),
      showDistance: JsonValues.asBool(values['show_distance'], fallback: true),
      nearbyVisibility: JsonValues.asString(
        values['nearby_visibility'],
        fallback: 'everyone',
      ),
    );
  }
}

final class _SettingsRepo extends BaseRepository {
  const _SettingsRepo();
}
