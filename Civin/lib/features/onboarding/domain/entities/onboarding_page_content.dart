import 'package:flutter/material.dart';

final class OnboardingPageContent {
  const OnboardingPageContent({
    required this.title,
    required this.description,
    required this.illustration,
    required this.semanticLabel,
  });

  final String title;
  final String description;
  final IconData illustration;
  final String semanticLabel;
}
