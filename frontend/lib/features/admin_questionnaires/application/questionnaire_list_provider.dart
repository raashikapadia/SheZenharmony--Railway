import 'package:flutter/foundation.dart';

import '../../../core/network/api_service.dart';
import '../data/questionnaire.dart';

enum ListLoadState { loading, loaded, error }

/// Drives the admin questionnaire list screen: search, filter, pagination,
/// and the create/delete/activate/deactivate mutations that affect it.
///
/// Every mutation re-fetches from the backend afterwards rather than
/// patching local state, so the UI always reflects actual persisted data.
class QuestionnaireListProvider extends ChangeNotifier {
  QuestionnaireListProvider({
    required this._apiService,
    required this._token,
  });

  final ApiService _apiService;
  final String _token;

  ListLoadState _state = ListLoadState.loading;
  List<Questionnaire> _items = const [];
  String _search = '';
  String _filter = 'all';
  int _page = 1;
  int _lastPage = 1;
  String? _errorMessage;
  bool _isLoadingMore = false;
  String? _actionError;
  Map<String, List<String>>? _actionFieldErrors;

  ListLoadState get state => _state;
  List<Questionnaire> get items => _items;
  String get search => _search;
  String get filter => _filter;
  String? get errorMessage => _errorMessage;
  bool get isLoadingMore => _isLoadingMore;
  bool get hasMore => _page < _lastPage;
  String? get actionError => _actionError;
  Map<String, List<String>>? get actionFieldErrors => _actionFieldErrors;

  Future<void> load() async {
    _state = ListLoadState.loading;
    _errorMessage = null;
    notifyListeners();

    try {
      final result = await _apiService.adminQuestionnaires(
        _token,
        search: _search,
        filter: _filter,
        page: 1,
      );
      _items = result.items;
      _page = result.currentPage;
      _lastPage = result.lastPage;
      _state = ListLoadState.loaded;
    } on ApiException catch (e) {
      _errorMessage = e.message;
      _state = ListLoadState.error;
    }
    notifyListeners();
  }

  Future<void> loadMore() async {
    if (_isLoadingMore || !hasMore) return;
    _isLoadingMore = true;
    notifyListeners();

    try {
      final result = await _apiService.adminQuestionnaires(
        _token,
        search: _search,
        filter: _filter,
        page: _page + 1,
      );
      _items = [..._items, ...result.items];
      _page = result.currentPage;
      _lastPage = result.lastPage;
    } on ApiException catch (e) {
      _actionError = e.message;
    } finally {
      _isLoadingMore = false;
      notifyListeners();
    }
  }

  void setSearch(String value) {
    _search = value;
    load();
  }

  void setFilter(String value) {
    if (_filter == value) return;
    _filter = value;
    load();
  }

  Future<int?> createQuestionnaire({
    required String title,
    String? description,
    String? period,
    required String status,
  }) async {
    _actionError = null;
    _actionFieldErrors = null;
    try {
      final created = await _apiService.adminCreateQuestionnaire(
        _token,
        title: title,
        description: description,
        period: period,
        status: status,
      );
      await load();
      return created.id;
    } on ApiException catch (e) {
      _actionError = e.message;
      _actionFieldErrors = e.fieldErrors;
      notifyListeners();
      return null;
    }
  }

  Future<bool> updateQuestionnaire(
    int id, {
    required String title,
    String? description,
    String? period,
    required String status,
    required bool isActive,
  }) async {
    _actionError = null;
    _actionFieldErrors = null;
    try {
      await _apiService.adminUpdateQuestionnaire(
        _token,
        id,
        title: title,
        description: description,
        period: period,
        status: status,
        isActive: isActive,
      );
      await load();
      return true;
    } on ApiException catch (e) {
      _actionError = e.message;
      _actionFieldErrors = e.fieldErrors;
      notifyListeners();
      return false;
    }
  }

  Future<bool> deleteQuestionnaire(int id) async {
    _actionError = null;
    try {
      await _apiService.adminDeleteQuestionnaire(_token, id);
      await load();
      return true;
    } on ApiException catch (e) {
      _actionError = e.message;
      notifyListeners();
      return false;
    }
  }

  Future<bool> setActive(int id, bool active) async {
    _actionError = null;
    try {
      if (active) {
        await _apiService.adminActivateQuestionnaire(_token, id);
      } else {
        await _apiService.adminDeactivateQuestionnaire(_token, id);
      }
      await load();
      return true;
    } on ApiException catch (e) {
      _actionError = e.message;
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
