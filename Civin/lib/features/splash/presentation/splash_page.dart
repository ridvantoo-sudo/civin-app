import 'package:civin/core/constants/app_sizes.dart';
import 'package:civin/core/constants/strings.dart';
import 'package:civin/core/router/router.dart';
import 'package:civin/core/theme/colors.dart';
import 'package:civin/core/utils/responsive_helper.dart';
import 'package:civin/features/splash/domain/entities/splash_destination.dart';
import 'package:civin/features/splash/presentation/splash_providers.dart';
import 'package:civin/features/splash/widgets/splash_logo.dart';
import 'package:civin/features/splash/widgets/splash_version_label.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:package_info_plus/package_info_plus.dart';

final class SplashPage extends ConsumerStatefulWidget {
  const SplashPage({super.key});

  @override
  ConsumerState<SplashPage> createState() => _SplashPageState();
}

final class _SplashPageState extends ConsumerState<SplashPage>
    with SingleTickerProviderStateMixin {
  late final AnimationController _fadeController;
  late final Animation<double> _fadeAnimation;
  bool _navigated = false;

  @override
  void initState() {
    super.initState();
    _fadeController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    );
    _fadeAnimation = CurvedAnimation(
      parent: _fadeController,
      curve: Curves.easeInOut,
    );
    _fadeController.forward();
  }

  @override
  void dispose() {
    _fadeController.dispose();
    super.dispose();
  }

  void _navigate(SplashDestination destination) {
    if (!mounted || _navigated) {
      return;
    }
    _navigated = true;
    final String location = switch (destination) {
      SplashDestination.onboarding => AppRoutes.onboarding,
      SplashDestination.login => AppRoutes.login,
      SplashDestination.home => AppRoutes.home,
    };
    context.go(location);
  }

  @override
  Widget build(BuildContext context) {
    ref.listen<AsyncValue<SplashDestination>>(splashBootstrapProvider, (
      AsyncValue<SplashDestination>? previous,
      AsyncValue<SplashDestination> next,
    ) {
      next.whenData(_navigate);
    });

    final AsyncValue<PackageInfo> packageInfo = ref.watch(
      splashPackageInfoProvider,
    );
    final AsyncValue<SplashDestination> bootstrap = ref.watch(
      splashBootstrapProvider,
    );
    final double horizontalPadding = ResponsiveHelper.value(
      context,
      mobile: AppSizes.space24,
      tablet: AppSizes.space40,
      desktop: AppSizes.space48,
    );
    final bool isLandscape =
        MediaQuery.orientationOf(context) == Orientation.landscape;

    return Semantics(
      label: AppStrings.splashSemanticLabel,
      child: Scaffold(
        body: DecoratedBox(
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: <Color>[
                Theme.of(context).colorScheme.surface,
                Theme.of(context).scaffoldBackgroundColor,
                AppColors.primary.withValues(alpha: 0.08),
              ],
            ),
          ),
          child: SafeArea(
            child: Padding(
              padding: EdgeInsets.symmetric(horizontal: horizontalPadding),
              child: AnimatedSwitcher(
                duration: const Duration(milliseconds: 350),
                child: isLandscape
                    ? _LandscapeSplashBody(
                        key: const ValueKey<String>('landscape'),
                        fadeAnimation: _fadeAnimation,
                        packageInfo: packageInfo,
                        bootstrap: bootstrap,
                        onRetry: () => ref.invalidate(splashBootstrapProvider),
                      )
                    : _PortraitSplashBody(
                        key: const ValueKey<String>('portrait'),
                        fadeAnimation: _fadeAnimation,
                        packageInfo: packageInfo,
                        bootstrap: bootstrap,
                        onRetry: () => ref.invalidate(splashBootstrapProvider),
                      ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

final class _PortraitSplashBody extends StatelessWidget {
  const _PortraitSplashBody({
    super.key,
    required this.fadeAnimation,
    required this.packageInfo,
    required this.bootstrap,
    required this.onRetry,
  });

  final Animation<double> fadeAnimation;
  final AsyncValue<PackageInfo> packageInfo;
  final AsyncValue<SplashDestination> bootstrap;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: <Widget>[
        const Spacer(),
        SplashLogo(fadeAnimation: fadeAnimation),
        const SizedBox(height: AppSizes.space24),
        FadeTransition(
          opacity: fadeAnimation,
          child: Text(
            AppStrings.appName,
            style: Theme.of(context).textTheme.headlineLarge,
            textAlign: TextAlign.center,
          ),
        ),
        const Spacer(),
        _SplashFooter(
          packageInfo: packageInfo,
          bootstrap: bootstrap,
          onRetry: onRetry,
        ),
        const SizedBox(height: AppSizes.space24),
      ],
    );
  }
}

final class _LandscapeSplashBody extends StatelessWidget {
  const _LandscapeSplashBody({
    super.key,
    required this.fadeAnimation,
    required this.packageInfo,
    required this.bootstrap,
    required this.onRetry,
  });

  final Animation<double> fadeAnimation;
  final AsyncValue<PackageInfo> packageInfo;
  final AsyncValue<SplashDestination> bootstrap;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Row(
      children: <Widget>[
        Expanded(
          child: Center(child: SplashLogo(fadeAnimation: fadeAnimation)),
        ),
        Expanded(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: <Widget>[
              FadeTransition(
                opacity: fadeAnimation,
                child: Text(
                  AppStrings.appName,
                  style: Theme.of(context).textTheme.headlineLarge,
                  textAlign: TextAlign.center,
                ),
              ),
              const SizedBox(height: AppSizes.space32),
              _SplashFooter(
                packageInfo: packageInfo,
                bootstrap: bootstrap,
                onRetry: onRetry,
              ),
            ],
          ),
        ),
      ],
    );
  }
}

final class _SplashFooter extends StatelessWidget {
  const _SplashFooter({
    required this.packageInfo,
    required this.bootstrap,
    required this.onRetry,
  });

  final AsyncValue<PackageInfo> packageInfo;
  final AsyncValue<SplashDestination> bootstrap;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return AnimatedSwitcher(
      duration: const Duration(milliseconds: 250),
      child: bootstrap.when(
        data: (_) => packageInfo.when(
          data: (PackageInfo info) => SplashVersionLabel(
            key: const ValueKey<String>('version'),
            version: info.version,
          ),
          loading: () => const SizedBox.square(
            key: ValueKey<String>('version-loading'),
            dimension: 20,
          ),
          error: (_, _) => SplashVersionLabel(
            key: const ValueKey<String>('version-fallback'),
            version: '1.0.0',
          ),
        ),
        loading: () => const SizedBox.square(
          key: ValueKey<String>('bootstrap-loading'),
          dimension: 28,
          child: CircularProgressIndicator.adaptive(strokeWidth: 2),
        ),
        error: (Object error, StackTrace _) => Column(
          key: const ValueKey<String>('bootstrap-error'),
          mainAxisSize: MainAxisSize.min,
          children: <Widget>[
            Text(
              AppStrings.unexpectedError,
              style: Theme.of(context).textTheme.bodyMedium,
              textAlign: TextAlign.center,
            ),
            const SizedBox(height: AppSizes.space12),
            TextButton(
              onPressed: onRetry,
              child: const Text(AppStrings.retry),
            ),
          ],
        ),
      ),
    );
  }
}
