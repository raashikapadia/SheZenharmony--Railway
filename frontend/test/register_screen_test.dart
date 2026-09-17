import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';
import 'package:shezen_harmony/features/auth/data/auth_challenge.dart';
import 'package:shezen_harmony/features/auth/presentation/register_screen.dart';
import 'package:shezen_harmony/features/auth/presentation/survey_consent.dart';

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
      find.widgetWithText(TextFormField, 'USP student email'),
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

  testWidgets('registration requires a USP student email', (tester) async {
    await tester.pumpWidget(screen());
    await tester.enterText(
      find.widgetWithText(TextFormField, 'USP student email'),
      'student@gmail.com',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Create Password'),
      'safe-password',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Confirm Password'),
      'safe-password',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Continue'));
    await tester.pump();

    expect(find.text('Use your @student.usp.ac.fj email.'), findsOneWidget);
    expect(find.text('USP student email ready'), findsNothing);
  });

  testWidgets('malformed email receives friendly validation', (tester) async {
    await tester.pumpWidget(screen());
    await tester.enterText(
      find.widgetWithText(TextFormField, 'USP student email'),
      'not-an-email',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Continue'));
    await tester.pump();

    expect(find.text('Enter a valid email address.'), findsOneWidget);
    expect(find.text('Create Account'), findsOneWidget);
    expect(find.text('USP student email ready'), findsNothing);
  });

  testWidgets('registration uses account demographics and privacy steps', (
    tester,
  ) async {
    await tester.pumpWidget(screen());
    await _advanceToPrivacy(tester);

    expect(find.text('Privacy & Consent'), findsOneWidget);
    expect(find.text('Purpose of the survey'), findsOneWidget);
    expect(
      find.textContaining('female university students'),
      findsOneWidget,
    );
    expect(find.text('Confidentiality'), findsOneWidget);
    expect(find.text('Participation is voluntary.'), findsOneWidget);
    expect(
      find.textContaining('This survey is not a medical diagnosis.'),
      findsOneWidget,
    );
    expect(
      find.textContaining(SurveyConsentContent.consentQuestion),
      findsOneWidget,
    );
    expect(find.byKey(const Key('consent-yes')), findsOneWidget);
    expect(find.byKey(const Key('consent-no')), findsOneWidget);
    // The old checkbox-and-continue consent is gone: the only way forward
    // is the explicit "Yes".
    expect(find.byType(CheckboxListTile), findsNothing);
    expect(find.widgetWithText(FilledButton, 'Continue'), findsNothing);
    expect(find.text('Demographics'), findsOneWidget);
    expect(find.text('Select date of birth'), findsNothing);
  });

  testWidgets('declining consent during registration closes the app', (
    tester,
  ) async {
    var closed = 0;
    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => AuthProvider(),
        child: MaterialApp(
          home: RegisterScreen(closeApp: () async => closed++),
        ),
      ),
    );
    await _advanceToPrivacy(tester);

    final no = find.byKey(const Key('consent-no'));
    await tester.ensureVisible(no);
    await tester.tap(no);
    await tester.pumpAndSettle();
    expect(find.text('Leave SheZen Harmony?'), findsOneWidget);

    // Changing their mind keeps them on the consent step, data intact, and
    // still without a way into demographics other than "Yes".
    await tester.tap(find.text('Go back'));
    await tester.pumpAndSettle();
    expect(closed, 0);
    expect(find.text('Privacy & Consent'), findsOneWidget);
    expect(find.text('Select date of birth'), findsNothing);

    await tester.tap(no);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('consent-decline-confirm')));
    // The step shows a busy state while the app closes, so settle by hand.
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 300));
    expect(closed, 1);
    expect(find.text('Select date of birth'), findsNothing);
  });

  testWidgets('going back from consent keeps entered account data', (
    tester,
  ) async {
    await tester.pumpWidget(screen());
    await _advanceToPrivacy(tester);

    await tester.tap(find.byIcon(Icons.arrow_back_rounded));
    await tester.pump();
    await tester.tap(find.byIcon(Icons.arrow_back_rounded));
    await tester.pump();
    expect(
      tester
          .widget<TextFormField>(
            find.widgetWithText(TextFormField, 'USP student email'),
          )
          .controller
          ?.text,
      'student@student.usp.ac.fj',
    );
  });

  testWidgets('successful registration shows SheZen ID before questionnaire', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 740);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final api = _RegistrationApiService();
    final provider = AuthProvider(
      apiService: api,
      storage: _MemoryStorage(),
    );
    await tester.pumpWidget(screen(provider: provider));
    await _advanceToPrivacy(tester);
    final yes = find.byKey(const Key('consent-yes'));
    await tester.ensureVisible(yes);
    await tester.tap(yes);
    await tester.pump();
    expect(find.text('Preferred language'), findsNothing);
    await _completeDemographics(tester);
    final create = find.widgetWithText(FilledButton, 'Generate my SheZen ID');
    await tester.ensureVisible(create);
    await tester.tap(create);
    await tester.pumpAndSettle();

    expect(find.text('Verify your USP email'), findsOneWidget);
    await tester.enterText(find.byKey(const Key('otp-code-field')), '123456');
    await tester.tap(find.byKey(const Key('verify-otp-button')));
    await tester.pumpAndSettle();

    expect(find.text('Your SheZen profile is ready'), findsOneWidget);
    expect(find.text('SZ7K42P'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Continue'), findsOneWidget);
    expect(provider.hasCompletedRequiredAssessment, isFalse);
    // "Yes" is what the backend records as the consent row.
    expect(api.sentConsent, isTrue);
    // Demographics reach the backend exactly as chosen; a fixed year of
    // study carries no "Other" specification.
    expect(api.sentDemographics?['country'], 'Fiji');
    expect(api.sentDemographics?['year_of_study'], 'Year 3');
    expect(api.sentDemographics?['year_of_study_detail'], isNull);
    expect(api.sentDemographics?['date_of_birth'], isNotNull);
  });

  testWidgets('"Other" year of study must be specified before registering', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(360, 740);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final api = _RegistrationApiService();
    final provider = AuthProvider(
      apiService: api,
      storage: _MemoryStorage(),
    );
    await tester.pumpWidget(screen(provider: provider));
    await _advanceToPrivacy(tester);
    final yes = find.byKey(const Key('consent-yes'));
    await tester.ensureVisible(yes);
    await tester.tap(yes);
    await tester.pump();
    await _completeDemographics(tester);

    final other = find.widgetWithText(ChoiceChip, 'Other').first;
    await tester.ensureVisible(other);
    await tester.tap(other);
    await tester.pumpAndSettle();
    final detail = find.byKey(const Key('year-of-study-detail'));
    expect(detail, findsOneWidget);

    final create = find.widgetWithText(FilledButton, 'Generate my SheZen ID');
    await tester.ensureVisible(create);
    await tester.tap(create);
    await tester.pumpAndSettle();
    expect(find.text('Please specify your year of study.'), findsOneWidget);
    expect(api.sentDemographics, isNull);

    await tester.ensureVisible(detail);
    await tester.enterText(detail, 'Foundation programme');
    await tester.ensureVisible(create);
    await tester.tap(create);
    await tester.pumpAndSettle();
    expect(api.sentDemographics?['year_of_study'], 'Other');
    expect(
      api.sentDemographics?['year_of_study_detail'],
      'Foundation programme',
    );
  });
}

