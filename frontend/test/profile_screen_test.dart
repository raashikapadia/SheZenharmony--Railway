import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';
import 'package:shezen_harmony/features/profile/presentation/profile_view_screen.dart';

void main() {
  Future<AuthProvider> signedInProvider() async {
    final provider = AuthProvider(
      apiService: _StubApiService(),
      storage: _MemoryStorage(),
    );
    await provider.register(
      email: 'student@example.com',
      password: 'safe-password',
      passwordConfirmation: 'safe-password',
      demographics: const {},
      privacyConsent: true,
    );
    return provider;
  }

  testWidgets('profile view shows fetched account details and computed age', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(400, 2200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final provider = await signedInProvider();
    final api = _ProfileApiService();

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: provider,
        child: MaterialApp(home: ProfileViewScreen(apiService: api)),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('student@example.com'), findsOneWidget);
    expect(find.text('22'), findsOneWidget);
    expect(find.text('Fiji'), findsOneWidget);
    expect(find.text('Year 3'), findsOneWidget);
    expect(find.text('Yes'), findsOneWidget);
    expect(find.widgetWithText(FilledButton, 'Edit Profile'), findsOneWidget);
  });

  testWidgets('editing the profile saves changes and shows a success message', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(400, 2200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final provider = await signedInProvider();
    final api = _ProfileApiService();

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: provider,
        child: MaterialApp(home: ProfileViewScreen(apiService: api)),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.widgetWithText(FilledButton, 'Edit Profile'));
    await tester.pumpAndSettle();

    expect(find.text('Edit Profile'), findsWidgets);
    expect(
      tester
          .widget<TextFormField>(find.widgetWithText(TextFormField, 'Email'))
          .controller
          ?.text,
      'student@example.com',
    );

    final saveButton = find.widgetWithText(FilledButton, 'Save Changes');
    await tester.ensureVisible(saveButton);
    await tester.tap(saveButton);
    await tester.pumpAndSettle();

    expect(api.lastUpdate?['country'], 'Fiji');
    expect(
      find.text('Your profile has been updated successfully.'),
      findsOneWidget,
    );
  });
}

class _ProfileApiService extends ApiService {
  Map<String, dynamic>? lastUpdate;

  Map<String, dynamic> _profileJson() => {
    'email': 'student@example.com',
    'date_of_birth': '2004-03-15',
    'age': 22,
    'country': 'Fiji',
    'year_of_study': 'Year 3',
    'employment_status': 'Not employed',
    'relationship_status': 'Single',
    'has_children': true,
    'living_situation': 'With family',
  };

  @override
  Future<Map<String, dynamic>> getProfile(String token) async =>
      _profileJson();

  @override
  Future<Map<String, dynamic>> updateProfile(
    String token,
    Map<String, dynamic> updates,
  ) async {
    lastUpdate = updates;
    return _profileJson();
  }
}

class _StubApiService extends ApiService {
  @override
  Future<AuthSession> register({
    required String email,
    required String password,
    required String passwordConfirmation,
    required Map<String, dynamic> demographics,
    required bool privacyConsent,
    String deviceName = 'SheZen mobile app',
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
