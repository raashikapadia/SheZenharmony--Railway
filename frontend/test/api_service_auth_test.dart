import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shezen_harmony/core/network/api_service.dart';

void main() {
  const demographics = <String, dynamic>{
    'date_of_birth': '2004-03-15',
    'year_of_study': 'Year 3',
    'gender': 'Woman',
    'country': 'Fiji',
    'employment_status': 'Student',
    'relationship_status': 'Single',
    'has_children': false,
    'living_situation': 'With family',
  };

  test('registration request and response match Laravel contract', () async {
    late Map<String, dynamic> requestBody;
    final api = ApiService(
      client: MockClient((request) async {
        expect(request.method, 'POST');
        expect(request.url.path, '/api/v1/auth/register');
        requestBody = jsonDecode(request.body) as Map<String, dynamic>;
        return http.Response(
          jsonEncode({
            'mfa': {
              'challenge_id': '11111111-1111-4111-8111-111111111111',
              'purpose': 'registration',
              'masked_email': 's*******@student.usp.ac.fj',
              'expires_in_seconds': 600,
              'resend_after_seconds': 60,
            },
          }),
          201,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    final challenge = await api.register(
      email: 's12345678@student.usp.ac.fj',
      password: 'safe-password',
      passwordConfirmation: 'safe-password',
      demographics: demographics,
      privacyConsent: true,
    );

    expect(requestBody['name'], isNull);
    expect(requestBody['privacy_consent'], isTrue);
    expect(requestBody['demographics'], demographics);
    expect(challenge.purpose, 'registration');
    expect(challenge.maskedEmail, 's*******@student.usp.ac.fj');
    expect(challenge.expiresInSeconds, 600);
  });

  test('duplicate registration exposes a friendly field error', () async {
    final api = ApiService(
      client: MockClient(
        (_) async => http.Response(
          jsonEncode({
            'message': 'Please correct the highlighted fields.',
            'errors': {
              'email': ['An account with this email already exists.'],
            },
          }),
          422,
          headers: {'content-type': 'application/json'},
        ),
      ),
    );

    await expectLater(
      api.register(
        email: 's12345678@student.usp.ac.fj',
        password: 'safe-password',
        passwordConfirmation: 'safe-password',
        demographics: demographics,
        privacyConsent: true,
      ),
      throwsA(
        isA<ApiException>().having(
          (error) => error.fieldError('email'),
          'email error',
          'An account with this email already exists.',
        ),
      ),
    );
  });

  test('login requires OTP before auth session can be parsed', () async {
    var calls = 0;
    final api = ApiService(
      client: MockClient((request) async {
        calls++;
        expect(request.url.path, switch (calls) {
          1 => '/api/v1/auth/login',
          2 => '/api/v1/auth/verify-otp',
          _ => '/api/v1/auth/me',
        });
        return http.Response(
          jsonEncode({
            if (calls == 1)
              'mfa': {
                'challenge_id': '22222222-2222-4222-8222-222222222222',
                'purpose': 'login',
                'masked_email': 's*******@student.usp.ac.fj',
                'expires_in_seconds': 600,
                'resend_after_seconds': 60,
              }
            else ...{
              if (calls == 2) 'token': 'login-token',
              'user': {
                'role': 'student',
                'shezen_id': 'SZ-TESTIDENTITY',
                'has_completed_required_assessment': true,
              },
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    final login = await api.login(
      email: 's12345678@student.usp.ac.fj',
      password: 'safe-password',
    );
    final verified = await api.verifyOtp(challengeId: login.id, code: '123456');
    final restored = await api.me(verified.token);

    expect(restored.token, 'login-token');
    expect(restored.hasCompletedRequiredAssessment, isTrue);
  });

  test('getProfile fetches the authenticated student\'s own profile', () async {
    final api = ApiService(
      client: MockClient((request) async {
        expect(request.method, 'GET');
        expect(request.url.path, '/api/v1/profile');
        expect(request.headers['Authorization'], 'Bearer a-token');
        return http.Response(
          jsonEncode({
            'data': {
              'shezen_id': 'SZ-TESTIDENTITY',
              'date_of_birth': '2004-03-15',
              'age': 22,
              'country': 'Fiji',
            },
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    final profile = await api.getProfile('a-token');

    expect(profile['shezen_id'], 'SZ-TESTIDENTITY');
    expect(profile['age'], 22);
  });

  test(
    'updateProfile sends only the given fields and returns the updated data',
    () async {
      late Map<String, dynamic> requestBody;
      final api = ApiService(
        client: MockClient((request) async {
          expect(request.method, 'PUT');
          expect(request.url.path, '/api/v1/profile');
          requestBody = jsonDecode(request.body) as Map<String, dynamic>;
          return http.Response(
            jsonEncode({
              'message': 'Your profile has been updated successfully.',
              'data': {'country': 'Samoa'},
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
      );

      final result = await api.updateProfile('a-token', {'country': 'Samoa'});

      expect(requestBody, {'country': 'Samoa'});
      expect(result['country'], 'Samoa');
    },
  );

  test('authentication connection failures become safe API errors', () async {
    final api = ApiService(
      client: MockClient((_) async => throw http.ClientException('refused')),
    );

    await expectLater(
      api.login(
        email: 's12345678@student.usp.ac.fj',
        password: 'safe-password',
      ),
      throwsA(
        isA<ApiException>().having(
          (error) => error.message,
          'safe message',
          'Unable to connect to SheZen. Please try again.',
        ),
      ),
    );
  });

  test('deleteAccount calls the authenticated deletion endpoint', () async {
    final api = ApiService(
      client: MockClient((request) async {
        expect(request.method, 'DELETE');
        expect(request.url.path, '/api/v1/auth/account');
        expect(request.headers['Authorization'], 'Bearer a-token');
        return http.Response(
          jsonEncode({'message': 'Account deleted successfully.'}),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );

    await api.deleteAccount('a-token');
  });
}
