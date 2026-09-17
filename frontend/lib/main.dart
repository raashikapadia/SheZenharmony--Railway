import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/platform/app_exit.dart';
import 'core/theme/app_theme.dart';
import 'features/assessment/presentation/questionnaire_screen.dart';
import 'features/auth/application/auth_provider.dart';
import 'features/auth/presentation/login_screen.dart';
import 'features/auth/presentation/survey_consent.dart';
import 'features/home/presentation/home_screen.dart';
import 'shared/widgets/app_ui.dart';

void main() => runApp(const SheZenApp());

class SheZenApp extends StatelessWidget {
  const SheZenApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => AuthProvider()..restoreSession(),
      child: MaterialApp(
        title: 'SheZen Harmony',
        debugShowCheckedModeBanner: false,
        theme: AppTheme.light,
        builder: (context, child) => AppBackground(child: child!),
        home: const RootScreen(),
      ),
    );
  }
}

/// Decides what the app shows from the session alone, so every launch, deep
/// link or restart goes through the same gates in the same order: sign-in,
/// then survey consent, then the first stress check, then home.
class RootScreen extends StatelessWidget {
  const RootScreen({super.key, this.closeApp = closeApplication});

  /// Handed to the consent gate; injected so tests can observe the exit.
  final Future<void> Function() closeApp;

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    return switch (auth.status) {
      AuthStatus.unknown => const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      ),
      AuthStatus.signedOut => const LoginScreen(),
      // Consent comes before everything else: an account whose recorded
      // consent predates the current wording is asked again here, and cannot
      // reach the questionnaire or home until it says yes.
      AuthStatus.signedIn when !auth.hasCurrentConsent => SurveyConsentScreen(
        closeApp: closeApp,
      ),
      AuthStatus.signedIn =>
        auth.hasCompletedRequiredAssessment
            ? const HomeScreen()
            : const QuestionnaireScreen(mandatory: true),
    };
  }
}
