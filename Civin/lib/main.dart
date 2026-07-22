import 'dart:async';

import 'package:civin/app.dart';
import 'package:civin/core/services/app_bootstrap.dart';
import 'package:civin/core/services/app_logger.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

Future<void> main() async {
  final AppLogger logger = AppLogger();
  await runZonedGuarded<Future<void>>(
    () async {
      await AppBootstrap.initialize();
      runApp(const ProviderScope(child: CivinApp()));
    },
    (Object error, StackTrace stackTrace) {
      logger.error(
        'Uncaught application error',
        error: error,
        stackTrace: stackTrace,
      );
    },
  );
}