Future<void> _advanceToPrivacy(WidgetTester tester) async {
  await tester.enterText(
    find.widgetWithText(TextFormField, 'USP student email'),
    'student@student.usp.ac.fj',
  );
  await tester.enterText(
    find.widgetWithText(TextFormField, 'Create Password'),
    'safe-password',
  );
  await tester.enterText(
    find.widgetWithText(TextFormField, 'Confirm Password'),
    'safe-password',
  );
  await tester.tap(find.widgetWithText(FilledButton, 'Continue'));
  await tester.pump();
  await tester.tap(find.widgetWithText(FilledButton, 'Continue'));
  await tester.pump();
}

Future<void> _completeDemographics(WidgetTester tester) async {
  final dobField = find.text('Select date of birth');
  await tester.ensureVisible(dobField);
  await tester.tap(dobField);
  await tester.pumpAndSettle();
  await tester.tap(find.text('OK'));
  await tester.pumpAndSettle();

  for (final option in const ['Female']) {
    final chip = find.widgetWithText(ChoiceChip, option);
    await tester.ensureVisible(chip);
    await tester.tap(chip);
    await tester.pump();
  }

  // The form only shows the chosen country; the full list lives in a
  // searchable sheet, so search it down before selecting.
  final countryField = find.byKey(const Key('country-field'));
  await tester.ensureVisible(countryField);
  await tester.tap(countryField);
  await tester.pumpAndSettle();
  await tester.enterText(find.byKey(const Key('country-search')), 'Fiji');
  await tester.pumpAndSettle();
  await tester.tap(find.widgetWithText(ListTile, 'Fiji'));
  await tester.pumpAndSettle();

  for (final option in const [
    'Year 3',
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
  bool? sentConsent;
  Map<String, dynamic>? sentDemographics;

  @override
  Future<AuthChallenge> register({
    required String email,
    required String password,
    required String passwordConfirmation,
    required Map<String, dynamic> demographics,
    required bool privacyConsent,
    String deviceName = 'SheZen mobile app',
  }) async {
    sentConsent = privacyConsent;
    sentDemographics = demographics;
    return const AuthChallenge(
      id: '11111111-1111-4111-8111-111111111111',
      purpose: 'registration',
      maskedEmail: 's*******@student.usp.ac.fj',
      expiresInSeconds: 600,
      resendAfterSeconds: 60,
    );
  }

  @override
  Future<AuthSession> verifyOtp({
    required String challengeId,
    required String code,
  }) async => const AuthSession(
    token: 'new-token',
    role: 'student',
    shezenId: 'SZ7K42P',
    hasCompletedRequiredAssessment: false,
  );
}

class _MemoryStorage extends SecureTokenStorage {
  @override
  Future<void> save({required String token, required String role}) async {}
}
