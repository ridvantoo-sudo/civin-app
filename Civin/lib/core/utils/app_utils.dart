import 'dart:async';

import 'package:flutter/widgets.dart';

abstract final class AppUtils {
  static void unfocus(BuildContext context) => FocusScope.of(context).unfocus();
}

final class Debouncer {
  Debouncer({required this.duration});

  final Duration duration;
  Timer? _timer;

  void run(VoidCallback action) {
    _timer?.cancel();
    _timer = Timer(duration, action);
  }

  void dispose() {
    _timer?.cancel();
    _timer = null;
  }
}
