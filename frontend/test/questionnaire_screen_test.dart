import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';
import 'package:shezen_harmony/features/assessment/data/assessment_questionnaire.dart';
import 'package:shezen_harmony/features/assessment/data/assessment_result.dart';
import 'package:shezen_harmony/features/assessment/presentation/questionnaire_screen.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/data/auth_challenge.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';

const _likert = [
  AssessmentOption(id: 1, label: 'Strongly disagree', value: 'sd'),
  AssessmentOption(id: 2, label: 'Disagree', value: 'd'),
  AssessmentOption(id: 3, label: 'Neutral', value: 'n'),
  AssessmentOption(id: 4, label: 'Agree', value: 'a'),
  AssessmentOption(id: 5, label: 'Strongly agree', value: 'sa'),
];

/// Three sections of 9, 9 and 6 scale questions — 24 in all, which the pager
/// lays out as three pages at the default phone budget.
AssessmentQuestionnaire _questionnaire() {
  final questions = <AssessmentQuestion>[];
  var position = 0;
  for (final (index, size) in const [9, 9, 6].indexed) {
    for (var i = 0; i < size; i++) {
      position++;
      questions.add(
        AssessmentQuestion(
          id: position,
          text: 'Question number $position',
          required: true,
          position: position,
          options: _likert,
          sectionId: index + 1,
        ),
      );
    }
  }
  return AssessmentQuestionnaire(
    id: 1,
    title: 'Test wellbeing questionnaire',
    questions: questions,
    sections: const [
      AssessmentSection(id: 1, title: 'Academic life', position: 1),
      AssessmentSection(id: 2, title: 'Emotional life', position: 2),
      AssessmentSection(id: 3, title: 'Social life', position: 3),
    ],
  );
}

void main() {
  Future<AuthProvider> signedInProvider() async {
    final provider = AuthProvider(
      apiService: _StubAuthApi(),
      storage: _MemoryStorage(),
    );
    await provider.register(
      email: 'student@example.com',
      password: 'safe-password',
      passwordConfirmation: 'safe-password',
      demographics: const {},
      privacyConsent: true,
    );
    await provider.verifyOtp('123456');
    return provider;
  }

  Future<_QuestionnaireApi> pumpQuestionnaire(WidgetTester tester) async {
    tester.view.physicalSize = const Size(400, 760);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final auth = await signedInProvider();
    final api = _QuestionnaireApi();
    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: auth,
        child: MaterialApp(
          home: QuestionnaireScreen(mandatory: false, apiService: api),
        ),
      ),
    );
    await tester.pumpAndSettle();
    return api;
  }

  Future<void> answerVisible(WidgetTester tester, Iterable<int> numbers) async {
    for (final number in numbers) {
      final card = find.ancestor(
        of: find.text('Question number $number'),
        matching: find.byType(AnimatedContainer),
      );
      await tester.ensureVisible(card.first);
      await tester.tap(
        find.descendant(of: card.first, matching: find.text('Agree')),
      );
      await tester.pump();
    }
  }

  testWidgets('pages are laid out from the data, with live progress', (
    tester,
  ) async {
    await pumpQuestionnaire(tester);

    // The intro already knows the shape of what is coming.
    expect(
      find.text('24 questions across 3 sections, in 3 short pages'),
      findsOneWidget,
    );
    await tester.tap(find.text('Begin'));
    await tester.pumpAndSettle();

    expect(find.text('SECTION 1 OF 3'), findsOneWidget);
    expect(find.textContaining('Academic life'), findsOneWidget);
    expect(find.textContaining('Questions 1–9 of 9'), findsOneWidget);
    expect(find.text('0 of 24 answered · 0%'), findsOneWidget);
    expect(find.text('Question number 1'), findsOneWidget);
    expect(find.text('Question number 10'), findsNothing);

    await answerVisible(tester, [1]);
    expect(find.text('1 of 24 answered · 4%'), findsOneWidget);
  });

  testWidgets('Next points at unanswered questions instead of moving on', (
    tester,
  ) async {
    await pumpQuestionnaire(tester);
    await tester.tap(find.text('Begin'));
    await tester.pumpAndSettle();

    await answerVisible(tester, [1, 2, 3]);
    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();

    // Still on page one, and the first gap is called out.
    expect(find.text('SECTION 1 OF 3'), findsOneWidget);
    expect(
      find.text('6 questions on this page still need an answer.'),
      findsOneWidget,
    );
    expect(find.text('Please choose an answer to continue.'), findsWidgets);
  });

  testWidgets('answers survive going back and forward between pages', (
    tester,
  ) async {
    await pumpQuestionnaire(tester);
    await tester.tap(find.text('Begin'));
    await tester.pumpAndSettle();

    await answerVisible(tester, List.generate(9, (i) => i + 1));
    await tester.ensureVisible(find.text('Next'));
    await tester.tap(find.text('Next'));
    await tester.pumpAndSettle();

    // Finishing a section earns a pause before the next one begins.
    expect(find.text('Academic life complete'), findsOneWidget);
    expect(find.text('NEXT'), findsOneWidget);
    expect(find.text('Emotional life'), findsOneWidget);
    expect(
      find.text('1 of 3 sections done · 9 of 24 answered'),
      findsOneWidget,
    );
    expect(find.text('Question number 10'), findsNothing);
    await tester.tap(find.text('Continue'));
    await tester.pumpAndSettle();

    expect(find.text('SECTION 2 OF 3'), findsOneWidget);
    expect(find.textContaining('Emotional life'), findsOneWidget);
    expect(find.text('Question number 10'), findsOneWidget);
    expect(find.text('9 of 24 answered · 38%'), findsOneWidget);

    await tester.tap(find.text('Back'));
    await tester.pumpAndSettle();

    expect(find.text('SECTION 1 OF 3'), findsOneWidget);
    // Every answered card shows its tick, and the count is untouched.
    expect(find.byIcon(Icons.check_rounded), findsNWidgets(9));
    expect(find.text('9 of 24 answered · 38%'), findsOneWidget);
  });

  testWidgets('the last page submits every answer by backend id', (
    tester,
  ) async {
    final api = await pumpQuestionnaire(tester);
    await tester.tap(find.text('Begin'));
    await tester.pumpAndSettle();

    for (final page in const [(1, 9), (10, 18), (19, 24)]) {
      final (from, to) = page;
      await answerVisible(tester, [for (var n = from; n <= to; n++) n]);
      final button = to == 24
          ? find.text('Submit check-in')
          : find.text('Next');
      await tester.ensureVisible(button);
      await tester.tap(button);
      await tester.pumpAndSettle();
      if (to != 24) {
        await tester.tap(find.text('Continue'));
        await tester.pumpAndSettle();
      }
    }

    expect(api.submittedAnswers, hasLength(24));
    expect(api.submittedAnswers?.values.expand((ids) => ids).toSet(), {4});
  });
}

class _QuestionnaireApi extends ApiService {
  Map<int, List<int>>? submittedAnswers;

  @override
  Future<AssessmentQuestionnaire> activeQuestionnaire() async =>
      _questionnaire();

  @override
  Future<AssessmentResult> submitAssessment(
    String token,
    int questionnaireId,
    Map<int, List<int>> answers,
  ) async {
    submittedAnswers = answers;
    return const AssessmentResult(
      assessmentId: 1,
      totalScore: 96,
      scoreOutOf: 120,
      bandCode: 'high',
      bandLabel: 'High mental well-being',
    );
  }
}

class _StubAuthApi extends ApiService {
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
    maskedEmail: 's*******@student.usp.ac.fj',
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
