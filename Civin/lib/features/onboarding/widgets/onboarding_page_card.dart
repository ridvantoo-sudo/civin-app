import 'package:civin/core/constants/app_sizes.dart';
import 'package:civin/core/theme/colors.dart';
import 'package:civin/core/utils/responsive_helper.dart';
import 'package:civin/features/onboarding/domain/entities/onboarding_page_content.dart';
import 'package:flutter/material.dart';

final class OnboardingIllustration extends StatelessWidget {
  const OnboardingIllustration({super.key, required this.content});

  final OnboardingPageContent content;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    final double size = ResponsiveHelper.value(
      context,
      mobile: 220,
      tablet: 280,
      desktop: 320,
    );

    return Semantics(
      label: content.semanticLabel,
      image: true,
      child: Hero(
        tag: 'onboarding_illustration_${content.title}',
        child: SizedBox.square(
          dimension: size,
          child: DecoratedBox(
            decoration: BoxDecoration(
              shape: BoxShape.circle,
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: <Color>[
                  colors.primary.withValues(alpha: 0.18),
                  AppColors.secondary.withValues(alpha: 0.14),
                  colors.surface,
                ],
              ),
              boxShadow: <BoxShadow>[
                BoxShadow(
                  color: colors.primary.withValues(alpha: 0.12),
                  blurRadius: 28,
                  offset: const Offset(0, 12),
                ),
              ],
            ),
            child: Icon(
              content.illustration,
              size: size * 0.38,
              color: colors.primary,
            ),
          ),
        ),
      ),
    );
  }
}

final class OnboardingPageCard extends StatelessWidget {
  const OnboardingPageCard({super.key, required this.content});

  final OnboardingPageContent content;

  @override
  Widget build(BuildContext context) {
    final TextTheme textTheme = Theme.of(context).textTheme;
    final bool isLandscape =
        MediaQuery.orientationOf(context) == Orientation.landscape;
    final double horizontal = ResponsiveHelper.value(
      context,
      mobile: AppSizes.space24,
      tablet: AppSizes.space40,
      desktop: AppSizes.space48,
    );

    final Widget illustration = OnboardingIllustration(content: content);
    final Widget copy = Column(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        Text(
          content.title,
          style: textTheme.headlineMedium,
          textAlign: TextAlign.center,
        ),
        const SizedBox(height: AppSizes.space12),
        Text(
          content.description,
          style: textTheme.bodyLarge?.copyWith(color: AppColors.mutedText),
          textAlign: TextAlign.center,
        ),
      ],
    );

    if (isLandscape) {
      return Padding(
        padding: EdgeInsets.symmetric(horizontal: horizontal),
        child: Row(
          children: <Widget>[
            Expanded(child: Center(child: illustration)),
            Expanded(child: copy),
          ],
        ),
      );
    }

    return Padding(
      padding: EdgeInsets.symmetric(horizontal: horizontal),
      child: Column(
        children: <Widget>[
          const Spacer(),
          illustration,
          const SizedBox(height: AppSizes.space40),
          copy,
          const Spacer(flex: 2),
        ],
      ),
    );
  }
}
