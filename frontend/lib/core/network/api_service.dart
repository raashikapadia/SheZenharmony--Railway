import 'dart:convert';

import 'package:http/http.dart' as http;

import '../../features/auth/data/auth_session.dart';
import '../config/api_config.dart';

class ApiService {
  ApiService({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;

  Future<AuthSession> login({
    required String email,
    required String password,
    String deviceName = 'SheZen mobile app',
  }) async {
    final response = await _client.post(
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
    );

    final body = _decodeObject(response);
    if (response.statusCode != 200) {
      throw ApiException(_errorMessage(body, 'Sign in failed.'));
    }

    try {
      return AuthSession.fromJson(body);
    } on FormatException {
      throw ApiException('Backend returned an unexpected sign-in response.');
    }
  }

  Future<void> logout(String token) async {
    final response = await _client.post(
      Uri.parse('${ApiConfig.baseUrl}/v1/auth/logout'),
      headers: _authorizedHeaders(token),
    );

    if (response.statusCode != 200) {
      throw ApiException('Could not sign out (${response.statusCode}).');
    }
  }

  Future<Map<String, dynamic>> health() async {
    final uri = Uri.parse('${ApiConfig.baseUrl}/health');

    final response = await _client
        .get(
          uri,
          headers: const {
            'Accept': 'application/json',
          },
        )
        .timeout(const Duration(seconds: 8));

    final Object? body = jsonDecode(response.body);

    if (response.statusCode < 200 || response.statusCode >= 300) {
      throw ApiException(
        'Backend returned HTTP ${response.statusCode}.',
      );
    }

    if (body is! Map<String, dynamic>) {
      throw ApiException('Backend returned an unexpected response.');
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
      throw ApiException('Could not load questions (${response.statusCode}).');
    }

    final body = jsonDecode(response.body);
    if (body is Map<String, dynamic> && body['data'] is List) {
      return body['data'] as List<dynamic>;
    }

    throw ApiException('Unexpected question response.');
  }

  void close() => _client.close();

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
    final errors = body['errors'];
    if (errors is Map<String, dynamic>) {
      final emailErrors = errors['email'];
      if (emailErrors is List && emailErrors.isNotEmpty) {
        return emailErrors.first.toString();
      }
    }

    return fallback;
  }
}

class ApiException implements Exception {
  const ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}
