import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../../features/admin_questionnaires/data/questionnaire.dart';
import '../../features/admin_questionnaires/data/score_band.dart';
import '../../features/admin_questionnaires/data/stress_question.dart';
import '../../features/activities/data/support_content.dart';
import '../../features/assessment/data/assessment_detail.dart';
import '../../features/assessment/data/assessment_questionnaire.dart';
import '../../features/assessment/data/assessment_result.dart';
import '../../features/auth/data/auth_session.dart';
import '../../features/auth/data/auth_challenge.dart';
import '../config/api_config.dart';

class ApiService {
  ApiService({http.Client? client}) : _client = client ?? http.Client();

  static const _emailRequestTimeout = Duration(seconds: 30);

  final http.Client _client;

  Future<AuthChallenge> register({
    required String email,
    required String password,
    required String passwordConfirmation,
    required Map<String, dynamic> demographics,
    required bool privacyConsent,
    String deviceName = 'SheZen mobile app',
  }) async {
    final http.Response response;
    try {
      response = await _client
          .post(
            Uri.parse('${ApiConfig.baseUrl}/v1/auth/register'),
            headers: const {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            body: jsonEncode({
              'email': email,
              'password': password,
              'password_confirmation': passwordConfirmation,
              'device_name': deviceName,
              'demographics': demographics,
              'privacy_consent': privacyConsent,
            }),
          )
          .timeout(_emailRequestTimeout);
    } on http.ClientException {
      throw const ApiException(
        'Unable to connect to SheZen. Please try again.',
      );
    } on TimeoutException {
      throw const ApiException(
        'The verification email is taking longer than expected. Try signing in with the same email and password to continue.',
      );
    }

    final body = _decodeObject(response);
    if (response.statusCode != 201) {
      throw ApiException(
        _errorMessage(body, 'Registration failed.'),
        statusCode: response.statusCode,
        fieldErrors: _fieldErrors(body),
      );
    }

    try {
      return AuthChallenge.fromResponse(body);
    } on FormatException {
      throw const ApiException(
        'Backend returned an unexpected registration response.',
      );
    }
  }

  Future<AuthChallenge> login({
    required String email,
    required String password,
    String deviceName = 'SheZen mobile app',
  }) async {
    final http.Response response;
    try {
      response = await _client
          .post(
            Uri.parse('${ApiConfig.baseUrl}/v1/auth/login'),
            headers: const {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            body: jsonEncode({
              'email': email,
              'password': password,
              'device_name': deviceName,
            }),
          )
          .timeout(_emailRequestTimeout);
    } on http.ClientException {
      throw const ApiException(
        'Unable to connect to SheZen. Please try again.',
      );
    } on TimeoutException {
      throw const ApiException(
        'The verification email is taking longer than expected. Please try again.',
      );
    }

    final body = _decodeObject(response);
    if (response.statusCode != 200) {
      throw ApiException(
        _errorMessage(body, 'Sign in failed.'),
        statusCode: response.statusCode,
      );
    }

    try {
      return AuthChallenge.fromResponse(body);
    } on FormatException {
      throw const ApiException(
        'Backend returned an unexpected sign-in response.',
      );
    }
  }

  Future<AuthSession> verifyOtp({
    required String challengeId,
    required String code,
  }) async {
    final response = await _postPublicJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/auth/verify-otp'),
      {'challenge_id': challengeId, 'code': code},
    );
    try {
      return AuthSession.fromJson(response);
    } on FormatException {
      throw const ApiException(
        'Backend returned an unexpected verification response.',
      );
    }
  }

  Future<AuthChallenge> resendOtp(String challengeId) async {
    final response = await _postPublicJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/auth/resend-otp'),
      {'challenge_id': challengeId},
    );
    try {
      return AuthChallenge.fromResponse(response);
    } on FormatException {
      throw const ApiException(
        'Backend returned an unexpected verification response.',
      );
    }
  }

