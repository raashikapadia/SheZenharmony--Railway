import 'package:flutter/foundation.dart';

import '../../../core/network/api_service.dart';
import '../data/questionnaire.dart';

enum BuilderLoadState { loading, loaded, error }

/// Drives the questionnaire builder screen: loading one questionnaire with
/// its ordered questions, and every question-level mutation (add/edit/
/// delete/reorder). Every mutation re-fetches from the backend so the UI
/// never assumes an operation succeeded without confirmation.
class QuestionnaireBuilderProvider extends ChangeNotifier {
  QuestionnaireBuilderProvider({
    required ApiService apiService,
    required String token,
    required this.questionnaireId,
  }) : _apiService = apiService,
       _token = token;

  final ApiService _apiService;
  final String _token;
  final int questionnaireId;

  BuilderLoadState _state = BuilderLoadState.loading;
  Questionnaire? _questionnaire;
  String? _errorMessage;
  bool _isMutating = false;
  String? _actionError;
  Map<String, List<String>>? _actionFieldErrors;
  String? _lastActionMessage;

  BuilderLoadState get state => _state;
  Questionnaire? get questionnaire => _questionnaire;
  String? get errorMessage => _errorMessage;
  bool get isMutating => _isMutating;
  String? get actionError => _actionError;
  Map<String, List<String>>? get actionFieldErrors => _actionFieldErrors;
  String? get lastActionMessage => _lastActionMessage;

  Future<void> load() async {
    _state = BuilderLoadState.loading;
    _errorMessage = null;
    notifyListeners();

    try {
      _questionnaire = await _apiService.adminShowQuestionnaire(_token, questionnaireId);
      _state = BuilderLoadState.loaded;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _state = BuilderLoadState.error;
    }
    notifyListeners();
  }

  Future<bool> addQuestion({
    required String questionText,
    String? dimension,
    required String questionType,
    required bool isRequired,
    required List<Map<String, dynamic>> options,
  }) => _mutate(() => _apiService.adminAddQuestion(
    _token,
    questionnaireId,
    questionText: questionText,
    dimension: dimension,
    questionType: questionType,
    isRequired: isRequired,
    options: options,
  ), successMessage: 'Question added.');

  Future<bool> updateQuestion({
    required int questionId,
    required String questionText,
    String? dimension,
    required String questionType,
    required bool isRequired,
    required List<Map<String, dynamic>> options,
  }) => _mutate(() => _apiService.adminUpdateQuestion(
    _token,
    questionnaireId,
    questionId,
    questionText: questionText,
    dimension: dimension,
    questionType: questionType,
    isRequired: isRequired,
    options: options,
  ), successMessage: 'Question updated.');

  Future<bool> deleteQuestion(int questionId) async {
    _isMutating = true;
    _actionError = null;
    notifyListeners();

    try {
      _lastActionMessage = await _apiService.adminDeleteQuestion(_token, questionnaireId, questionId);
      await load();
      return true;
    } on ApiException catch (e) {
      _actionError = e.message;
      _isMutating = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> reorder(List<int> orderedQuestionIds) async {
    _isMutating = true;
    _actionError = null;
    notifyListeners();

    try {
      final positions = [
        for (var i = 0; i < orderedQuestionIds.length; i++) {'id': orderedQuestionIds[i], 'position': i + 1},
      ];
      await _apiService.adminReorderQuestions(_token, questionnaireId, positions);
      await load();
      return true;
    } on ApiException catch (e) {
      _actionError = e.message;
      _isMutating = false;
      notifyListeners();
      return false;
    }
  }

  Future<bool> addScoreBand({
    required String code,
    required String label,
    required int minScore,
    required int maxScore,
  }) => _mutate(() => _apiService.adminAddScoreBand(
    _token,
    questionnaireId,
    code: code,
    label: label,
    minScore: minScore,
    maxScore: maxScore,
  ), successMessage: 'Score range added.');

  Future<bool> updateScoreBand({
    required int bandId,
    required String code,
    required String label,
    required int minScore,
    required int maxScore,
    required bool isActive,
  }) => _mutate(() => _apiService.adminUpdateScoreBand(
    _token,
    questionnaireId,
    bandId,
    code: code,
    label: label,
    minScore: minScore,
    maxScore: maxScore,
    isActive: isActive,
  ), successMessage: 'Score range updated.');

  Future<bool> deleteScoreBand(int bandId) async {
    _isMutating = true;
    _actionError = null;
    notifyListeners();

    try {
      _lastActionMessage = await _apiService.adminDeleteScoreBand(_token, questionnaireId, bandId);
      await load();
      return true;
    } on ApiException catch (e) {
      _actionError = e.message;
      _isMutating = false;
      notifyListeners();
      return false;
    }
  }

  /// Clones this questionnaire into a new draft version. Returns the new
  /// version's id on success so the caller can navigate straight to it.
  Future<int?> createNewVersion() async {
    _isMutating = true;
    _actionError = null;
    notifyListeners();

    try {
      final clone = await _apiService.adminCreateNewVersion(_token, questionnaireId);
      return clone.id;
    } on ApiException catch (e) {
      _actionError = e.message;
      return null;
    } finally {
      _isMutating = false;
      notifyListeners();
    }
  }

  Future<bool> _mutate(Future<void> Function() operation, {required String successMessage}) async {
    _isMutating = true;
    _actionError = null;
    _actionFieldErrors = null;
    notifyListeners();

    try {
      await operation();
      _lastActionMessage = successMessage;
      await load();
      return true;
    } on ApiException catch (e) {
      _actionError = e.message;
      _actionFieldErrors = e.fieldErrors;
      _isMutating = false;
      notifyListeners();
      return false;
    }
  }

  void clearActionError() {
    _actionError = null;
    _actionFieldErrors = null;
    notifyListeners();
  }
}
