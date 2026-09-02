import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';
import 'package:shezen_harmony/features/assessment/data/assessment_detail.dart';
import 'package:shezen_harmony/features/assessment/data/assessment_result.dart';
import 'package:shezen_harmony/features/assessment/presentation/assessment_detail_screen.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/data/auth_challenge.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';

void main() {
  Future<AuthProvider> signedIn() async {
    final provider = AuthProvider(
      apiService: _AuthStub(),
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
    return provider;
  }

  Future<void> pump(WidgetTester tester, ApiService api) async {
    tester.view.physicalSize = const Size(420, 2200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: await signedIn(),
        child: MaterialApp(
          home: AssessmentDetailScreen(assessmentId: 5, apiService: api),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('renders score, band, answers and recommended support', (
    tester,
  ) async {
    await pump(tester, _DetailStub());

    expect(find.text('72 / 100'), findsOneWidget);
    expect(find.text('High'), findsOneWidget);
    expect(find.text('Your answers'), findsOneWidget);
    expect(find.text('I have felt overwhelmed.'), findsOneWidget);
    expect(find.text('Talk to someone'), findsOneWidget);
  });

  testWidgets('shows a neutral error state when the API rejects the id', (
    tester,
  ) async {
    await pump(tester, _NotFoundStub());

    expect(find.text('Result unavailable'), findsOneWidget);
    expect(find.text('72 / 100'), findsNothing);
  });
}

class _DetailStub extends ApiService {
  @override
  Future<AssessmentDetail> assessmentDetail(String token, int id) async {
    return AssessmentDetail(
      id: id,
      questionnaireTitle: 'Stress Check-In',
      totalScore: 72,
      scoreOutOf: 100,
      bandCode: 'high',
      bandLabel: 'High',
      completedAt: DateTime(2026, 9, 2),
      responses: const [
        AssessmentResponseLine(
          question: 'I have felt overwhelmed.',
          answer: 'Quite a bit',
          score: 7,
        ),
      ],
      recommendedInterventions: const [
        RecommendedIntervention(title: 'Talk to someone'),
      ],
    );
  }
}

class _NotFoundStub extends ApiService {
  @override
  Future<AssessmentDetail> assessmentDetail(String token, int id) async {
    throw const ApiException('This assessment could not be found.');
  }
}

class _AuthStub extends ApiService {
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