  /// Re-fetches the current user from the backend — used on app resume so
  /// completion status and role always reflect real server state rather
  /// than a cached client value.
  Future<AuthSession> me(String token) async {
    final body = await _getJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/auth/me'),
      token,
    );
    try {
      return AuthSession.fromJson({'token': token, 'user': body['user']});
    } on FormatException {
      throw const ApiException(
        'Backend returned an unexpected profile response.',
      );
    }
  }

  Future<Map<String, dynamic>> getProfile(String token) async {
    final body = await _getJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/profile'),
      token,
    );
    return body['data'] as Map<String, dynamic>? ?? const {};
  }

  /// [updates] should only contain fields the student is permitted to
  /// change — the backend also enforces an explicit allowlist server-side.
  Future<Map<String, dynamic>> updateProfile(
    String token,
    Map<String, dynamic> updates,
  ) async {
    final body = await _sendJson(
      'PUT',
      Uri.parse('${ApiConfig.baseUrl}/v1/profile'),
      token,
      updates,
    );
    return body['data'] as Map<String, dynamic>? ?? const {};
  }

  Future<void> logout(String token) async {
    final response = await _client.post(
      Uri.parse('${ApiConfig.baseUrl}/v1/auth/logout'),
      headers: _authorizedHeaders(token),
    );

    if (response.statusCode != 200) {
      throw ApiException(
        'Could not sign out (${response.statusCode}).',
        statusCode: response.statusCode,
      );
    }
  }

  Future<void> deleteAccount(String token) async {
    await _sendJson(
      'DELETE',
      Uri.parse('${ApiConfig.baseUrl}/v1/auth/account'),
      token,
      null,
    );
  }

  Future<Map<String, dynamic>> health() async {
    final uri = Uri.parse('${ApiConfig.baseUrl}/health');

    final response = await _client
        .get(uri, headers: const {'Accept': 'application/json'})
        .timeout(const Duration(seconds: 8));

    final Object? body = jsonDecode(response.body);

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(
        'Backend returned HTTP ${response.statusCode}.',
        statusCode: response.statusCode,
      );
    }

    if (body is! Map<String, dynamic>) {
      throw const ApiException('Backend returned an unexpected response.');
    }

    return body;
  }

  Future<List<dynamic>> questions() async {
    final uri = Uri.parse('${ApiConfig.baseUrl}/v1/questions');
    final response = await _client.get(
      uri,
      headers: const {'Accept': 'application/json'},
    );

    if (response.statusCode != 200) {
      throw ApiException(
        'Could not load questions (${response.statusCode}).',
        statusCode: response.statusCode,
      );
    }

    final body = jsonDecode(response.body);
    if (body is Map<String, dynamic> && body['data'] is List) {
      return body['data'] as List<dynamic>;
    }

    throw const ApiException('Unexpected question response.');
  }

  // ---------------------------------------------------------------------
  // Stress assessment (student-facing) — the same active questionnaire
  // backs both the mandatory post-registration flow and the optional
  // in-app check-in.
  // ---------------------------------------------------------------------

  Future<AssessmentQuestionnaire> activeQuestionnaire() async {
    final uri = Uri.parse('${ApiConfig.baseUrl}/v1/questionnaires/active');
    final http.Response response;
    try {
      response = await _client
          .get(uri, headers: const {'Accept': 'application/json'})
          .timeout(const Duration(seconds: 10));
    } on http.ClientException {
      throw const ApiException(
        'Network error — check your connection and that the server is reachable.',
      );
    } on TimeoutException {
      throw const ApiException(
        'Network error — check your connection and that the server is reachable.',
      );
    }
    final body = _handleResponse(response);
    return AssessmentQuestionnaire.fromJson(
      body['data'] as Map<String, dynamic>,
    );
  }

  /// [answers] maps questionId -> optionId. The backend independently
  /// recalculates the score and stress level from these IDs — it never
  /// trusts a score computed on the client.
  Future<AssessmentResult> submitAssessment(
    String token,
    int questionnaireId,
    Map<int, int> answers,
  ) async {
    final body = await _sendJson(
      'POST',
      Uri.parse('${ApiConfig.baseUrl}/v1/assessments'),
      token,
      {
        'questionnaire_id': questionnaireId,
        'answers': [
          for (final entry in answers.entries)
            {'question_id': entry.key, 'option_id': entry.value},
        ],
      },
    );
    return AssessmentResult.fromJson(body);
  }

  Future<List<AssessmentSummary>> myAssessments(String token) async {
    final body = await _getJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/assessments'),
      token,
    );
    return (body['data'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(AssessmentSummary.fromJson)
        .toList();
  }

  /// One completed assessment belonging to the authenticated student. The
  /// backend enforces ownership and returns 404 for anyone else's id.
  Future<AssessmentDetail> assessmentDetail(String token, int id) async {
    final body = await _getJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/assessments/$id'),
      token,
    );
    return AssessmentDetail.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<List<WellbeingActivity>> wellbeingActivities() async {
    final responses = await Future.wait([
      _getPublicJson(Uri.parse('${ApiConfig.baseUrl}/v1/wellbeing-activities')),
      _getPublicJson(
        Uri.parse('${ApiConfig.baseUrl}/v1/interventions').replace(
          queryParameters: {
            'content_type':
                'breathing,grounding,mindfulness,relaxation,activity,resource',
          },
        ),
      ),
    ]);
    final videoActivities = (responses[0]['data'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(WellbeingActivity.fromJson);
    final guidedActivities =
        (responses[1]['data'] as List<dynamic>? ?? const [])
            .whereType<Map<String, dynamic>>()
            .map(WellbeingActivity.fromInterventionJson);
    return [...guidedActivities, ...videoActivities];
  }

  Future<List<PositiveContent>> positiveEngagement() async {
    final uri = Uri.parse('${ApiConfig.baseUrl}/v1/interventions').replace(
      queryParameters: {
        'content_type':
            'journaling,affirmation,quiz,motivation,positive_engagement',
      },
    );
    final body = await _getPublicJson(uri);
    return (body['data'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(PositiveContent.fromJson)
        .toList();
  }

  // ---------------------------------------------------------------------
  // Admin: questionnaires
  // ---------------------------------------------------------------------

  Future<QuestionnairePage> adminQuestionnaires(
    String token, {
    String search = '',
    String filter = 'all',
    int page = 1,
  }) async {
    final uri = Uri.parse('${ApiConfig.baseUrl}/v1/admin/questionnaires')
        .replace(
          queryParameters: {
            if (search.isNotEmpty) 'search': search,
            'filter': filter,
            'page': page.toString(),
          },
        );

    final body = await _getJson(uri, token);
    final items = (body['data'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(Questionnaire.fromJson)
        .toList();
    final meta = body['meta'] as Map<String, dynamic>? ?? const {};

    return QuestionnairePage(
      items: items,
      currentPage: meta['current_page'] as int? ?? 1,
      lastPage: meta['last_page'] as int? ?? 1,
      total: meta['total'] as int? ?? items.length,
    );
  }

  Future<Questionnaire> adminShowQuestionnaire(String token, int id) async {
    final body = await _getJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/admin/questionnaires/$id'),
      token,
    );
    return Questionnaire.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<Questionnaire> adminCreateQuestionnaire(
    String token, {
    required String title,
    String? description,
    String? period,
    String status = 'draft',
  }) async {
    final body = await _sendJson(
      'POST',
      Uri.parse('${ApiConfig.baseUrl}/v1/admin/questionnaires'),
      token,
      {
        'title': title,
        'description': description,
        'period': period,
        'status': status,
      },
    );
    return Questionnaire.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<Questionnaire> adminUpdateQuestionnaire(
    String token,
    int id, {
    required String title,
    String? description,
    String? period,
    required String status,
    required bool isActive,
  }) async {
    final body = await _sendJson(
      'PUT',
      Uri.parse('${ApiConfig.baseUrl}/v1/admin/questionnaires/$id'),
      token,
      {
        'title': title,
        'description': description,
        'period': period,
        'status': status,
        'is_active': isActive,
      },
    );
    return Questionnaire.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<void> adminDeleteQuestionnaire(String token, int id) async {
    await _sendJson(
      'DELETE',
      Uri.parse('${ApiConfig.baseUrl}/v1/admin/questionnaires/$id'),
      token,
      null,
    );
  }

  /// Clones the questionnaire into a new draft version with independent
  /// copies of its questions/options/score bands — the sanctioned way to
  /// make structural changes once a questionnaire has assessment history.
  Future<Questionnaire> adminCreateNewVersion(String token, int id) async {
    final body = await _sendJson(
      'POST',
      Uri.parse('${ApiConfig.baseUrl}/v1/admin/questionnaires/$id/new-version'),
      token,
      null,
    );
    return Questionnaire.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<Questionnaire> adminActivateQuestionnaire(String token, int id) async {
    final body = await _sendJson(
      'PATCH',
      Uri.parse('${ApiConfig.baseUrl}/v1/admin/questionnaires/$id/activate'),
      token,
      null,
    );
    return Questionnaire.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<Questionnaire> adminDeactivateQuestionnaire(
    String token,
    int id,
  ) async {
    final body = await _sendJson(
      'PATCH',
      Uri.parse('${ApiConfig.baseUrl}/v1/admin/questionnaires/$id/deactivate'),
      token,
      null,
    );
    return Questionnaire.fromJson(body['data'] as Map<String, dynamic>);
  }

  // ---------------------------------------------------------------------
  // Admin: score ranges / stress levels (scoped to a questionnaire)
  // ---------------------------------------------------------------------

  Future<ScoreBand> adminAddScoreBand(
    String token,
    int questionnaireId, {
    required String code,
    required String label,
    required int minScore,
    required int maxScore,
  }) async {
    final body = await _sendJson(
      'POST',
      Uri.parse(
        '${ApiConfig.baseUrl}/v1/admin/questionnaires/$questionnaireId/score-bands',
      ),
      token,
      {
        'code': code,
        'label': label,
        'min_score': minScore,
        'max_score': maxScore,
      },
    );
    return ScoreBand.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<ScoreBand> adminUpdateScoreBand(
    String token,
    int questionnaireId,
    int bandId, {
    required String code,
    required String label,
    required int minScore,
    required int maxScore,
    required bool isActive,
  }) async {
    final body = await _sendJson(
      'PUT',
      Uri.parse(
        '${ApiConfig.baseUrl}/v1/admin/questionnaires/$questionnaireId/score-bands/$bandId',
      ),
      token,
      {
        'code': code,
        'label': label,
        'min_score': minScore,
        'max_score': maxScore,
        'is_active': isActive,
      },
    );
    return ScoreBand.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<String> adminDeleteScoreBand(
    String token,
    int questionnaireId,
    int bandId,
  ) async {
    final body = await _sendJson(
      'DELETE',
      Uri.parse(
        '${ApiConfig.baseUrl}/v1/admin/questionnaires/$questionnaireId/score-bands/$bandId',
      ),
      token,
      null,
    );
    return body['message'] as String? ?? 'Score range removed.';
  }

  // ---------------------------------------------------------------------
  // Admin: questions (scoped to a questionnaire)
  // ---------------------------------------------------------------------

  Future<StressQuestion> adminAddQuestion(
    String token,
    int questionnaireId, {
    required String questionText,
    String? dimension,
    required String questionType,
    required bool isRequired,
    required List<Map<String, dynamic>> options,
  }) async {
    final body = await _sendJson(
      'POST',
      Uri.parse(
        '${ApiConfig.baseUrl}/v1/admin/questionnaires/$questionnaireId/questions',
      ),
      token,
      {
        'question_text': questionText,
        'dimension': dimension,
        'question_type': questionType,
        'is_required': isRequired,
        'options': options,
      },
    );
    return StressQuestion.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<StressQuestion> adminUpdateQuestion(
    String token,
    int questionnaireId,
    int questionId, {
    required String questionText,
    String? dimension,
    required String questionType,
    required bool isRequired,
    required List<Map<String, dynamic>> options,
  }) async {
    final body = await _sendJson(
      'PUT',
      Uri.parse(
        '${ApiConfig.baseUrl}/v1/admin/questionnaires/$questionnaireId/questions/$questionId',
      ),
      token,
      {
        'question_text': questionText,
        'dimension': dimension,
        'question_type': questionType,
        'is_required': isRequired,
        'options': options,
      },
    );
    return StressQuestion.fromJson(body['data'] as Map<String, dynamic>);
  }

  /// Returns the backend's confirmation message, which may indicate the
  /// question was deactivated instead of deleted (it has response history).
  Future<String> adminDeleteQuestion(
    String token,
    int questionnaireId,
    int questionId,
  ) async {
    final body = await _sendJson(
      'DELETE',
      Uri.parse(
        '${ApiConfig.baseUrl}/v1/admin/questionnaires/$questionnaireId/questions/$questionId',
      ),
      token,
      null,
    );
    return body['message'] as String? ?? 'Question removed.';
  }

  Future<List<StressQuestion>> adminReorderQuestions(
    String token,
    int questionnaireId,
    List<Map<String, int>> positions,
  ) async {
    final body = await _sendJson(
      'PATCH',
      Uri.parse(
        '${ApiConfig.baseUrl}/v1/admin/questionnaires/$questionnaireId/questions/reorder',
      ),
      token,
      {'questions': positions},
    );
    return (body['data'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(StressQuestion.fromJson)
        .toList();
  }

  void close() => _client.close();

  Future<Map<String, dynamic>> _postPublicJson(
    Uri uri,
    Map<String, dynamic> payload,
  ) async {
    final http.Response response;
    try {
      response = await _client
          .post(
            uri,
            headers: const {
              'Accept': 'application/json',
              'Content-Type': 'application/json',
            },
            body: jsonEncode(payload),
          )
          .timeout(_emailRequestTimeout);
    } on http.ClientException {
      throw const ApiException(
        'Unable to connect to SheZen. Please try again.',
      );
    } on TimeoutException {
      throw const ApiException(
        'The email service is taking longer than expected. Please try again.',
      );
    }

    return _handleResponse(response);
  }

  Future<Map<String, dynamic>> _getPublicJson(Uri uri) async {
    final http.Response response;
    try {
      response = await _client
          .get(uri, headers: const {'Accept': 'application/json'})
          .timeout(const Duration(seconds: 10));
    } on http.ClientException {
      throw const ApiException(
        'Unable to connect to SheZen. Check your connection and try again.',
      );
    } on TimeoutException {
      throw const ApiException(
        'SheZen is taking longer than expected. Please try again.',
      );
    }
    return _handleResponse(response);
  }

  // ---------------------------------------------------------------------
  // Internal helpers
  // ---------------------------------------------------------------------

  Future<Map<String, dynamic>> _getJson(Uri uri, String token) async {
    final http.Response response;
    try {
      response = await _client.get(uri, headers: _authorizedHeaders(token));
    } on http.ClientException {
      throw const ApiException(
        'Network error — check your connection and that the server is reachable.',
      );
    } on TimeoutException {
      throw const ApiException(
        'Network error — check your connection and that the server is reachable.',
      );
    }
    return _handleResponse(response);
  }

  Future<Map<String, dynamic>> _sendJson(
    String method,
    Uri uri,
    String token,
    Map<String, dynamic>? payload,
  ) async {
    final headers = {
      ..._authorizedHeaders(token),
      'Content-Type': 'application/json',
    };
    final body = payload == null ? null : jsonEncode(payload);

    final http.Response response;
    try {
      switch (method) {
        case 'POST':
          response = await _client.post(uri, headers: headers, body: body);
        case 'PUT':
          response = await _client.put(uri, headers: headers, body: body);
        case 'PATCH':
          response = await _client.patch(uri, headers: headers, body: body);
        case 'DELETE':
          response = await _client.delete(uri, headers: headers, body: body);
        default:
          throw ArgumentError('Unsupported method: $method');
      }
    } on http.ClientException {
      throw const ApiException(
        'Network error — check your connection and that the server is reachable.',
      );
    } on TimeoutException {
      throw const ApiException(
        'Network error — check your connection and that the server is reachable.',
      );
    }
    return _handleResponse(response);
  }

  Map<String, dynamic> _handleResponse(http.Response response) {
    final body = _decodeObject(response);

    if (response.statusCode >= 200 && response.statusCode < 300) {
      return body;
    }

    throw ApiException(
      _errorMessage(body, _fallbackMessageFor(response.statusCode)),
      statusCode: response.statusCode,
      fieldErrors: _fieldErrors(body),
    );
  }

  String _fallbackMessageFor(int statusCode) {
    return switch (statusCode) {
      401 => 'Your session has expired. Please sign in again.',
      403 => 'You do not have permission to do that.',
      404 => 'That item could not be found — it may have been removed.',
      409 =>
        'This could not be completed due to a conflict with existing data.',
      422 => 'Please correct the highlighted fields.',
      >= 500 => 'The server ran into a problem. Please try again shortly.',
      _ => 'Something went wrong (HTTP $statusCode).',
    };
  }

  Map<String, String> _authorizedHeaders(String token) => {
    'Accept': 'application/json',
    'Authorization': 'Bearer $token',
  };

  Map<String, dynamic> _decodeObject(http.Response response) {
    try {
      final body = jsonDecode(response.body);
      if (body is Map<String, dynamic>) {
        return body;
      }
    } on FormatException {
      // Converted to a stable API exception by the caller.
    }

    return const {};
  }

  String _errorMessage(Map<String, dynamic> body, String fallback) {
    final message = body['message'];
    if (message is String && message.isNotEmpty) {
      return message;
    }

    final errors = body['errors'];
    if (errors is Map<String, dynamic>) {
      final first = errors.values.whereType<List>().firstOrNull;
      if (first != null && first.isNotEmpty) {
        return first.first.toString();
      }
    }

    return fallback;
  }

  Map<String, List<String>>? _fieldErrors(Map<String, dynamic> body) {
    final errors = body['errors'];
    if (errors is! Map<String, dynamic>) return null;

    final result = <String, List<String>>{};
    for (final entry in errors.entries) {
      if (entry.value is List) {
        result[entry.key] = (entry.value as List)
            .map((e) => e.toString())
            .toList();
      }
    }
    return result.isEmpty ? null : result;
  }
}

class QuestionnairePage {
  const QuestionnairePage({
    required this.items,
    required this.currentPage,
    required this.lastPage,
    required this.total,
  });

  final List<Questionnaire> items;
  final int currentPage;
  final int lastPage;
  final int total;

  bool get hasMore => currentPage < lastPage;
}

class ApiException implements Exception {
  const ApiException(this.message, {this.statusCode, this.fieldErrors});

  final String message;
  final int? statusCode;
  final Map<String, List<String>>? fieldErrors;

  String? fieldError(String field) => fieldErrors?[field]?.firstOrNull;

  @override
  String toString() => message;
}

extension _FirstOrNull<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
