import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/features/diary/data/diary.dart';
import 'package:shezen_harmony/features/diary/data/diary_storage.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  group('Diary serialisation', () {
    test('a diary survives a round trip through JSON', () {
      final original = Diary.create(title: 'Quiet thoughts', coverIndex: 3)
          .withPage(
            DiaryPage.blank().copyWith(
              title: 'First evening',
              body: 'Long day, but it passed.',
            ),
          );

      final restored = Diary.fromJson(original.toJson());

      expect(restored.id, original.id);
      expect(restored.title, 'Quiet thoughts');
      expect(restored.coverIndex, 3);
      expect(restored.pages, hasLength(1));
      expect(restored.pages.single.title, 'First evening');
      expect(restored.pages.single.body, 'Long day, but it passed.');
    });

    test('a half-written page keeps whatever fields it still has', () {
      final page = DiaryPage.fromJson({'body': 'no title, no dates'});

      expect(page.body, 'no title, no dates');
      expect(page.title, isEmpty);
      expect(page.id, isNotEmpty);
    });
  });

  group('DiaryStorage', () {
    test('reads back what it wrote, most recently updated first', () async {
      FlutterSecureStorage.setMockInitialValues({});
      final storage = DiaryStorage();

      final older = Diary.create(
        title: 'Older',
      ).copyWith(updatedAt: DateTime(2026, 1, 1));
      final newer = Diary.create(
        title: 'Newer',
      ).copyWith(updatedAt: DateTime(2026, 6, 1));
      await storage.writeAll([older, newer]);

      final restored = await storage.readAll();

      expect(restored.map((diary) => diary.title), ['Newer', 'Older']);
    });

    test('an untouched device has no diaries', () async {
      FlutterSecureStorage.setMockInitialValues({});

      expect(await DiaryStorage().readAll(), isEmpty);
    });

    test('unreadable data raises rather than reporting an empty diary', () {
      // Reporting "no diaries" here would let the screen offer a fresh start
      // and overwrite whatever is actually stored.
      FlutterSecureStorage.setMockInitialValues({
        'shezen_diaries_v1': 'not json at all',
      });

      expect(DiaryStorage().readAll(), throwsA(isA<DiaryStorageException>()));
    });
  });
}
