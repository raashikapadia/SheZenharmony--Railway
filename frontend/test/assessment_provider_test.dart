import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/features/assessment/application/assessment_provider.dart';
import 'package:shezen_harmony/features/assessment/data/assessment_questionnaire.dart';
import 'package:shezen_harmony/features/assessment/data/assessment_result.dart';

void main() {
  test(
    'dynamic questionnaire navigation and submission use backend IDs',
    () async {
      final api = _AssessmentApiService();
      final provider = AssessmentProvider(
        apiService: api,
        token: 'student-token',
      );

      await provider.load();

      expect(provider.state, AssessmentLoadState.loaded);
      expect(provider.questionCount, 2);
      expect(provider.currentQuestion?.text, 'How do you feel?');
      expect(provider.canGoNext, isFalse);

      provider.selectAnswer(11, 101);
      expect(provider.canGoNext, isTrue);
      provider.goNext();
      provider.selectAnswer(12, 202);

      expect(provider.allRequiredAnswered, isTrue);
      expect(await provider.submit(), isTrue);
      expect(api.submittedQuestionnaireId, 7);
      expect(api.submittedAnswers, {11: 101, 12: 202});
      expect(provider.result?.bandLabel, 'Supportive demo tier');
    },
  );
}

class _AssessmentApiService extends ApiService {
  int? submittedQuestionnaireId;
  Map<int, int>? submittedAnswers;

  @override
  Future<AssessmentQuestionnaire> activeQuestionnaire() async =>
      const AssessmentQuestionnaire(
        id: 7,
        title: 'Dynamic stress check',
        questions: [
          AssessmentQuestion(
            id: 11,
            text: 'How do you feel?',
            required: true,
            position: 1,
            options: [AssessmentOption(id: 101, label: 'Okay', value: 'okay')],
          ),
          AssessmentQuestion(
            id: 12,
            text: 'How was today?',
            required: true,
            position: 2,
            options: [AssessmentOption(id: 202, label: 'Busy', value: 'busy')],
          ),
        ],
      );

  @override
  Future<AssessmentResult> submitAssessment(
    String token,
    int questionnaireId,
    Map<int, int> answers,
  ) async {
    expect(token, 'student-token');
    submittedQuestionnaireId = questionnaireId;
    submittedAnswers = answers;
    return const AssessmentResult(
      assessmentId: 99,
      totalScore: 3,
      bandCode: 'demo',
      bandLabel: 'Supportive demo tier',
    );
  }
}
