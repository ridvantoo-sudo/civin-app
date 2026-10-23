import 'package:flutter/material.dart';

abstract final class AppTextStyles {
  static TextTheme textTheme(Color color) => TextTheme(
    displayLarge: TextStyle(
      color: color,
      fontSize: 57,
      fontWeight: FontWeight.w700,
      letterSpacing: -0.25,
    ),
    displayMedium: TextStyle(
      color: color,
      fontSize: 45,
      fontWeight: FontWeight.w700,
    ),
    headlineLarge: TextStyle(
      color: color,
      fontSize: 32,
      fontWeight: FontWeight.w700,
    ),
    headlineMedium: TextStyle(
      color: color,
      fontSize: 28,
      fontWeight: FontWeight.w600,
    ),
    titleLarge: TextStyle(
      color: color,
      fontSize: 22,
      fontWeight: FontWeight.w600,
    ),
    titleMedium: TextStyle(
      color: color,
      fontSize: 16,
      fontWeight: FontWeight.w600,
      letterSpacing: 0.15,
    ),
    bodyLarge: TextStyle(color: color, fontSize: 16, height: 1.5),
    bodyMedium: TextStyle(color: color, fontSize: 14, height: 1.5),
    labelLarge: TextStyle(
      color: color,
      fontSize: 14,
      fontWeight: FontWeight.w600,
      letterSpacing: 0.1,
    ),
  );
}
