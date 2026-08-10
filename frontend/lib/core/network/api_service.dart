import 'dart:convert';

import 'package:http/http.dart' as http;

import '../config/api_config.dart';

class ApiService {
  ApiService({http.Client? client}) : _client = client ?? http.Client();

  final http.Client _client;

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
}

class ApiException implements Exception {
  const ApiException(this.message);

  final String message;

  @override
  String toString() => message;
}
