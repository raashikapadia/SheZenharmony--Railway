import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';

void main() {
  const stored = {'token': 'revoked-token', 'role': 'student'};

  test('401 during restoration clears storage and signs out', () async {
    final storage = _FakeStorage(stored);
    final provider = AuthProvider(
      apiService: _FakeApiService(
        const ApiException('Expired', statusCode: 401),
      ),
      storage: storage,
    );

    await provider.restoreSession();

    expect(provider.status, AuthStatus.signedOut);
    expect(provider.session, isNull);
    expect(storage.wasCleared, isTrue);
  });

  test(
    'transient restoration failure never reports an authenticated session',
    () async {
      final storage = _FakeStorage(stored);
      final provider = AuthProvider(
        apiService: _FakeApiService(const ApiException('Network error')),
        storage: storage,
      );

      await provider.restoreSession();

      expect(provider.status, AuthStatus.signedOut);
      expect(provider.session, isNull);
      expect(storage.wasCleared, isFalse);
    },
  );

  test(
    'restoration timeout signs out and notifies without clearing a potentially valid token',
    () async {
      final storage = _FakeStorage(stored);
      final provider = AuthProvider(
        apiService: _HangingApiService(),
        storage: storage,
        restoreTimeout: const Duration(milliseconds: 10),
      );
      var notifications = 0;
      provider.addListener(() => notifications++);

      await provider.restoreSession();

      expect(provider.status, AuthStatus.signedOut);
      expect(provider.session, isNull);
      expect(storage.wasCleared, isFalse);
      expect(notifications, 1);
    },
  );

  test(
    'unexpected restoration failure clears malformed session and signs out',
    () async {
      final storage = _FakeStorage(stored);
      final provider = AuthProvider(
        apiService: _UnexpectedApiService(),
        storage: storage,
      );

      await provider.restoreSession();

      expect(provider.status, AuthStatus.signedOut);
      expect(provider.session, isNull);
      expect(storage.wasCleared, isTrue);
    },
  );

  test('missing stored token clears storage and signs out', () async {
    final storage = _FakeStorage({...stored}..remove('token'));
    final provider = AuthProvider(
      apiService: _HangingApiService(),
      storage: storage,
    );

    await provider.restoreSession();

    expect(provider.status, AuthStatus.signedOut);
    expect(storage.wasCleared, isTrue);
  });

  test(
    'successful restoration persists only token and role session fields',
    () async {
      final storage = _FakeStorage(stored);
      final provider = AuthProvider(
        apiService: _SuccessfulApiService(),
        storage: storage,
      );

      await provider.restoreSession();

      expect(provider.status, AuthStatus.signedIn);
      expect(storage.savedToken, 'revoked-token');
      expect(storage.savedRole, 'student');
    },
  );

  test(
    'successful registration authenticates into questionnaire gate',
    () async {
      final storage = _FakeStorage(null);
      final provider = AuthProvider(
        apiService: _RegistrationApiService(),
        storage: storage,
      );

      final success = await provider.register(
        email: 's12345678@student.usp.ac.fj',
        password: 'safe-password',
        passwordConfirmation: 'safe-password',
        demographics: const {
          'gender': 'Woman',
          'country': 'Fiji',
          'employment_status': 'Student',
          'relationship_status': 'Single',
          'has_children': false,
          'living_situation': 'With family',
        },
        privacyConsent: true,
      );

      expect(success, isTrue);
      expect(provider.status, AuthStatus.signedIn);
      expect(provider.hasCompletedRequiredAssessment, isFalse);
      expect(storage.savedToken, 'new-token');
      expect(storage.savedRole, 'student');
    },
  );
}

class _FakeApiService extends ApiService {
  _FakeApiService(this.error);

  final ApiException error;

  @override
  Future<AuthSession> me(String token) => Future.error(error);
}

class _HangingApiService extends ApiService {
  @override
  Future<AuthSession> me(String token) => Completer<AuthSession>().future;
}

class _UnexpectedApiService extends ApiService {
  @override
  Future<AuthSession> me(String token) =>
      Future.error(const FormatException('Malformed profile'));
}

class _SuccessfulApiService extends ApiService {
  @override
  Future<AuthSession> me(String token) async => AuthSession(
    token: token,
    role: 'student',
    shezenId: 'SZ-TESTIDENTITY',
    hasCompletedRequiredAssessment: false,
  );
}

class _RegistrationApiService extends ApiService {
  @override
  Future<AuthSession> register({
    required String email,
    required String password,
    required String passwordConfirmation,
    required Map<String, dynamic> demographics,
    required bool privacyConsent,
    String deviceName = 'SheZen mobile app',
  }) async => const AuthSession(
    token: 'new-token',
    role: 'student',
    shezenId: 'SZ-TESTIDENTITY',
    hasCompletedRequiredAssessment: false,
  );
}

class _FakeStorage extends SecureTokenStorage {
  _FakeStorage(this.value);

  final Map<String, String>? value;
  bool wasCleared = false;
  String? savedToken;
  String? savedRole;

  @override
  Future<Map<String, String>?> read() async => value;

  @override
  Future<void> clear() async {
    wasCleared = true;
  }

  @override
  Future<void> save({required String token, required String role}) async {
    savedToken = token;
    savedRole = role;
  }
}
