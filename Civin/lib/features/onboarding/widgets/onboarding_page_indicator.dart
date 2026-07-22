import 'package:civin/core/constants/app_sizes.dart';
import 'package:civin/core/constants/strings.dart';
import 'package:flutter/material.dart';

final class OnboardingPageIndicator extends StatelessWidget {
  const OnboardingPageIndicator({
    super.key,
    required this.length,
    required this.index,
  });

  final int length;
  final int index;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;

    return Semantics(
      label: '${AppStrings.onboardingPageIndicatorLabel}, page ${index + 1} of $length',
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: List<Widget>.generate(length, (int i) {
          final bool selected = i == index;
          return AnimatedContainer(
            duration: const Duration(milliseconds: 250),
            curve: Curves.easeOutCubic,
            margin: const EdgeInsets.symmetric(horizontal: AppSizes.space4),
            width: selected ? 28 : 8,
            height: 8,
            decoration: BoxDecoration(
              color: selected
                  ? colors.primary
                  : colors.onSurface.withValues(alpha: 0.18),
              borderRadius: BorderRadius.circular(AppSizes.radiusLarge),
            ),
          );
        }),
      ),
    );
  }
}
