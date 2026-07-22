import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:logger/logger.dart';

final Provider<AppLogger> appLoggerProvider = Provider<AppLogger>(
  (Ref ref) => AppLogger(),
);

final class AppLogger {
  AppLogger()
    : _logger = Logger(
        printer: PrettyPrinter(
          methodCount: 0,
          errorMethodCount: 24,
          lineLength: 100,
          dateTimeFormat: DateTimeFormat.onlyTimeAndSinceStart,
        ),
      );

  final Logger _logger;

  void debug(Object? message) => _logger.d(message);
  void info(Object? message) => _logger.i(message);
  void warning(Object? message) => _logger.w(message);
  void error(Object? message, {Object? error, StackTrace? stackTrace}) =>
      _logger.e(message, error: error, stackTrace: stackTrace);
}
