enum AppEnvironment { development, staging, production }

abstract final class Environment {
  static const String _name = String.fromEnvironment(
    'ENVIRONMENT',
    defaultValue: 'development',
  );

  /// Local Laravel (`php artisan serve`) default so plain `flutter run` works.
  /// Override in CI/release with `--dart-define=API_BASE_URL=...`.
  static const String apiBaseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://127.0.0.1:8000',
  );
  static const String agoraAppId = String.fromEnvironment('AGORA_APP_ID');
  static const bool enableFirebase = bool.fromEnvironment('ENABLE_FIREBASE');
  static const String broadcastHost = String.fromEnvironment('BROADCAST_HOST');
  static const String broadcastPort = String.fromEnvironment(
    'BROADCAST_PORT',
    defaultValue: '443',
  );
  static const String broadcastScheme = String.fromEnvironment(
    'BROADCAST_SCHEME',
    defaultValue: 'https',
  );
  static const String broadcastAppKey = String.fromEnvironment(
    'BROADCAST_APP_KEY',
  );

  static AppEnvironment get current => switch (_name.toLowerCase()) {
    'production' => AppEnvironment.production,
    'staging' => AppEnvironment.staging,
    _ => AppEnvironment.development,
  };

  static bool get isProduction => current == AppEnvironment.production;

  static bool get hasBroadcastConfig =>
      broadcastHost.isNotEmpty && broadcastAppKey.isNotEmpty;

  static String get broadcastWsUrl {
    final String scheme = broadcastScheme == 'http' ? 'ws' : 'wss';
    final String port = broadcastPort.isEmpty ? '' : ':$broadcastPort';
    return '$scheme://$broadcastHost$port/app/$broadcastAppKey';
  }
}
