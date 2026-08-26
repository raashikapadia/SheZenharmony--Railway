import 'package:flutter/foundation.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';

/// Persists the signed-in user's session across app restarts.
///
/// Every operation is defensive: flutter_secure_storage's web backend uses
/// the browser's Web Crypto API, which can throw (e.g. in a fresh/locked-down
/// browser profile) rather than just failing gracefully. A storage failure
/// should degrade to "not persisted" — the user simply has to sign in again —
/// never crash the app on startup.
class SecureTokenStorage {
  SecureTokenStorage({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _tokenKey = 'auth_token';
  static const _roleKey = 'auth_role';

  Future<void> save({required String token, required String role}) async {
    try {
      // Remove legacy cached PII/internal IDs before writing the minimal session.
      await Future.wait([
        _storage.delete(key: 'auth_user_id'),
        _storage.delete(key: 'auth_name'),
        _storage.delete(key: 'auth_email'),
      ]);
      await Future.wait([
        _storage.write(key: _tokenKey, value: token),
        _storage.write(key: _roleKey, value: role),
      ]);
    } catch (error) {
      debugPrint(
        'SecureTokenStorage.save failed, session will not persist across restarts: $error',
      );
    }
  }

  Future<Map<String, String>?> read() async {
    try {
      final values = await Future.wait([
        _storage.read(key: _tokenKey),
        _storage.read(key: _roleKey),
      ]);

      final token = values[0];
      final role = values[1];

      if (token == null || role == null) {
        return null;
      }

      return {'token': token, 'role': role};
    } catch (error) {
      debugPrint(
        'SecureTokenStorage.read failed, treating as signed out: $error',
      );
      return null;
    }
  }

  Future<void> clear() async {
    try {
      await _storage.deleteAll();
    } catch (error) {
      debugPrint('SecureTokenStorage.clear failed: $error');
    }
  }
}
