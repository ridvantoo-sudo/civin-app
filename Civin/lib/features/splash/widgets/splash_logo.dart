import 'package:civin/core/constants/app_sizes.dart';
import 'package:civin/core/constants/assets.dart';
import 'package:civin/core/constants/strings.dart';
import 'package:civin/core/theme/colors.dart';
import 'package:civin/core/utils/responsive_helper.dart';
import 'package:flutter/material.dart';
import 'package:lottie/lottie.dart';

final class SplashLogo extends StatelessWidget {
  const SplashLogo({super.key, required this.fadeAnimation});

  final Animation<double> fadeAnimation;

  @override
  Widget build(BuildContext context) {
    final double size = ResponsiveHelper.value(
      context,
      mobile: 148,
      tablet: 180,
      desktop: 200,
    );

    return FadeTransition(
      opacity: fadeAnimation,
      child: Semantics(
        label: AppStrings.splashLogoSemanticLabel,
        image: true,
        child: Hero(
          tag: 'civin_app_logo',
          child: SizedBox.square(
            dimension: size,
            child: DecoratedBox(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                gradient: LinearGradient(
                  begin: Alignment.topLeft,
                  end: Alignment.bottomRight,
                  colors: <Color>[
                    AppColors.primary.withValues(alpha: 0.18),
                    AppColors.secondary.withValues(alpha: 0.12),
                  ],
                ),
              ),
              child: Padding(
                padding: const EdgeInsets.all(AppSizes.space16),
                child: Lottie.asset(
                  AppAssets.splashLogoAnimation,
                  fit: BoxFit.contain,
                  repeat: true,
                  frameBuilder:
                      (
                        BuildContext context,
                        Widget child,
                        LottieComposition? composition,
                      ) {
                        if (composition == null) {
                          return const Icon(
                            Icons.videocam_rounded,
                            size: 64,
                            color: AppColors.primary,
                          );
                        }
                        return child;
                      },
                  errorBuilder:
                      (BuildContext context, Object error, StackTrace? stack) =>
                          const Icon(
                            Icons.videocam_rounded,
                            size: 64,
                            color: AppColors.primary,
                          ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
