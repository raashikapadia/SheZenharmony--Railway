import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';
import 'package:shezen_harmony/features/auth/data/auth_challenge.dart';
import 'package:shezen_harmony/features/home/presentation/home_screen.dart';

void main() {
  testWidgets('profile shows SheZen ID without student name or email', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(320, 568);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);
    final provider = AuthProvider(
      apiService: _SignedInApiService(),
      storage: _MemoryStorage(),
    );
    await provider.register(
      email: 'hidden@student.usp.ac.fj',
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
    await tester.tap(find.text('Profile'));
    await tester.pumpAndSettle();

    expect(find.text('SZ-TESTIDENTITY'), findsOneWidget);
    expect(find.text('hidden@student.usp.ac.fj'), findsNothing);
    expect(find.text('Hidden Student Name'), findsNothing);
    expect(tester.takeException(), isNull);
  });
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
    maskedEmail: 'h*****@student.usp.ac.fj',
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
