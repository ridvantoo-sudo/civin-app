import 'package:civin/app.dart';
import 'package:civin/core/constants/strings.dart';
import 'package:civin/features/splash/domain/entities/splash_destination.dart';
import 'package:civin/features/splash/presentation/splash_providers.dart';
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

  testWidgets('renders the Civin splash shell', (WidgetTester tester) async {
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          splashBootstrapProvider.overrideWith(
            (Ref ref) async => SplashDestination.onboarding,
          ),
        ],
        child: const CivinApp(),
      ),
    );
    await tester.pump();

    expect(find.text(AppStrings.appName), findsWidgets);
  });
}
