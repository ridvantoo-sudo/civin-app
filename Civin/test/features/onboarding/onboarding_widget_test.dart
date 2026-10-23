import 'package:civin/core/constants/strings.dart';
import 'package:civin/core/services/app_logger.dart';
import 'package:civin/features/onboarding/domain/repositories/onboarding_repository.dart';
import 'package:civin/features/onboarding/domain/usecases/complete_onboarding.dart';
import 'package:civin/features/onboarding/presentation/onboarding_controller.dart';
import 'package:civin/features/onboarding/presentation/onboarding_page.dart';
import 'package:civin/features/onboarding/services/onboarding_service.dart';
import 'package:civin/features/splash/domain/entities/splash_destination.dart';
import 'package:civin/features/splash/presentation/splash_page.dart';
import 'package:civin/features/splash/presentation/splash_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:package_info_plus/package_info_plus.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  setUp(() {
    PackageInfo.setMockInitialValues(
      appName: AppStrings.appName,
      packageName: 'com.civin.app',
      version: '1.0.0',
      buildNumber: '1',
      buildSignature: '',
    );
  });

  testWidgets('splash renders branding and version', (WidgetTester tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          splashBootstrapProvider.overrideWith(
            (Ref ref) async => SplashDestination.onboarding,
          ),
        ],
        child: const MaterialApp(home: SplashPage()),
      ),
    );
    await tester.pump();
    expect(find.text(AppStrings.appName), findsOneWidget);

    await tester.pump();
    expect(find.text('v1.0.0'), findsOneWidget);
  });

  testWidgets('onboarding shows pages, skip, next, and finish', (
    WidgetTester tester,
  ) async {
    final _FakeOnboardingRepository repository = _FakeOnboardingRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          onboardingRepositoryProvider.overrideWith((Ref ref) => repository),
          onboardingServiceProvider.overrideWith(
            (Ref ref) => OnboardingService(
              completeOnboarding: CompleteOnboarding(repository),
              logger: AppLogger(),
            ),
          ),
        ],
        child: const MaterialApp(home: OnboardingPage()),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text(AppStrings.onboardingTitleDiscover), findsOneWidget);
    expect(find.text(AppStrings.skip), findsOneWidget);
    expect(find.text(AppStrings.next), findsOneWidget);

    await tester.tap(find.text(AppStrings.next));
    await tester.pumpAndSettle();
    expect(find.text(AppStrings.onboardingTitleConnect), findsOneWidget);

    await tester.tap(find.text(AppStrings.next));
    await tester.pumpAndSettle();
    await tester.tap(find.text(AppStrings.next));
    await tester.pumpAndSettle();

    expect(find.text(AppStrings.onboardingTitleEarn), findsOneWidget);
    expect(find.text(AppStrings.finish), findsOneWidget);
    expect(find.text(AppStrings.skip), findsNothing);
  });
}

final class _FakeOnboardingRepository implements OnboardingRepository {
  bool completed = false;

  @override
  Future<bool> isCompleted() async => completed;

  @override
  Future<void> complete() async {
    completed = true;
  }
}
