import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';

/// Digits in a diary PIN. The pad, the dots and the validation all read this,
/// so a future 6-digit PIN only needs changing here.
const diaryPinLength = 4;

/// Rounds of SHA-256 applied when turning a PIN into its stored hash.
///
/// A four-digit PIN only has 10,000 values, so no iteration count makes the
/// hash genuinely brute-force resistant. This is cheap insurance rather than a
/// guarantee: it costs a student nothing perceptible and costs anyone grinding
/// the hash offline ten thousand times more work than a bare digest.
const _hashRounds = 20000;

/// The student's PIN, as stored so it can be recognised again.
///
/// There is one of these per student, not one per diary: a PIN is chosen the
/// first time anything is locked, and every diary locked afterwards opens with
/// that same PIN.
///
/// The PIN itself is never written down — only a salted hash of it — so
/// reading the stored diary back does not reveal the PIN that opens it.
///
/// What this protects against is someone picking up an unlocked phone and
/// opening the app. It is not protection against an attacker who can already
/// read the app's private storage: the pages themselves stay readable in
/// `flutter_secure_storage`, which is where the device's own encryption
/// applies.
class DiaryLock {
  const DiaryLock({required this.salt, required this.hash});

  /// Builds a fresh lock for [pin], generating a new random salt.
  factory DiaryLock.fromPin(String pin) {
    final salt = _newSalt();
    return DiaryLock(salt: salt, hash: _hash(pin, salt));
  }

  /// Random per-diary salt, so two students who pick the same PIN do not end
  /// up with the same stored hash.
  final String salt;
  final String hash;

  bool matches(String pin) => _equalsInConstantTime(_hash(pin, salt), hash);

  Map<String, dynamic> toJson() => {'salt': salt, 'hash': hash};

  /// Returns null for anything that is not a complete, usable lock.
  ///
  /// A half-written lock therefore leaves the diary open rather than sealing a
  /// student out of pages that cannot be recovered by any other route. That
  /// matches how the rest of this feature treats damaged records — losing a
  /// field is recoverable, losing the writing is not.
  static DiaryLock? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    final salt = json['salt'] as String?;
    final hash = json['hash'] as String?;
    if (salt == null || salt.isEmpty) return null;
    if (hash == null || hash.isEmpty) return null;
    return DiaryLock(salt: salt, hash: hash);
  }

  static String _newSalt() {
    final random = Random.secure();
    return base64Url.encode(List<int>.generate(16, (_) => random.nextInt(256)));
  }

  static String _hash(String pin, String salt) {
    var digest = sha256.convert(utf8.encode('$salt:$pin'));
    for (var round = 1; round < _hashRounds; round++) {
      digest = sha256.convert(digest.bytes);
    }
    return base64Url.encode(digest.bytes);
  }

  /// Compares without returning early on the first differing character.
  ///
  /// Timing an on-device string compare is not a realistic way in, but the
  /// constant-time version costs nothing and keeps the comparison from being
  /// the weak link if this ever moves somewhere it does matter.
  static bool _equalsInConstantTime(String a, String b) {
    if (a.length != b.length) return false;
    var difference = 0;
    for (var index = 0; index < a.length; index++) {
      difference |= a.codeUnitAt(index) ^ b.codeUnitAt(index);
    }
    return difference == 0;
  }
}

/// True when [pin] is the right shape to be accepted or stored.
bool isValidDiaryPin(String pin) =>
    pin.length == diaryPinLength && pin.codeUnits.every(_isDigit);

bool _isDigit(int codeUnit) => codeUnit >= 0x30 && codeUnit <= 0x39;

/// The student's PIN together with when they last changed it.
///
/// The timestamp is what lets two of a student's devices agree on which PIN is
/// current: newest wins, the same rule the diaries themselves follow. A
/// [DiaryLockState] with a null [lock] and a timestamp is the record of a PIN
/// having been removed — distinct from a device that simply has nothing to say
/// about the PIN, which stores nothing at all.
class DiaryLockState {
  const DiaryLockState({required this.lock, required this.updatedAt});

  final DiaryLock? lock;
  final DateTime updatedAt;

  bool get isCleared => lock == null;

  Map<String, dynamic> toJson() => {
    'salt': lock?.salt,
    'hash': lock?.hash,
    'cleared': isCleared,
    'updated_at': updatedAt.toUtc().toIso8601String(),
  };

  static DiaryLockState? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    final updatedAt = DateTime.tryParse(json['updated_at'] as String? ?? '');
    if (updatedAt == null) return null;

    if (json['cleared'] == true) {
      return DiaryLockState(lock: null, updatedAt: updatedAt);
    }

    final lock = DiaryLock.fromJson(json);
    if (lock == null) return null;
    return DiaryLockState(lock: lock, updatedAt: updatedAt);
  }
}
