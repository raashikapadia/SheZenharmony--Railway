import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';
import 'package:shezen_harmony/features/auth/presentation/login_screen.dart';
import 'package:shezen_harmony/features/auth/presentation/survey_consent.dart';
import 'package:shezen_harmony/features/home/presentation/home_screen.dart';
import 'package:shezen_harmony/main.dart';

/// The survey consent gate for accounts that already exist. Registration's
/// consent step is covered in register_screen_test; this is the same content
/// shown by the root screen when the backend reports no consent to the
/// current wording — e.g. an account that registered under older wording.
void main() {
  testWidgets(
    'a signed-in account without current consent lands on the consent screen',
    (tester) async {
      final api = _ConsentApiService(hasCurrentConsent: false);
      await _pumpRoot(tester, api);

      expect(find.byType(SurveyConsentScreen), findsOneWidget);
      expect(find.byType(HomeScreen), findsNothing);
      expect(find.text('Purpose of the survey'), findsOneWidget);
      expect(
        find.textContaining(SurveyConsentContent.consentQuestion),
        findsOneWidget,
      );
      expect(find.byKey(const Key('consent-yes')), findsOneWidget);
      expect(find.byKey(const Key('consent-no')), findsOneWidget);
      // No back affordance: there is nothing behind this screen to reach.
      expect(find.byType(BackButton), findsNothing);
    },
  );

  testWidgets('an account that already consented is not asked again', (
    tester,
  ) async {
    final api = _ConsentApiService(hasCurrentConsent: true);
    await _pumpRoot(tester, api);

    expect(find.byType(SurveyConsentScreen), findsNothing);
    expect(find.byType(HomeScreen), findsOneWidget);
    expect(api.consentCalls, 0);
  });

  testWidgets('Yes records consent on the backend and lets the account in', (
    tester,
  ) async {
    final api = _ConsentApiService(hasCurrentConsent: false);
    await _pumpRoot(tester, api);

    final yes = find.byKey(const Key('consent-yes'));
    await tester.ensureVisible(yes);
    await tester.tap(yes);
    // Home has its own ambient animation, so settle by hand.
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));

    expect(api.consentCalls, 1);
    expect(find.byType(SurveyConsentScreen), findsNothing);
    expect(find.byType(HomeScreen), findsOneWidget);
    expect(api.deleteCalls, 0);
  });

  testWidgets('No deletes the account, signs out, and closes the app', (
    tester,
  ) async {
    final api = _ConsentApiService(hasCurrentConsent: false);
    final storage = _MemoryStorage();
    var closed = 0;
    final provider = await _pumpRoot(
      tester,
      api,
      storage: storage,
      closeApp: () async => closed++,
    );

    final no = find.byKey(const Key('consent-no'));
    await tester.ensureVisible(no);
    await tester.tap(no);
    await tester.pumpAndSettle();
    expect(find.text('Withdraw consent and leave?'), findsOneWidget);

    // Backing out of the dialog changes nothing.
    await tester.tap(find.text('Go back'));
    await tester.pumpAndSettle();
    expect(api.deleteCalls, 0);
    expect(closed, 0);
    expect(find.byType(SurveyConsentScreen), findsOneWidget);

    await tester.tap(no);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('consent-decline-confirm')));
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));

    expect(api.deleteCalls, 1);
    expect(api.consentCalls, 0);
    expect(closed, 1);
    expect(provider.status, AuthStatus.signedOut);
    expect(storage.cleared, isTrue);
    // Should the OS keep the process alive anyway, the root is already at
    // sign-in — the same place a fresh launch lands.
    expect(find.byType(LoginScreen), findsOneWidget);
    expect(find.byType(HomeScreen), findsNothing);
  });

  testWidgets('No still signs out and closes when the delete call fails', (
    tester,
  ) async {
    final api = _ConsentApiService(hasCurrentConsent: false, deleteFails: true);
    final storage = _MemoryStorage();
    var closed = 0;
    final provider = await _pumpRoot(
      tester,
      api,
      storage: storage,
      closeApp: () async => closed++,
    );

    final no = find.byKey(const Key('consent-no'));
    await tester.ensureVisible(no);
    await tester.tap(no);
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('consent-decline-confirm')));
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));

    expect(api.deleteCalls, 1);
    expect(closed, 1);
    expect(provider.status, AuthStatus.signedOut);
    expect(storage.cleared, isTrue);
    expect(find.byType(HomeScreen), findsNothing);
  });

  testWidgets('the system back gesture cannot dismiss the consent screen', (
    tester,
  ) async {
    final api = _ConsentApiService(hasCurrentConsent: false);
    final provider = AuthProvider(apiService: api, storage: _MemoryStorage());
    await provider.restoreSession();

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: provider,
        child: MaterialApp(
          home: Builder(
            builder: (context) => Scaffold(
              body: TextButton(
                onPressed: () => Navigator.of(context).push(
                  MaterialPageRoute<void>(
                    builder: (_) => SurveyConsentScreen(closeApp: () async {}),
                  ),
                ),
                child: const Text('open'),
              ),
            ),
          ),
        ),
      ),
    );
    await tester.tap(find.text('open'));
    await tester.pumpAndSettle();
    expect(find.byType(SurveyConsentScreen), findsOneWidget);

    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.byType(SurveyConsentScreen), findsOneWidget);
    expect(find.text('open'), findsNothing);
  });
}

Future<AuthProvider> _pumpRoot(
  WidgetTester tester,
  _ConsentApiService api, {
  _MemoryStorage? storage,
  Future<void> Function()? closeApp,
}) async {
  tester.view.physicalSize = const Size(360, 780);
  tester.view.devicePixelRatio = 1;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);

  final provider = AuthProvider(
    apiService: api,
    storage: storage ?? _MemoryStorage(),
  );
  // A reopened app: the stored token is exchanged for the backend's view of
  // the account, consent flag included, before anything is shown.
  await provider.restoreSession();
  expect(provider.status, AuthStatus.signedIn);

  await tester.pumpWidget(
    ChangeNotifierProvider.value(
      value: provider,
      child: MaterialApp(home: RootScreen(closeApp: closeApp ?? () async {})),
    ),
  );
  await tester.pump();
  return provider;
}

class _ConsentApiService extends ApiService {
  _ConsentApiService({required this.hasCurrentConsent, this.deleteFails = false});

  bool hasCurrentConsent;
  final bool deleteFails;
  int consentCalls = 0;
  int deleteCalls = 0;

  AuthSession _session(String token) => AuthSession(
    token: token,
    role: 'student',
    shezenId: 'SZ-TESTIDENTITY',
    hasCompletedRequiredAssessment: true,
    hasCurrentConsent: hasCurrentConsent,
  );

  @override
  Future<AuthSession> me(String token) async => _session(token);

  @override
  Future<AuthSession> recordConsent(String token) async {
    consentCalls++;
    hasCurrentConsent = true;
    return _session(token);
  }

  @override
  Future<void> deleteAccount(String token) async {
    deleteCalls++;
    if (deleteFails) {
      throw const ApiException('Unable to connect to SheZen.');
    }
  }

  @override
  Future<void> logout(String token) async {}
}

class _MemoryStorage extends SecureTokenStorage {
  bool cleared = false;

  @override
  Future<Map<String, String>?> read() async => {
    'token': 'stored-token',
    'role': 'student',
  };

  @override
  Future<void> save({required String token, required String role}) async {}

  @override
  Future<void> clear() async {
    cleared = true;
  }
}
