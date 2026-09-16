import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/features/diary/data/diary.dart';
import 'package:shezen_harmony/features/diary/data/diary_lock.dart';

void main() {
  group('DiaryLock', () {
    test('recognises the PIN it was built from', () {
      final lock = DiaryLock.fromPin('2468');

      expect(lock.matches('2468'), isTrue);
    });

    test('rejects a PIN that is merely close', () {
      final lock = DiaryLock.fromPin('2468');

      expect(lock.matches('2469'), isFalse);
      expect(lock.matches('8642'), isFalse);
      expect(lock.matches('246'), isFalse);
      expect(lock.matches(''), isFalse);
    });

    test('never writes the PIN down', () {
      final lock = DiaryLock.fromPin('2468');
      final stored = lock.toJson().values.join(' ');

      expect(stored, isNot(contains('2468')));
    });

    test('two diaries with the same PIN store different hashes', () {
      // Without a per-diary salt, matching hashes would tell anyone reading
      // the stored file that two diaries share a PIN.
      final first = DiaryLock.fromPin('2468');
      final second = DiaryLock.fromPin('2468');

      expect(first.salt, isNot(second.salt));
      expect(first.hash, isNot(second.hash));
      expect(second.matches('2468'), isTrue);
    });

    test('survives a round trip through JSON', () {
      final lock = DiaryLock.fromPin('1357');

      final restored = DiaryLock.fromJson(lock.toJson());

      expect(restored, isNotNull);
      expect(restored!.matches('1357'), isTrue);
      expect(restored.matches('7531'), isFalse);
    });

    test('a half-written lock leaves the diary open rather than sealed', () {
      // A diary cannot be recovered from anywhere else, so damaged lock data
      // must not be the thing that locks a student out permanently.
      expect(DiaryLock.fromJson(null), isNull);
      expect(DiaryLock.fromJson('nonsense'), isNull);
      expect(DiaryLock.fromJson({'salt': 'abc'}), isNull);
      expect(DiaryLock.fromJson({'hash': 'abc'}), isNull);
      expect(DiaryLock.fromJson({'salt': '', 'hash': ''}), isNull);
    });
  });

  group('isValidDiaryPin', () {
    test('accepts exactly $diaryPinLength digits', () {
      expect(isValidDiaryPin('1234'), isTrue);
      expect(isValidDiaryPin('0000'), isTrue);
    });

    test('rejects the wrong length or anything that is not a digit', () {
      expect(isValidDiaryPin('123'), isFalse);
      expect(isValidDiaryPin('12345'), isFalse);
      expect(isValidDiaryPin('12a4'), isFalse);
      expect(isValidDiaryPin(' 123'), isFalse);
      expect(isValidDiaryPin(''), isFalse);
    });
  });

  group('DiaryLockState', () {
    test('survives a round trip through JSON', () {
      final state = DiaryLockState(
        lock: DiaryLock.fromPin('2468'),
        updatedAt: DateTime.utc(2026, 9, 10),
      );

      final restored = DiaryLockState.fromJson(state.toJson());

      expect(restored, isNotNull);
      expect(restored!.lock!.matches('2468'), isTrue);
      expect(restored.updatedAt, DateTime.utc(2026, 9, 10));
      expect(restored.isCleared, isFalse);
    });

    test('a removed PIN is recorded, not just left blank', () {
      // A device that has forgotten the PIN and a device recording that the
      // student removed it are different things, and only the second should
      // take a PIN away from their other devices.
      final cleared = DiaryLockState(
        lock: null,
        updatedAt: DateTime.utc(2026, 9, 10),
      );

      final restored = DiaryLockState.fromJson(cleared.toJson());

      expect(restored, isNotNull);
      expect(restored!.isCleared, isTrue);
      expect(restored.lock, isNull);
      expect(restored.updatedAt, DateTime.utc(2026, 9, 10));
    });

    test('a damaged record reads as nothing rather than a broken PIN', () {
      expect(DiaryLockState.fromJson(null), isNull);
      expect(DiaryLockState.fromJson('nonsense'), isNull);
      expect(DiaryLockState.fromJson({'salt': 'a', 'hash': 'b'}), isNull);
      expect(DiaryLockState.fromJson({'updated_at': 'not a date'}), isNull);
    });
  });

  group('a locked diary', () {
    test('keeps the fact it is locked through a round trip through JSON', () {
      final diary = Diary.create(title: 'Quiet thoughts', isLocked: true);

      final restored = Diary.fromJson(diary.toJson());

      expect(restored.isLocked, isTrue);
    });

    test('the diary records that it is locked, never which PIN opens it', () {
      // The PIN is the student's, not the diary's, so nothing about it should
      // reach a diary's stored form.
      final stored = Diary.create(
        title: 'Quiet thoughts',
        isLocked: true,
      ).toJson();

      expect(stored.keys, isNot(contains('lock')));
      expect(stored['is_locked'], isTrue);
    });

    test('a diary stored before PINs existed reads back unlocked', () {
      final restored = Diary.fromJson({
        'id': 'abc',
        'title': 'Quiet thoughts',
        'pages': <Map<String, dynamic>>[],
      });

      expect(restored.isLocked, isFalse);
      expect(restored.title, 'Quiet thoughts');
    });

    test('copyWith edits the diary without unlocking it', () {
      final diary = Diary.create(title: 'Quiet thoughts', isLocked: true);

      final renamed = diary.copyWith(title: 'Evenings');

      expect(renamed.title, 'Evenings');
      expect(renamed.isLocked, isTrue);
    });

    test('locked and unlocked flip only that diary', () {
      final diary = Diary.create(title: 'Quiet thoughts');

      expect(diary.locked().isLocked, isTrue);
      expect(diary.locked().unlocked().isLocked, isFalse);
    });

    test('locking keeps the pages that were already written', () {
      final diary = Diary.create(
        title: 'Quiet thoughts',
      ).withPage(DiaryPage.blank().copyWith(title: 'First evening'));

      final locked = diary.locked();

      expect(locked.pages, hasLength(1));
      expect(locked.pages.single.title, 'First evening');
    });
  });
}
