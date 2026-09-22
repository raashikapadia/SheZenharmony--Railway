import 'dart:async';
import 'dart:convert';

import 'package:http/http.dart' as http;

import '../../features/activities/data/support_content.dart';
import '../../features/assessment/data/assessment_detail.dart';
import '../../features/assessment/data/assessment_questionnaire.dart';
import '../../features/assessment/data/assessment_result.dart';
import '../../features/guidance/data/personal_guidance.dart';
import '../../features/activities/data/managed_quiz.dart';
import '../../features/activities/data/gratitude_entry.dart';
import '../../features/auth/data/auth_session.dart';
import '../../features/auth/data/auth_challenge.dart';
import '../config/api_config.dart';

class ApiService {
  ApiService({
    http.Client? client,
    this.requestTimeout = const Duration(seconds: 10),
  }) : _client = client ?? http.Client();

  static const _emailRequestTimeout = Duration(seconds: 30);

  final http.Client _client;
  final Duration requestTimeout;

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

  Future<void> requestPasswordReset(String email) async {
    await _postPublicJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/auth/forgot-password'),
      {'email': email},
    );
  }

  Future<void> verifyPasswordResetCode(String email, String code) async {
    await _postPublicJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/auth/verify-reset-code'),
      {'email': email, 'code': code},
    );
  }

  Future<void> resetPassword({
    required String email,
    required String code,
    required String password,
    required String passwordConfirmation,
  }) async {
    await _postPublicJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/auth/reset-password'),
      {
        'email': email,
        'code': code,
        'password': password,
        'password_confirmation': passwordConfirmation,
      },
    );
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

  /// Records agreement to the current consent wording for an existing
  /// account. New accounts consent inside [register] instead.
  Future<AuthSession> recordConsent(String token) async {
    final body = await _sendJson(
      'POST',
      Uri.parse('${ApiConfig.baseUrl}/v1/auth/consent'),
      token,
      const {'privacy_consent': true},
    );
    try {
      return AuthSession.fromJson({'token': token, 'user': body['user']});
    } on FormatException {
      throw const ApiException(
        'Backend returned an unexpected consent response.',
      );
    }
  }

  Future<void> logout(String token) async {
    await _sendJson(
      'POST',
      Uri.parse('${ApiConfig.baseUrl}/v1/auth/logout'),
      token,
      null,
    );
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
                'journaling,breathing,grounding,mindfulness,relaxation,activity,resource',
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

  /// The admin-published helplines shown in the Resource tab.
  Future<List<HelplineResource>> helplines() async {
    final body = await _getPublicJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/helplines'),
    );
    return (body['data'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(HelplineResource.fromJson)
        .toList();
  }

  Future<List<PositiveContent>> positiveEngagement() async {
    final uri = Uri.parse('${ApiConfig.baseUrl}/v1/interventions').replace(
      queryParameters: {'content_type': 'quiz,motivation,positive_engagement'},
    );
    final body = await _getPublicJson(uri);
    return (body['data'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(PositiveContent.fromJson)
        .toList();
  }

  Future<List<PositiveContent>> games() async {
    const builtInGames = [
      PositiveContent(
        title: 'Breathing Challenge',
        description: 'Follow a simple breathing rhythm and take a calm moment.',
        contentType: 'positive_engagement',
        instructions: '',
        externalUrl: '',
      ),
      PositiveContent(
        title: 'Gratitude Jar',
        description:
            'Write down something positive and add it to your gratitude jar.',
        contentType: 'positive_engagement',
        instructions: '',
        externalUrl: '',
      ),
      PositiveContent(
        title: 'Memory Spark',
        description:
            'Gently tap the sparks as they appear and practise noticing the moment.',
        contentType: 'positive_engagement',
        instructions: '',
        externalUrl: '',
      ),
      PositiveContent(
        title: 'Mindful Memory',
        description:
            'Match peaceful symbols and practise your memory mindfully.',
        contentType: 'positive_engagement',
        instructions: '',
        externalUrl: '',
      ),
    ];

    final uri = Uri.parse(
      '${ApiConfig.baseUrl}/v1/interventions',
    ).replace(queryParameters: {'content_type': 'positive_engagement'});
    final List<PositiveContent> games;
    try {
      final body = await _getPublicJson(uri);
      games = (body['data'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(PositiveContent.fromJson)
          .toList();
    } on ApiException {
      return [...builtInGames];
    }
    final existingTitles = games.map((game) => game.title).toSet();

    for (final game in builtInGames) {
      if (!existingTitles.contains(game.title)) games.add(game);
    }

    return games;
  }

  Future<void> recordGamePlay(String token, int gameId) async {
    await _sendJson(
      'POST',
      Uri.parse(
        '${ApiConfig.baseUrl}/v1/positive-engagement/games/$gameId/play',
      ),
      token,
      const {},
    );
  }

  Future<List<ManagedQuiz>> managedQuizzes() async {
    final body = await _getPublicJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/positive-engagement/quizzes'),
    );
    return (body['data'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(ManagedQuiz.fromJson)
        .toList();
  }

  Future<Map<String, dynamic>> completeManagedQuiz(
    String token,
    int quizId,
    Map<int, String> answers,
  ) async {
    final body = await _sendJson(
      'POST',
      Uri.parse(
        '${ApiConfig.baseUrl}/v1/positive-engagement/quizzes/$quizId/complete',
      ),
      token,
      {'answers': answers.map((key, value) => MapEntry(key.toString(), value))},
    );
    return body['data'] as Map<String, dynamic>? ?? const {};
  }

  Future<Map<String, dynamic>> answerManagedQuizQuestion(
    String token,
    int quizId,
    int questionId,
    String answer,
  ) async {
    final body = await _sendJson(
      'POST',
      Uri.parse(
        '${ApiConfig.baseUrl}/v1/positive-engagement/quizzes/$quizId/answer',
      ),
      token,
      {'question_id': questionId, 'answer': answer},
    );
    return body['data'] as Map<String, dynamic>? ?? const {};
  }

  Future<List<GratitudeEntry>> gratitudeEntries(String token) async {
    final body = await _getJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/positive-engagement/gratitude'),
      token,
    );
    return (body['data'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(GratitudeEntry.fromJson)
        .toList();
  }

  Future<GratitudeEntry> addGratitudeEntry(
    String token, {
    required String text,
    required String symbol,
  }) async {
    final body = await _sendJson(
      'POST',
      Uri.parse('${ApiConfig.baseUrl}/v1/positive-engagement/gratitude'),
      token,
      {'text': text, 'symbol': symbol},
    );
    return GratitudeEntry.fromJson(body['data'] as Map<String, dynamic>);
  }

  Future<void> deleteGratitudeEntry(String token, int id) async {
    await _sendJson(
      'DELETE',
      Uri.parse('${ApiConfig.baseUrl}/v1/positive-engagement/gratitude/$id'),
      token,
      null,
    );
  }

  /// Pushes the device's diary up and returns the merged result.
  ///
  /// Deliberately untyped: the diary models and the merge rules live in the
  /// diary feature, and this facade only carries the request, so the contract
  /// can change without touching shared network code.
  ///
  /// [lock] is the student's single diary PIN as this device knows it. Omitted
  /// when the device has none to offer, which the server reads as "no opinion"
  /// rather than "remove it".
  Future<({List<Map<String, dynamic>> diaries, Map<String, dynamic>? lock})>
  syncDiaries(
    String token,
    List<Map<String, dynamic>> diaries, {
    Map<String, dynamic>? lock,
  }) async {
    final body = await _sendJson(
      'POST',
      Uri.parse('${ApiConfig.baseUrl}/v1/diary/sync'),
      token,
      {'diaries': diaries, 'lock': ?lock},
    );
    return (
      diaries: (body['data'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .toList(),
      lock: body['lock'] as Map<String, dynamic>?,
    );
  }

  // ---------------------------------------------------------------------
  // Personal Guidance (student Home Page) — small, admin-authored moments
  // of encouragement. Separate from wellbeing activities and stress content.
  // ---------------------------------------------------------------------

  /// Today's guidance, or null when the admin has nothing published (the
  /// caller shows a gentle empty state rather than an error).
  Future<PersonalGuidance?> currentGuidance(String token) async {
    final body = await _getJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/personal-guidance/current'),
      token,
    );
    return _guidanceOrNull(body['data']);
  }

  Future<PersonalGuidance?> anotherGuidance(
    String token, {
    int? excludeId,
  }) async {
    final uri = Uri.parse('${ApiConfig.baseUrl}/v1/personal-guidance/another')
        .replace(
          queryParameters: {
            if (excludeId != null) 'exclude': excludeId.toString(),
          },
        );
    final body = await _getJson(uri, token);
    return _guidanceOrNull(body['data']);
  }

  /// The student's personalised toolkit, matched to their latest check-in by
  /// the admin's rules. Always returns something usable — the backend falls
  /// back to general guidance when nothing matches.
  Future<GuidanceToolkit> guidanceToolkit(String token) async {
    final body = await _getJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/personal-guidance/for-you'),
      token,
    );
    return GuidanceToolkit.fromJson(body);
  }

  Future<List<PersonalGuidance>> favouriteGuidance(String token) async {
    final body = await _getJson(
      Uri.parse('${ApiConfig.baseUrl}/v1/personal-guidance/favourites'),
      token,
    );
    return (body['data'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(PersonalGuidance.fromJson)
        .toList();
  }

  Future<void> setGuidanceFavourite(
    String token,
    int id, {
    required bool favourite,
  }) async {
    await _sendJson(
      favourite ? 'POST' : 'DELETE',
      Uri.parse('${ApiConfig.baseUrl}/v1/personal-guidance/$id/favourite'),
      token,
      null,
    );
  }

  PersonalGuidance? _guidanceOrNull(Object? data) =>
      data is Map<String, dynamic> ? PersonalGuidance.fromJson(data) : null;

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
      response = await _client
          .get(uri, headers: _authorizedHeaders(token))
          .timeout(requestTimeout);
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
          response = await _client
              .post(uri, headers: headers, body: body)
              .timeout(requestTimeout);
        case 'PUT':
          response = await _client
              .put(uri, headers: headers, body: body)
              .timeout(requestTimeout);
        case 'PATCH':
          response = await _client
              .patch(uri, headers: headers, body: body)
              .timeout(requestTimeout);
        case 'DELETE':
          response = await _client
              .delete(uri, headers: headers, body: body)
              .timeout(requestTimeout);
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
      if (response.statusCode != 204 && !_hasJsonObjectBody(response)) {
        throw const ApiException(
          'Backend returned an unexpected response. Please try again.',
        );
      }
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
      423 => 'Your SheZen Harmony account is currently on hold.',
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

  bool _hasJsonObjectBody(http.Response response) {
    try {
      return jsonDecode(response.body) is Map<String, dynamic>;
    } on FormatException {
      return false;
    }
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
