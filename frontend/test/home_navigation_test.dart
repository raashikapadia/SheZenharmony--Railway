import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/data/auth_challenge.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';
import 'package:shezen_harmony/features/home/presentation/home_screen.dart';

/// Guards the agreed information architecture: four primary destinations, the
/// three content areas reachable from Home, and ChatBuddy floating rather than
/// occupying a tab.
void main() {
  testWidgets('bottom navigation carries exactly the four destinations', (
    tester,
  ) async {
    await _pumpHome(tester);

    final bar = tester.widget<NavigationBar>(find.byType(NavigationBar));
    expect(
      bar.destinations
          .cast<NavigationDestination>()
          .map((destination) => destination.label)
          .toList(),
      ['Home', 'Stress level', 'Resource', 'Profile'],
    );
  });

  testWidgets('ChatBuddy floats instead of taking a fifth tab', (tester) async {
    await _pumpHome(tester);

    final scaffold = tester.widget<Scaffold>(find.byType(Scaffold).first);
    expect(scaffold.floatingActionButton, isNotNull);
    expect(find.text('ChatBuddy'), findsOneWidget);

    final bar = tester.widget<NavigationBar>(find.byType(NavigationBar));
    expect(
      bar.destinations
          .cast<NavigationDestination>()
          .map((destination) => destination.label),
      isNot(contains('ChatBuddy')),
    );
  });

  testWidgets('Home exposes the three primary content areas', (tester) async {
    await _pumpHome(tester);

    // The cards sit below the fold, and the dashboard list builds lazily.
    await tester.scrollUntilVisible(
      find.text('Positive engagement'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pump();

    // Personal guidance keeps its own entry point rather than living inside
    // one of the other two.
    expect(find.text('Personal guidance'), findsOneWidget);
    expect(find.text('Wellbeing activities'), findsOneWidget);
    expect(find.text('Positive engagement'), findsOneWidget);
  });

  testWidgets('the three pathway cards lay out side by side when wide', (
    tester,
  ) async {
    await _pumpHome(tester, size: const Size(1280, 900));
    await tester.scrollUntilVisible(
      find.text('Positive engagement'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pump();

    final guidance = tester.getRect(find.text('Personal guidance'));
    final wellbeing = tester.getRect(find.text('Wellbeing activities'));
    final positive = tester.getRect(find.text('Positive engagement'));

    // One row, in the agreed order.
    expect(guidance.top, wellbeing.top);
    expect(wellbeing.top, positive.top);
    expect(guidance.left, lessThan(wellbeing.left));
    expect(wellbeing.left, lessThan(positive.left));
    expect(tester.takeException(), isNull);
  });

  testWidgets('the pathway cards stack on a narrow phone', (tester) async {
    await _pumpHome(tester, size: const Size(320, 900));
    await tester.scrollUntilVisible(
      find.text('Positive engagement'),
      300,
      scrollable: find.byType(Scrollable).first,
    );
    await tester.pump();

    expect(
      tester.getRect(find.text('Personal guidance')).top,
      lessThan(tester.getRect(find.text('Positive engagement')).top),
    );
    expect(tester.takeException(), isNull);
  });
}

Future<void> _pumpHome(
  WidgetTester tester, {
  Size size = const Size(420, 900),
}) async {
  tester.view.physicalSize = size;
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);

  final provider = AuthProvider(
    apiService: _SignedInApiService(),
    storage: _MemoryStorage(),
  );
  await provider.register(
    email: 'student@student.usp.ac.fj',
    password: 'safe-password',
    passwordConfirmation: 'safe-password',
    demographics: const {},
    privacyConsent: true,
  );
  await provider.verifyOtp('123456');

  await tester.pumpWidget(
    ChangeNotifierProvider.value(
      value: provider,
      child: const MaterialApp(home: HomeScreen()),
    ),
  );
  await tester.pump();
}

class _SignedInApiService extends ApiService {
  @override
  Future<AuthChallenge> register({
    required String email,
    required String password,
    required String passwordConfirmation,
    required Map<String, dynamic> demographics,
    required bool privacyConsent,
    String deviceName = 'SheZen mobile app',
  }) async => const AuthChallenge(
    id: '11111111-1111-4111-8111-111111111111',
    purpose: 'registration',
    maskedEmail: 's*****@student.usp.ac.fj',
    expiresInSeconds: 600,
    resendAfterSeconds: 60,
  );

  @override
  Future<AuthSession> verifyOtp({
    required String challengeId,
    required String code,
  }) async => const AuthSession(
    token: 'token',
    role: 'student',
    shezenId: 'SZ-TESTIDENTITY',
    hasCompletedRequiredAssessment: true,
  );
}

class _MemoryStorage extends SecureTokenStorage {
  @override
  Future<void> save({required String token, required String role}) async {}
}
