import 'dart:async';

import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';

void main() {
  const stored = {
    'token': 'revoked-token',
    'userId': '1',
    'name': 'Cached Student',
    'email': 'student@example.test',
    'role': 'student',
  };

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

class _FakeStorage extends SecureTokenStorage {
  _FakeStorage(this.value);

  final Map<String, String>? value;
  bool wasCleared = false;

  @override
  Future<Map<String, String>?> read() async => value;

  @override
  Future<void> clear() async {
    wasCleared = true;
  }
}
