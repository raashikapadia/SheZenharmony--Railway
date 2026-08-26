import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';
import 'package:shezen_harmony/features/auth/presentation/register_screen.dart';

void main() {
  Widget screen({AuthProvider? provider}) => provider == null
      ? ChangeNotifierProvider(
          create: (_) => AuthProvider(),
          child: const MaterialApp(home: RegisterScreen()),
        )
      : ChangeNotifierProvider.value(
          value: provider,
          child: const MaterialApp(home: RegisterScreen()),
        );

  testWidgets('registration starts with account details and no real identity', (
    tester,
  ) async {
    await tester.pumpWidget(screen());

    expect(find.text('Create Account'), findsOneWidget);
    expect(
      find.widgetWithText(TextFormField, 'USP Student Email'),
      findsOneWidget,
    );
    expect(
      find.widgetWithText(TextFormField, 'Create Password'),
      findsOneWidget,
    );
    expect(
      find.widgetWithText(TextFormField, 'Confirm Password'),
      findsOneWidget,
    );
    expect(find.text('First name'), findsNothing);
    expect(find.text('Surname'), findsNothing);
    expect(find.text('Student ID'), findsNothing);
    expect(find.text('Country'), findsNothing);
  });

  testWidgets('non-student USP domain receives friendly validation', (
    tester,
  ) async {
    await tester.pumpWidget(screen());
    await tester.enterText(
      find.widgetWithText(TextFormField, 'USP Student Email'),
      'person@usp.ac.fj',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Verify Account'));
    await tester.pump();

    expect(find.text('Use your @student.usp.ac.fj email.'), findsOneWidget);
    expect(find.text('Create Account'), findsOneWidget);
    expect(find.text('Student login ready'), findsNothing);
  });

  testWidgets('registration uses account demographics and privacy steps', (
    tester,
  ) async {
    await tester.pumpWidget(screen());
    await _advanceToPrivacy(tester);

    expect(find.text('Privacy & Consent'), findsOneWidget);
    expect(find.byType(CheckboxListTile), findsOneWidget);
    expect(
      tester.widget<CheckboxListTile>(find.byType(CheckboxListTile)).value,
      isFalse,
    );
    expect(
      tester
          .widget<FilledButton>(find.widgetWithText(FilledButton, 'Continue'))
          .onPressed,
      isNull,
    );
  });

  testWidgets('privacy notice opens without losing entered account data', (
    tester,
  ) async {
    await tester.pumpWidget(screen());
    await _advanceToPrivacy(tester);

    final privacyNotice = find.text('Read full Privacy & Data Use');
    await tester.ensureVisible(privacyNotice);
    await tester.tap(privacyNotice);
    await tester.pumpAndSettle();
    expect(find.text('Information SheZen collects'), findsOneWidget);
    expect(
      find.textContaining('pseudonymised, not completely anonymous'),
      findsOneWidget,
    );
    Navigator.of(
      tester.element(find.text('Information SheZen collects')),
    ).pop();
    await tester.pumpAndSettle();

    await tester.tap(find.byIcon(Icons.arrow_back_rounded));
    await tester.pump();
    await tester.tap(find.byIcon(Icons.arrow_back_rounded));
    await tester.pump();
    expect(
      tester
          .widget<TextFormField>(
            find.widgetWithText(TextFormField, 'USP Student Email'),
          )
          .controller
          ?.text,
      's12345678@student.usp.ac.fj',
    );
  });

  testWidgets('successful registration shows SheZen ID before questionnaire', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 740);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final provider = AuthProvider(
      apiService: _RegistrationApiService(),
      storage: _MemoryStorage(),
    );
    await tester.pumpWidget(screen(provider: provider));
    await _advanceToPrivacy(tester);
    final consent = find.byType(CheckboxListTile);
    await tester.ensureVisible(consent);
    await tester.tap(consent);
    await tester.pump();
    final privacyContinue = find.widgetWithText(FilledButton, 'Continue');
    await tester.ensureVisible(privacyContinue);
    await tester.tap(privacyContinue);
    await tester.pump();
    expect(find.text('Preferred language'), findsNothing);
    await _completeDemographics(tester);
    final create = find.widgetWithText(FilledButton, 'Generate my SheZen ID');
    await tester.ensureVisible(create);
    await tester.tap(create);
    await tester.pumpAndSettle();

    expect(find.text('Your SheZen profile is ready'), findsOneWidget);
    expect(find.text('SZ-TESTIDENTITY'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Continue'), findsOneWidget);
    expect(provider.hasCompletedRequiredAssessment, isFalse);
  });
}

Future<void> _advanceToPrivacy(WidgetTester tester) async {
  await tester.enterText(
    find.widgetWithText(TextFormField, 'USP Student Email'),
    's12345678@student.usp.ac.fj',
  );
  await tester.enterText(
    find.widgetWithText(TextFormField, 'Create Password'),
    'safe-password',
  );
  await tester.enterText(
    find.widgetWithText(TextFormField, 'Confirm Password'),
    'safe-password',
  );
  await tester.tap(find.widgetWithText(FilledButton, 'Verify Account'));
  await tester.pump();
  await tester.tap(find.widgetWithText(FilledButton, 'Continue'));
  await tester.pump();
}

Future<void> _completeDemographics(WidgetTester tester) async {
  for (final option in const [
    'Female',
    'Fiji',
    'Not employed',
    'Single',
    'No',
    'With family',
  ]) {
    final chip = find.widgetWithText(ChoiceChip, option);
    await tester.ensureVisible(chip);
    await tester.tap(chip);
    await tester.pump();
  }
}

class _RegistrationApiService extends ApiService {
  @override
  Future<AuthSession> register({
    required String email,
    required String password,
    required String passwordConfirmation,
    required Map<String, dynamic> demographics,
    required bool privacyConsent,
    String deviceName = 'SheZen mobile app',
  }) async => const AuthSession(
    token: 'new-token',
    role: 'student',
    shezenId: 'SZ-TESTIDENTITY',
    hasCompletedRequiredAssessment: false,
  );
}

class _MemoryStorage extends SecureTokenStorage {
  @override
  Future<void> save({required String token, required String role}) async {}
}
