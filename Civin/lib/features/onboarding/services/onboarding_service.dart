import 'package:civin/core/services/app_logger.dart';
import 'package:civin/features/onboarding/domain/usecases/complete_onboarding.dart';

final class OnboardingService {
  const OnboardingService({
    required this.completeOnboarding,
    required this.logger,
  });

  final CompleteOnboarding completeOnboarding;
  final AppLogger logger;

  Future<void> finish() async {
    await completeOnboarding();
    logger.info('Onboarding marked as completed');
  }
}
