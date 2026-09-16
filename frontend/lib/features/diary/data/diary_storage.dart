import 'dart:convert';

import 'package:flutter_secure_storage/flutter_secure_storage.dart';

import 'diary.dart';
import 'diary_lock.dart';
import 'diary_tombstone.dart';

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

/// The diary's copy on this device: encrypted storage, and the only thing the
/// screens read from or write to directly.
///
/// Nothing here talks to the API. Keeping the device copy authoritative for the
/// UI is what lets a student write with no signal; [DiarySyncService] is what
/// later reconciles this copy with the stored one so the writing outlives the
/// phone it was written on.
class DiaryStorage {
  DiaryStorage({FlutterSecureStorage? storage})
    : _storage = storage ?? const FlutterSecureStorage();

  final FlutterSecureStorage _storage;

  static const _key = 'shezen_diaries_v1';
  static const _tombstonesKey = 'shezen_diary_tombstones_v1';
  static const _lockKey = 'shezen_diary_lock_v1';

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

  /// Deletions waiting to be told to the server.
  ///
  /// A damaged tombstone list is dropped rather than raised: the worst it costs
  /// is a deleted diary reappearing on one device, which the student can delete
  /// again. Refusing to open the diary over it would be the greater harm.
  Future<List<DiaryTombstone>> readTombstones() async {
    try {
      final raw = await _storage.read(key: _tombstonesKey);
      if (raw == null || raw.trim().isEmpty) return [];
      return (jsonDecode(raw) as List<dynamic>)
          .map(DiaryTombstone.fromJson)
          .whereType<DiaryTombstone>()
          .toList();
    } catch (error) {
      return [];
    }
  }

  Future<void> writeTombstones(List<DiaryTombstone> tombstones) async {
    try {
      await _storage.write(
        key: _tombstonesKey,
        value: jsonEncode(tombstones.map((entry) => entry.toJson()).toList()),
      );
    } catch (error) {
      throw const DiaryStorageException('Could not save to this device.');
    }
  }

  Future<void> addTombstone(DiaryTombstone tombstone) async {
    final existing = await readTombstones();
    await writeTombstones([...existing, tombstone]);
  }

  /// The student's single PIN, or null if they have never set one.
  ///
  /// Unreadable data is treated as "no PIN recorded" rather than raised. The
  /// diaries themselves are what matter; a lost PIN record means a locked diary
  /// opens without asking, which is recoverable, while refusing to open the
  /// diary at all is not.
  Future<DiaryLockState?> readLock() async {
    try {
      final raw = await _storage.read(key: _lockKey);
      if (raw == null || raw.trim().isEmpty) return null;
      return DiaryLockState.fromJson(
        jsonDecode(raw) as Map<String, dynamic>? ?? const {},
      );
    } catch (error) {
      return null;
    }
  }

  Future<void> writeLock(DiaryLockState state) async {
    try {
      await _storage.write(key: _lockKey, value: jsonEncode(state.toJson()));
    } catch (error) {
      throw const DiaryStorageException('Could not save to this device.');
    }
  }
}
