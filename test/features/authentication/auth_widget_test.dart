import 'package:civin/core/network/network_checker.dart';
import 'package:civin/features/authentication/presentation/login_page.dart';
import 'package:civin/features/authentication/presentation/register_page.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('login validates empty credentials', (WidgetTester tester) async {
    await tester.pumpWidget(_testApp(const LoginPage()));

    await tester.tap(find.text('Sign in').first);
    await tester.pump();

    expect(find.text('Email is required.'), findsOneWidget);
    expect(find.text('Password is required.'), findsOneWidget);
  });

  testWidgets('register displays password strength and confirmation errors', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(_testApp(const RegisterPage()));

    await tester.enterText(
      find.byKey(const Key('register_password')),
      'Strong1!',
    );
    await tester.pump();
    expect(find.text('Strong password'), findsOneWidget);

    await tester.enterText(
      find.byKey(const Key('register_email')),
      'person@example.com',
    );
    await tester.enterText(
      find.byKey(const Key('register_confirm_password')),
      'Different1!',
    );
    await tester.tap(find.text('Create account'));
    await tester.pump();

    expect(find.text('Passwords do not match.'), findsOneWidget);
  });
}

Widget _testApp(Widget child) => ProviderScope(
  overrides: [
    connectivityProvider.overrideWith((Ref ref) => Stream<bool>.value(true)),
  ],
  child: MaterialApp(home: child),
);
