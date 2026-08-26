// Smoke tests for app startup and the sign-in screen.
//
// The real app bootstrap checks secure storage for a cached session before
// deciding what to show (AuthProvider.restoreSession). Secure storage has no
// platform implementation in the widget-test harness, so its exact async
// timing there is unreliable — the first test only asserts the app builds
// without throwing. LoginScreen's actual content is verified separately, in
// isolation, which sidesteps that async dependency entirely.

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';

import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/presentation/login_screen.dart';
import 'package:shezen_harmony/main.dart';

void main() {
  testWidgets('app builds without throwing on a fresh launch', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(const SheZenApp());
    await tester.pump();
    await tester.pump(const Duration(seconds: 11));

    // Whatever AuthProvider has resolved to by this point (loading spinner,
    // or already past it), the widget tree should be in a valid state with
    // no uncaught exceptions.
    expect(tester.takeException(), isNull);
  });

  testWidgets('LoginScreen renders its sign-in form', (
    WidgetTester tester,
  ) async {
    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => AuthProvider(),
        child: const MaterialApp(home: LoginScreen()),
      ),
    );
    await tester.pump();

    expect(find.text('Welcome to SheZen\nHarmony'), findsOneWidget);
    expect(find.text('Login'), findsOneWidget);
    expect(find.text('Forgot Password?'), findsOneWidget);
    expect(find.text('Create Account'), findsOneWidget);
  });
}
