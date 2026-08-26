import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';
import 'package:shezen_harmony/features/home/presentation/home_screen.dart';

void main() {
  testWidgets('profile shows SheZen ID without student name or email', (
    tester,
  ) async {
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
  });
}

class _SignedInApiService extends ApiService {
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
