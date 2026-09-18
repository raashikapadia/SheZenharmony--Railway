import 'dart:convert';

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/presentation/login_screen.dart';

void main() {
  test(
    'password reset API sends email, code, and confirmed password',
    () async {
      final paths = <String>[];
      final bodies = <Map<String, dynamic>>[];
      final api = ApiService(
        client: MockClient((request) async {
          paths.add(request.url.path);
          bodies.add(jsonDecode(request.body) as Map<String, dynamic>);
          return http.Response(jsonEncode({'message': 'OK'}), 200);
        }),
      );

      await api.requestPasswordReset('student@student.usp.ac.fj');
      await api.verifyPasswordResetCode('student@student.usp.ac.fj', '123456');
      await api.resetPassword(
        email: 'student@student.usp.ac.fj',
        code: '123456',
        password: 'new-password',
        passwordConfirmation: 'new-password',
      );

      expect(paths, [
        '/api/v1/auth/forgot-password',
        '/api/v1/auth/verify-reset-code',
        '/api/v1/auth/reset-password',
      ]);
      expect(bodies, [
        {'email': 'student@student.usp.ac.fj'},
        {'email': 'student@student.usp.ac.fj', 'code': '123456'},
        {
          'email': 'student@student.usp.ac.fj',
          'code': '123456',
          'password': 'new-password',
          'password_confirmation': 'new-password',
        },
      ]);
      api.close();
    },
  );

  testWidgets('student completes reset and returns to login with success', (
    tester,
  ) async {
    final api = _ResetApiService();
    final provider = AuthProvider(apiService: api);
    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: provider,
        child: const MaterialApp(home: LoginScreen()),
      ),
    );

    await tester.tap(find.text('Forgot Password?'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('reset-email-field')),
      'student@student.usp.ac.fj',
    );
    await tester.tap(find.text('Send verification code'));
    await tester.pumpAndSettle();
    expect(api.email, 'student@student.usp.ac.fj');

    await tester.enterText(find.byKey(const Key('reset-code-field')), '123456');
    await tester.tap(find.text('Verify code'));
    await tester.pumpAndSettle();
    expect(api.code, '123456');

    await tester.enterText(
      find.byKey(const Key('reset-password-field')),
      'short',
    );
    await tester.enterText(
      find.byKey(const Key('reset-confirm-field')),
      'short',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Reset password'));
    await tester.pump();
    expect(find.text('Use at least 8 characters.'), findsOneWidget);
    expect(api.resetCalls, 0);

    await tester.enterText(
      find.byKey(const Key('reset-password-field')),
      'new-password',
    );
    await tester.enterText(
      find.byKey(const Key('reset-confirm-field')),
      'different',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Reset password'));
    await tester.pump();
    expect(find.text('Passwords do not match.'), findsOneWidget);
    expect(api.resetCalls, 0);

    await tester.enterText(
      find.byKey(const Key('reset-confirm-field')),
      'new-password',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Reset password'));
    await tester.pumpAndSettle();
    expect(api.resetCalls, 1);
    expect(find.text('Welcome'), findsOneWidget);
    expect(find.textContaining('Password reset successfully'), findsOneWidget);
    expect(provider.status, isNot(AuthStatus.signedIn));
  });

  testWidgets('expired code at password step offers a new code', (
    tester,
  ) async {
    final api = _ResetApiService()..expireOnReset = true;
    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => AuthProvider(apiService: api),
        child: const MaterialApp(home: LoginScreen()),
      ),
    );
    await tester.tap(find.text('Forgot Password?'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('reset-email-field')),
      'student@student.usp.ac.fj',
    );
    await tester.tap(find.text('Send verification code'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byKey(const Key('reset-code-field')), '123456');
    await tester.tap(find.text('Verify code'));
    await tester.pumpAndSettle();
    await tester.enterText(
      find.byKey(const Key('reset-password-field')),
      'new-password',
    );
    await tester.enterText(
      find.byKey(const Key('reset-confirm-field')),
      'new-password',
    );
    await tester.tap(find.widgetWithText(FilledButton, 'Reset password'));
    await tester.pumpAndSettle();

    expect(find.textContaining('expired'), findsOneWidget);
    await tester.ensureVisible(find.text('Request a new code'));
    await tester.tap(find.text('Request a new code'));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('reset-code-field')), findsOneWidget);
  });
}

class _ResetApiService extends ApiService {
  String? email;
  String? code;
  int resetCalls = 0;
  bool expireOnReset = false;

  @override
  Future<void> requestPasswordReset(String email) async => this.email = email;

  @override
  Future<void> verifyPasswordResetCode(String email, String code) async {
    this.code = code;
  }

  @override
  Future<void> resetPassword({
    required String email,
    required String code,
    required String password,
    required String passwordConfirmation,
  }) async {
    resetCalls++;
    if (expireOnReset) {
      throw const ApiException(
        'Please correct the highlighted fields.',
        statusCode: 422,
        fieldErrors: {
          'code': ['This verification code has expired. Request a new code.'],
        },
      );
    }
  }
}
