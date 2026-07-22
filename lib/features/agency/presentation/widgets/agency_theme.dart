import 'package:flutter/material.dart';

ThemeData agencyDarkTheme(BuildContext context) {
  final ThemeData base = Theme.of(context);
  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    colorScheme: ColorScheme.fromSeed(
      seedColor: const Color(0xFF1FA2A6),
      brightness: Brightness.dark,
      secondary: const Color(0xFF7ED6C2),
      tertiary: const Color(0xFFE8B86D),
    ),
    scaffoldBackgroundColor: const Color(0xFF0D1214),
    textTheme: base.textTheme.apply(
      bodyColor: const Color(0xFFE8F3F1),
      displayColor: const Color(0xFFE8F3F1),
    ),
    appBarTheme: const AppBarTheme(
      centerTitle: true,
      backgroundColor: Colors.transparent,
      elevation: 0,
    ),
  );
}

BoxDecoration agencyAtmosphere(ColorScheme colors) => BoxDecoration(
  gradient: LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[
      colors.primary.withValues(alpha: 0.28),
      const Color(0xFF0D1214),
      colors.tertiary.withValues(alpha: 0.12),
    ],
  ),
);
