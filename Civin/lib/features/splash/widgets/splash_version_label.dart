import 'package:civin/core/constants/strings.dart';
import 'package:civin/core/theme/colors.dart';
import 'package:flutter/material.dart';

final class SplashVersionLabel extends StatelessWidget {
  const SplashVersionLabel({super.key, required this.version});

  final String version;

  @override
  Widget build(BuildContext context) {
    final TextTheme textTheme = Theme.of(context).textTheme;
    return Semantics(
      label: '${AppStrings.versionPrefix}$version',
      child: Text(
        '${AppStrings.versionPrefix}$version',
        style: textTheme.bodyMedium?.copyWith(color: AppColors.mutedText),
        textAlign: TextAlign.center,
      ),
    );
  }
}
