import 'package:flutter/material.dart';

final GlobalKey<ScaffoldMessengerState> scaffoldMessengerKey =
    GlobalKey<ScaffoldMessengerState>();

abstract final class AppSnackbar {
  static void show(
    String message, {
    bool isError = false,
    Duration duration = const Duration(seconds: 3),
  }) {
    final ScaffoldMessengerState? messenger = scaffoldMessengerKey.currentState;
    if (messenger == null) {
      return;
    }
    messenger
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(message),
          duration: duration,
          behavior: SnackBarBehavior.floating,
          backgroundColor: isError
              ? messenger.context.colorScheme.error
              : messenger.context.colorScheme.inverseSurface,
        ),
      );
  }
}

extension on BuildContext {
  ColorScheme get colorScheme => Theme.of(this).colorScheme;
}
