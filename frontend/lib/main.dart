import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'core/theme/app_theme.dart';
import 'features/assessment/presentation/questionnaire_screen.dart';
import 'features/auth/application/auth_provider.dart';
import 'features/auth/presentation/login_screen.dart';
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
        home: const _RootScreen(),
      ),
    );
  }
}

class _RootScreen extends StatelessWidget {
  const _RootScreen();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    return switch (auth.status) {
      AuthStatus.unknown => const Scaffold(
        body: Center(child: CircularProgressIndicator()),
      ),
      AuthStatus.signedOut => const LoginScreen(),
      AuthStatus.signedIn =>
        auth.hasCompletedRequiredAssessment
            ? const HomeScreen()
            : const QuestionnaireScreen(mandatory: true),
    };
  }
}
