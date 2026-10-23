import 'package:flutter/material.dart';

ThemeData vipDarkTheme(BuildContext context) {
  final ThemeData base = Theme.of(context);
  return ThemeData(
    useMaterial3: true,
    brightness: Brightness.dark,
    colorScheme: ColorScheme.fromSeed(
      seedColor: const Color(0xFFD4A017),
      brightness: Brightness.dark,
      secondary: const Color(0xFFFFC857),
      tertiary: const Color(0xFF8B5CF6),
    ),
    scaffoldBackgroundColor: const Color(0xFF101014),
    textTheme: base.textTheme.apply(
      bodyColor: const Color(0xFFF8F4EA),
      displayColor: const Color(0xFFF8F4EA),
    ),
    appBarTheme: const AppBarTheme(
      centerTitle: true,
      backgroundColor: Colors.transparent,
      elevation: 0,
    ),
  );
}

BoxDecoration vipAtmosphere(ColorScheme colors) => BoxDecoration(
  gradient: LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: <Color>[
      colors.primary.withValues(alpha: 0.30),
      const Color(0xFF101014),
      colors.tertiary.withValues(alpha: 0.16),
    ],
  ),
);
