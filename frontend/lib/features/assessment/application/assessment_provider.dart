import 'package:flutter/foundation.dart';

import '../../../core/network/api_service.dart';
import '../data/assessment_questionnaire.dart';
import '../data/assessment_result.dart';
import 'questionnaire_pager.dart';

enum AssessmentLoadState { loading, loaded, error }

/// Drives the questionnaire-taking flow: loads whichever questionnaire the
/// backend currently has active, tracks the user's in-progress answers and
/// position, and submits for server-side scoring. Every question, option,
/// and score comes from the API response — nothing here is hardcoded, so
/// the flow adapts automatically if the admin changes the question count,
/// text, or answer scores.
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

  QuestionnairePager _pager;
  AssessmentLoadState _state = AssessmentLoadState.loading;
  AssessmentQuestionnaire? _questionnaire;
  List<QuestionnairePage> _pages = const [];
  String? _errorMessage;
  int _pageIndex = 0;
  final Map<int, int> _answers = {};
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

  int? selectedOptionFor(int questionId) => _answers[questionId];
  bool isAnswered(int questionId) => _answers.containsKey(questionId);

  /// How many questions have an answer, across the whole questionnaire.
  int get answeredCount => _answers.length;

  /// Fraction of all questions answered, 0..1. Progress is measured by
  /// answers rather than by page so it rises as the user works, not in jumps.
  double get progress => questionCount == 0 ? 0 : answeredCount / questionCount;

  bool get allRequiredAnswered {
    final questions = _questionnaire?.questions ?? const [];
    return questions
        .where((q) => q.required)
        .every((q) => _answers.containsKey(q.id));
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
      if (item.question.required && !_answers.containsKey(item.question.id))
        item,
  ];

  bool get canGoNext => !isLastPage && unansweredOnCurrentPage.isEmpty;

  /// Re-lays the pages out for a different budget — typically once the
  /// screen knows its height. Keeps the user on the page holding whatever
  /// question they were looking at.
  void repaginate(QuestionnairePager pager) {
    final questionnaire = _questionnaire;
    _pager = pager;
    if (questionnaire == null) return;

    final anchor = currentPage?.questions.firstOrNull?.question.id;
    _pages = _pager.paginate(questionnaire);
    _pageIndex = anchor == null ? 0 : _pageHolding(anchor);
    notifyListeners();
  }

  int _pageHolding(int questionId) {
    for (final (index, page) in _pages.indexed) {
      if (page.questions.any((item) => item.question.id == questionId)) {
        return index;
      }
    }
    return 0;
  }

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

  void selectAnswer(int questionId, int optionId) {
    _answers[questionId] = optionId;
    notifyListeners();
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
