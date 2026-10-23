import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final NotifierProvider<GlobalLoadingController, bool> globalLoadingProvider =
    NotifierProvider<GlobalLoadingController, bool>(
      GlobalLoadingController.new,
    );

final class GlobalLoadingController extends Notifier<bool> {
  @override
  bool build() => false;

  void show() => state = true;
  void hide() => state = false;
}

final class GlobalLoadingOverlay extends ConsumerWidget {
  const GlobalLoadingOverlay({required this.child, super.key});

  final Widget child;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bool isLoading = ref.watch(globalLoadingProvider);
    return Stack(
      children: <Widget>[
        child,
        if (isLoading)
          Positioned.fill(
            child: ColoredBox(
              color: Colors.black54,
              child: const Center(child: CircularProgressIndicator.adaptive()),
            ),
          ),
      ],
    );
  }
}
