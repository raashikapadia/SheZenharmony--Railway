import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'diary.dart';

/// Raised when the diary cannot be read from or written to the device.
///
/// Unlike the session token, a diary cannot be recreated by signing in again,
/// so these failures are surfaced to the student rather than swallowed.
class DiaryStorageException implements Exception {
  const DiaryStorageException(this.message);

  final String message;

  @override
  String toString() => 'DiaryStorageException: $message';
}

/// The diary's only home: encrypted storage on this device.
///
/// Nothing here talks to the API. There is no diary table, no endpoint and no
/// admin screen, so a diary is unreadable by the SheZen team by construction
/// rather than by policy. The cost of that guarantee is that uninstalling the
/// app, clearing its data, or moving to a new phone loses the diary.
class DiaryStorage {
  DiaryStorage({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _key = 'shezen_diaries_v1';

  /// Diaries, most recently updated first.
  Future<List<Diary>> readAll() async {
    final String? raw;
    try {
      raw = await _storage.read(key: _key);
    } catch (error) {
      throw DiaryStorageException('Could not open the diary on this device.');
    }

    if (raw == null || raw.trim().isEmpty) return [];

    try {
      final decoded = jsonDecode(raw);
      final diaries = (decoded as List<dynamic>)
          .whereType<Map<String, dynamic>>()
          .map(Diary.fromJson)
          .toList();
      diaries.sort((a, b) => b.updatedAt.compareTo(a.updatedAt));
      return diaries;
    } catch (error) {
      // Refuse to report "no diaries" for data we simply failed to parse —
      // the screen would otherwise offer to start again and overwrite it.
      throw DiaryStorageException('The saved diary could not be read.');
    }
  }

  Future<void> writeAll(List<Diary> diaries) async {
    try {
      await _storage.write(
        key: _key,
        value: jsonEncode(diaries.map((diary) => diary.toJson()).toList()),
      );
    } catch (error) {
      throw const DiaryStorageException('Could not save to this device.');
    }
  }
}
