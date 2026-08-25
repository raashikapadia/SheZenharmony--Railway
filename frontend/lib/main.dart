import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import 'features/assessment/presentation/questionnaire_screen.dart';
import 'features/auth/application/auth_provider.dart';
import 'features/auth/presentation/login_screen.dart';
import 'features/home/presentation/home_screen.dart';

void main() {
  runApp(const SheZenApp());
}

class SheZenApp extends StatelessWidget {
  const SheZenApp({super.key});

  @override
  Widget build(BuildContext context) {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: const Color(0xFF6E5A8A),
      brightness: Brightness.light,
    );

    return ChangeNotifierProvider(
      create: (_) => AuthProvider()..restoreSession(),
      child: MaterialApp(
        title: 'SheZen Harmony',
        debugShowCheckedModeBanner: false,
        theme: ThemeData(
          useMaterial3: true,
          colorScheme: colorScheme,
          scaffoldBackgroundColor: const Color(0xFFF8F5FA),
          cardTheme: const CardThemeData(
            elevation: 0,
            margin: EdgeInsets.zero,
          ),
        ),
        home: const _RootScreen(),
      ),
    );
  }
}

class _RootScreen extends StatelessWidget {
  const _RootScreen();

  @override
  Widget build(BuildContext context) {
    final status = context.watch<AuthProvider>().status;

    return switch (status) {
      AuthStatus.unknown => const Scaffold(body: Center(child: CircularProgressIndicator())),
      AuthStatus.signedOut => const LoginScreen(),
      AuthStatus.signedIn => const _HomeGate(),
    };
  }
}

/// Sits under the home screen and, exactly once per sign-in, pushes the
/// mandatory stress questionnaire on top if the backend says it hasn't been
/// completed yet. The push happens once (in initState) rather than being
/// re-derived on every rebuild, so completing it and popping back doesn't
/// immediately re-trigger the gate — [AuthProvider.hasCompletedRequiredAssessment]
/// is only re-read fresh on the next sign-in / app restart via `restoreSession`.
///
/// This gate is student-only: assessments are a student concept (the
/// backend's SubmitAssessmentRequest rejects non-students outright), and an
/// admin account never has a completed assessment, so without this check
/// every admin login would be force-routed into a questionnaire the backend
/// would then refuse to accept — never reaching the admin dashboard.
class _HomeGate extends StatefulWidget {
  const _HomeGate();

  @override
  State<_HomeGate> createState() => _HomeGateState();
}

class _HomeGateState extends State<_HomeGate> {
  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final auth = context.read<AuthProvider>();
      final isStudent = auth.session?.role == 'student';
      if (isStudent && !auth.hasCompletedRequiredAssessment) {
        Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const QuestionnaireScreen(mandatory: true)),
        );
      }
    });
  }

  @override
  Widget build(BuildContext context) => const HomeScreen();
}
