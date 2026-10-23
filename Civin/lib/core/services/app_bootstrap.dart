import 'package:civin/core/config/environment.dart';
import 'package:civin/core/services/app_logger.dart';
import 'package:civin/core/services/firebase_service.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';

abstract final class AppBootstrap {
  static Future<void> initialize() async {
    WidgetsFlutterBinding.ensureInitialized();
    final AppLogger logger = AppLogger();

    FlutterError.onError = (FlutterErrorDetails details) {
      FlutterError.presentError(details);
      logger.error(
        'Flutter framework error',
        error: details.exception,
        stackTrace: details.stack,
      );
    };

    PlatformDispatcher.instance.onError = (Object error, StackTrace stack) {
      logger.error('Uncaught platform error', error: error, stackTrace: stack);
      return true;
    };

    await FirebaseService().initialize(enabled: Environment.enableFirebase);
  }
}
