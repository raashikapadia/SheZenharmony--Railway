import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/data/auth_challenge.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';
import 'package:shezen_harmony/features/auth/presentation/login_screen.dart';

void main() {
  testWidgets('login requires the emailed OTP before becoming signed in', (
    tester,
  ) async {
    final storage = _MemoryStorage();
    final provider = AuthProvider(
      apiService: _MfaApiService(),
      storage: storage,
    );

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: provider,
        child: const MaterialApp(home: LoginScreen()),
      ),
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'USP student email'),
      's12345678@student.usp.ac.fj',
    );
    await tester.enterText(
      find.widgetWithText(TextFormField, 'Password'),
      'safe-password',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Sign in'));
    await tester.pumpAndSettle();

    expect(find.text('Confirm it’s you'), findsOneWidget);
    expect(find.textContaining('s***@student.usp.ac.fj'), findsOneWidget);
    expect(provider.status, isNot(AuthStatus.signedIn));
    expect(storage.savedToken, isNull);

    await tester.enterText(find.byKey(const Key('otp-code-field')), '123456');
    await tester.tap(find.byKey(const Key('verify-otp-button')));
    await tester.pumpAndSettle();

    expect(provider.status, AuthStatus.signedIn);
    expect(storage.savedToken, 'verified-token');
  });
}

class _MfaApiService extends ApiService {
  @override
  Future<AuthChallenge> login({
    required String email,
    required String password,
    String deviceName = 'SheZen mobile app',
  }) async => const AuthChallenge(
    id: '22222222-2222-4222-8222-222222222222',
    purpose: 'login',
    maskedEmail: 's***@student.usp.ac.fj',
    expiresInSeconds: 600,
    resendAfterSeconds: 60,
  );

  @override
  Future<AuthSession> verifyOtp({
    required String challengeId,
    required String code,
  }) async => const AuthSession(
    token: 'verified-token',
    role: 'student',
    shezenId: 'SZ-TESTIDENTITY',
    hasCompletedRequiredAssessment: false,
  );
}

class _MemoryStorage extends SecureTokenStorage {
  String? savedToken;

  @override
  Future<void> save({required String token, required String role}) async {
    savedToken = token;
  }
}
