import 'dart:async';

import 'package:flutter/foundation.dart';

import '../../../core/network/api_service.dart';
import '../../../core/storage/secure_token_storage.dart';
import '../data/auth_session.dart';

enum AuthStatus { unknown, signedOut, signedIn }

/// Owns the current user's session. This is the first shared state-management
/// class in the app — the rest of the codebase used per-screen setState only.
class AuthProvider extends ChangeNotifier {
  AuthProvider({
    ApiService? apiService,
    SecureTokenStorage? storage,
    // Named publicly so focused tests can use a short timeout.
    this.restoreTimeout = const Duration(seconds: 10),
  }) : _apiService = apiService ?? ApiService(),
       _storage = storage ?? SecureTokenStorage();

  final ApiService _apiService;
  final SecureTokenStorage _storage;
  final Duration restoreTimeout;

  AuthStatus _status = AuthStatus.unknown;
  AuthSession? _session;
  bool _isLoading = false;
  String? _error;
  Map<String, List<String>>? _fieldErrors;

  AuthStatus get status => _status;
  AuthSession? get session => _session;
  bool get isLoading => _isLoading;
  String? get error => _error;
  Map<String, List<String>>? get fieldErrors => _fieldErrors;
  bool get isAdmin => _session?.isAdmin ?? false;
  bool get hasCompletedRequiredAssessment =>
      _session?.hasCompletedRequiredAssessment ?? false;

  /// Resolves the session before ever reporting `signedIn` — the mandatory
  /// questionnaire gate makes a one-time decision as soon as the app
  /// considers the user signed in, so that decision must be based on real
  /// backend truth, not an optimistic guess filled in ahead of the network
  /// call. Status stays `unknown` (loading) until this settles.
  Future<void> restoreSession() async {
    try {
      final stored = await _storage.read().timeout(restoreTimeout);
      if (stored == null) return;

      final token = stored['token'];
      if (token == null || token.trim().isEmpty) {
        throw const FormatException('Stored session has no valid token.');
      }

      _session = await _apiService.me(token).timeout(restoreTimeout);
      await _persist(_session!).timeout(restoreTimeout);
      _status = AuthStatus.signedIn;
    } on ApiException catch (error, stackTrace) {
      _session = null;
      _logRestoreFailure(
        'API failure (HTTP ${error.statusCode ?? 'unknown'})',
        stackTrace,
      );
      if (error.statusCode == 401) {
        await _clearStoredSession();
      }
    } on TimeoutException catch (_, stackTrace) {
      _session = null;
      _logRestoreFailure('timed out', stackTrace);
    } catch (error, stackTrace) {
      _session = null;
      _logRestoreFailure('unexpected ${error.runtimeType}', stackTrace);
      await _clearStoredSession();
    } finally {
      if (_status == AuthStatus.unknown) {
        _status = AuthStatus.signedOut;
      }
      notifyListeners();
    }
  }

  Future<void> _clearStoredSession() async {
    try {
      await _storage.clear().timeout(restoreTimeout);
    } on Object catch (_, stackTrace) {
      _logRestoreFailure('could not clear stored session', stackTrace);
    }
  }

  void _logRestoreFailure(String reason, StackTrace stackTrace) {
    if (!kDebugMode) return;
    debugPrint('AuthProvider.restoreSession failed: $reason. Signing out.');
    debugPrint(stackTrace.toString());
  }

  Future<bool> register({
    required String name,
    required String email,
    required String password,
    required String passwordConfirmation,
  }) async {
    _isLoading = true;
    _error = null;
    _fieldErrors = null;
    notifyListeners();

    try {
      final session = await _apiService.register(
        name: name,
        email: email,
        password: password,
        passwordConfirmation: passwordConfirmation,
      );
      _session = session;
      _status = AuthStatus.signedIn;
      await _persist(session);
      return true;
    } on ApiException catch (e) {
      _error = e.message;
      _fieldErrors = e.fieldErrors;
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> login({required String email, required String password}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final session = await _apiService.login(email: email, password: password);
      _session = session;
      _status = AuthStatus.signedIn;
      await _persist(session);
      return true;
    } on ApiException catch (e) {
      _error = e.message;
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  /// Called right after a successful assessment submission so the mandatory
  /// gate clears immediately, without waiting for another round trip. The
  /// underlying truth is still the backend row that was just created.
  void markAssessmentCompleted() {
    if (_session == null || _session!.hasCompletedRequiredAssessment) return;
    _session = _session!.copyWith(hasCompletedRequiredAssessment: true);
    notifyListeners();
  }

  Future<void> logout() async {
    final token = _session?.token;
    _session = null;
    _status = AuthStatus.signedOut;
    await _storage.clear();
    notifyListeners();

    if (token != null) {
      try {
        await _apiService.logout(token);
      } on ApiException {
        // Token is already discarded locally; a failed remote revoke isn't
        // actionable from the UI at this point.
      }
    }
  }

  Future<void> _persist(AuthSession session) {
    return _storage.save(
      token: session.token,
      userId: session.userId,
      name: session.name,
      email: session.email,
      role: session.role,
    );
  }

  @override
  void dispose() {
    _apiService.close();
    super.dispose();
  }
}
