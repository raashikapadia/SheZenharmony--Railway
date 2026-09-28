import 'package:flutter/foundation.dart';

import '../../../core/network/api_service.dart';
import '../data/assessment_questionnaire.dart';
import '../data/assessment_result.dart';
import 'questionnaire_pager.dart';

enum AssessmentLoadState { loading, loaded, error }

/// Drives the questionnaire-taking flow: loads the live questionnaire, tracks
/// the user's in-progress answers and position, and submits for server-side
/// scoring.
/// Every question, option, answer mode and score comes from the API
/// response — nothing here is hardcoded, so the flow adapts automatically
/// if the admin changes the question count, text, answer modes or points.
///
/// Questions are shown a page at a time. The pages come from
/// [QuestionnairePager], which sizes them from the questions themselves, so
/// 10 questions and 100 questions both land in sensible screens without
/// anyone laying them out by hand.
class AssessmentProvider extends ChangeNotifier {
  AssessmentProvider({
    required this._apiService,
    required this._token,
    this.closeApiServiceOnDispose = false,
    this._pager = const QuestionnairePager(),
  });

  final ApiService _apiService;
  final String _token;

  final bool closeApiServiceOnDispose;

  final QuestionnairePager _pager;
  AssessmentLoadState _state = AssessmentLoadState.loading;
  AssessmentQuestionnaire? _questionnaire;
  List<QuestionnairePage> _pages = const [];
  String? _errorMessage;
  int _pageIndex = 0;

  /// question id → the chosen option ids, in the order they were chosen. A
  /// single-answer question holds exactly one; a multi-select one holds up
  /// to its configured limit.
  final Map<int, List<int>> _answers = {};
  bool _isSubmitting = false;
  String? _submitError;
  AssessmentResult? _result;

  AssessmentLoadState get state => _state;
  AssessmentQuestionnaire? get questionnaire => _questionnaire;
  String? get errorMessage => _errorMessage;
  bool get isSubmitting => _isSubmitting;
  String? get submitError => _submitError;
  AssessmentResult? get result => _result;

  // ---- Questions and answers --------------------------------------------

  int get questionCount => _questionnaire?.questions.length ?? 0;

  /// Sections that actually hold questions, in the order they are shown —
  /// an admin section with nothing in it yet is not a step the user takes.
  int get renderedSectionCount => _pages.isEmpty
      ? 0
      : (_pages.last.section == null
            ? _pages.last.sectionIndex
            : _pages.last.sectionIndex + 1);

  /// Every option ticked for [questionId], in the order they were chosen.
  List<int> selectedOptionsFor(int questionId) =>
      List.unmodifiable(_answers[questionId] ?? const <int>[]);

  bool isSelected(int questionId, int optionId) =>
      _answers[questionId]?.contains(optionId) ?? false;

  bool isAnswered(int questionId) => _answers[questionId]?.isNotEmpty ?? false;

  /// How many questions have an answer, across the whole questionnaire.
  int get answeredCount => _answers.length;

  /// Fraction of all questions answered, 0..1. Progress is measured by
  /// answers rather than by page so it rises as the user works, not in jumps.
  double get progress => questionCount == 0 ? 0 : answeredCount / questionCount;

  bool get allRequiredAnswered {
    final questions = _questionnaire?.questions ?? const [];
    return questions.where((q) => q.required).every((q) => isAnswered(q.id));
  }

  // ---- Pages -------------------------------------------------------------

  List<QuestionnairePage> get pages => _pages;
  int get pageCount => _pages.length;
  int get pageIndex => _pageIndex;
  QuestionnairePage? get currentPage =>
      _pageIndex >= 0 && _pageIndex < _pages.length ? _pages[_pageIndex] : null;
  bool get isFirstPage => _pageIndex <= 0;
  bool get isLastPage => _pageIndex >= pageCount - 1;

  /// Required questions on the current page still waiting for an answer.
  List<PagedQuestion> get unansweredOnCurrentPage => [
    for (final item in currentPage?.questions ?? const <PagedQuestion>[])
      if (item.question.required && !isAnswered(item.question.id)) item,
  ];

  // ---- Lifecycle ---------------------------------------------------------

  Future<void> load() async {
    _state = AssessmentLoadState.loading;
    _errorMessage = null;
    notifyListeners();

    try {
      final questionnaire = await _apiService.activeQuestionnaire();
      _questionnaire = questionnaire;
      _pages = _pager.paginate(questionnaire);
      _pageIndex = 0;
      _answers.clear();
      _state = AssessmentLoadState.loaded;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _state = AssessmentLoadState.error;
    }
    notifyListeners();
  }

  /// Choose the one answer to a single-answer question (replacing any
  /// earlier choice). For a multi-select question this ticks the option,
  /// the same as [toggleAnswer].
  void selectAnswer(int questionId, int optionId) {
    final question = _question(questionId);
    if (question?.allowsMultiple == true) {
      toggleAnswer(questionId, optionId);
      return;
    }
    _answers[questionId] = [optionId];
    notifyListeners();
  }

  /// Tick or untick one option of a multi-select question. Returns false,
  /// and changes nothing, when ticking would exceed the question's limit —
  /// the screen then says so.
  bool toggleAnswer(int questionId, int optionId) {
    final question = _question(questionId);
    final chosen = List<int>.of(_answers[questionId] ?? const <int>[]);
    if (chosen.contains(optionId)) {
      chosen.remove(optionId);
    } else {
      final limit = question?.allowsMultiple == true
          ? question!.maxSelections
          : 1;
      if (limit == 1) {
        chosen
          ..clear()
          ..add(optionId);
      } else if (chosen.length >= limit) {
        return false;
      } else {
        chosen.add(optionId);
      }
    }
    if (chosen.isEmpty) {
      _answers.remove(questionId);
    } else {
      _answers[questionId] = chosen;
    }
    notifyListeners();
    return true;
  }

  AssessmentQuestion? _question(int questionId) {
    for (final question in _questionnaire?.questions ?? const []) {
      if (question.id == questionId) return question;
    }
    return null;
  }

  /// Advances a page. Returns false, and stays put, when a required question
  /// on this page is still unanswered — the screen then points it out.
  bool goNext() {
    if (isLastPage || unansweredOnCurrentPage.isNotEmpty) return false;
    _pageIndex++;
    notifyListeners();
    return true;
  }

  void goPrevious() {
    if (isFirstPage) return;
    _pageIndex--;
    notifyListeners();
  }

  Future<bool> submit() async {
    final questionnaire = _questionnaire;
    if (questionnaire == null || !allRequiredAnswered) return false;

    _isSubmitting = true;
    _submitError = null;
    notifyListeners();

    try {
      _result = await _apiService.submitAssessment(_token, questionnaire.id, {
        for (final entry in _answers.entries)
          entry.key: List<int>.of(entry.value),
      });
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
