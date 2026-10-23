import 'package:civin/core/constants/strings.dart';
import 'package:civin/features/onboarding/domain/entities/onboarding_page_content.dart';
import 'package:flutter/material.dart';

abstract final class OnboardingPagesData {
  static const List<OnboardingPageContent> pages = <OnboardingPageContent>[
    OnboardingPageContent(
      title: AppStrings.onboardingTitleDiscover,
      description: AppStrings.onboardingDescriptionDiscover,
      illustration: Icons.live_tv_rounded,
      semanticLabel: AppStrings.onboardingTitleDiscover,
    ),
    OnboardingPageContent(
      title: AppStrings.onboardingTitleConnect,
      description: AppStrings.onboardingDescriptionConnect,
      illustration: Icons.people_alt_rounded,
      semanticLabel: AppStrings.onboardingTitleConnect,
    ),
    OnboardingPageContent(
      title: AppStrings.onboardingTitleCompete,
      description: AppStrings.onboardingDescriptionCompete,
      illustration: Icons.sports_esports_rounded,
      semanticLabel: AppStrings.onboardingTitleCompete,
    ),
    OnboardingPageContent(
      title: AppStrings.onboardingTitleEarn,
      description: AppStrings.onboardingDescriptionEarn,
      illustration: Icons.account_balance_wallet_rounded,
      semanticLabel: AppStrings.onboardingTitleEarn,
    ),
  ];
}
