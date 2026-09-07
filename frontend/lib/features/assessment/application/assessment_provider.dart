import 'package:flutter/foundation.dart';

import '../../../core/network/api_service.dart';
import '../data/assessment_questionnaire.dart';
import '../data/assessment_result.dart';

enum AssessmentLoadState { loading, loaded, error }

/// Drives the questionnaire-taking flow: loads whichever questionnaire the
/// backend currently has active, tracks the user's in-progress answers and
/// position, and submits for server-side scoring. Every question, option,
/// and score comes from the API response — nothing here is hardcoded, so
/// the flow adapts automatically if the admin changes the question count,
/// text, or answer scores.
class AssessmentProvider extends ChangeNotifier {
  AssessmentProvider({
    required ApiService apiService,
    required String token,
    this.closeApiServiceOnDispose = false,
  }) : _apiService = apiService,
       _token = token;

  final ApiService _apiService;
  final String _token;
  final bool closeApiServiceOnDispose;

  AssessmentLoadState _state = AssessmentLoadState.loading;
  AssessmentQuestionnaire? _questionnaire;
  String? _errorMessage;
  int _currentIndex = 0;
  final Map<int, int> _answers = {};
  bool _isSubmitting = false;
  String? _submitError;
  AssessmentResult? _result;

  AssessmentLoadState get state => _state;
  AssessmentQuestionnaire? get questionnaire => _questionnaire;
  String? get errorMessage => _errorMessage;
  int get currentIndex => _currentIndex;
  bool get isSubmitting => _isSubmitting;
  String? get submitError => _submitError;
  AssessmentResult? get result => _result;

  int get questionCount => _questionnaire?.questions.length ?? 0;
  bool get isLastQuestion => _currentIndex >= questionCount - 1;
  bool get isFirstQuestion => _currentIndex <= 0;

  AssessmentQuestion? get currentQuestion {
    final questions = _questionnaire?.questions;
    if (questions == null ||
        _currentIndex < 0 ||
        _currentIndex >= questions.length) {
      return null;
    }
    return questions[_currentIndex];
  }

  int? selectedOptionFor(int questionId) => _answers[questionId];

  bool get canGoNext {
    final question = currentQuestion;
    if (question == null) return false;
    if (!question.required) return true;
    return _answers.containsKey(question.id);
  }

  bool get allRequiredAnswered {
    final questions = _questionnaire?.questions ?? const [];
    return questions
        .where((q) => q.required)
        .every((q) => _answers.containsKey(q.id));
  }

  Future<void> load() async {
    _state = AssessmentLoadState.loading;
    _errorMessage = null;
    notifyListeners();

    try {
      _questionnaire = await _apiService.activeQuestionnaire();
      _currentIndex = 0;
      _answers.clear();
      _state = AssessmentLoadState.loaded;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _state = AssessmentLoadState.error;
    }
    notifyListeners();
  }

  void selectAnswer(int questionId, int optionId) {
    _answers[questionId] = optionId;
    notifyListeners();
  }

  void goNext() {
    if (isLastQuestion) return;
    _currentIndex++;
    notifyListeners();
  }

  void goPrevious() {
    if (isFirstQuestion) return;
    _currentIndex--;
    notifyListeners();
  }

  Future<bool> submit() async {
    final questionnaire = _questionnaire;
    if (questionnaire == null || !allRequiredAnswered) return false;

    _isSubmitting = true;
    _submitError = null;
    notifyListeners();

    try {
      _result = await _apiService.submitAssessment(
        _token,
        questionnaire.id,
        Map.of(_answers),
      );
      return true;
    } on ApiException catch (e) {
      _submitError = e.message;
      return false;
    } finally {
      _isSubmitting = false;
      notifyListeners();
    }
  }

  @override
  void dispose() {
    if (closeApiServiceOnDispose) _apiService.close();
    super.dispose();
  }
}
