import 'package:flutter/material.dart';

final class AppLoadingWidget extends StatelessWidget {
  const AppLoadingWidget({super.key, this.message});

  final String? message;

  @override
  Widget build(BuildContext context) => Center(
    child: Semantics(
      liveRegion: true,
      label: message ?? 'Loading',
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          const CircularProgressIndicator.adaptive(),
          if (message case final String text) ...<Widget>[
            const SizedBox(height: 16),
            Text(text, textAlign: TextAlign.center),
          ],
        ],
      ),
    ),
  );
}
