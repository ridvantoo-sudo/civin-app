import 'package:civin/core/constants/strings.dart';
import 'package:civin/core/router/router.dart';
import 'package:civin/core/theme/theme.dart';
import 'package:civin/core/widgets/app_snackbar.dart';
import 'package:civin/core/widgets/global_loading.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class CivinApp extends ConsumerWidget {
  const CivinApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final GoRouter router = ref.watch(routerProvider);
    final ThemeMode themeMode = ref.watch(themeModeProvider);

    return MaterialApp.router(
      title: AppStrings.appName,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      darkTheme: AppTheme.dark,
      themeMode: themeMode,
      routerConfig: router,
      scaffoldMessengerKey: scaffoldMessengerKey,
      builder: (BuildContext context, Widget? child) =>
          GlobalLoadingOverlay(child: child ?? const SizedBox.shrink()),
    );
  }
}
