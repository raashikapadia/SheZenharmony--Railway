import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/core/theme/app_theme.dart';
import 'package:shezen_harmony/features/diary/data/diary.dart';
import 'package:shezen_harmony/features/diary/data/diary_lock.dart';
import 'package:shezen_harmony/features/diary/data/diary_storage.dart';
import 'package:shezen_harmony/features/diary/data/diary_tombstone.dart';
import 'package:shezen_harmony/features/diary/presentation/diary_library_screen.dart';

void main() {
  Future<void> pumpLibrary(WidgetTester tester, DiaryStorage storage) async {
    tester.view.physicalSize = const Size(430, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    // Signed out, so the screen stays on the device copy and never reaches for
    // the network — these tests are about the local diary, not syncing.
    await tester.pumpWidget(
      ChangeNotifierProvider<AuthProvider>(
        create: (_) => AuthProvider(),
        child: MaterialApp(
          theme: AppTheme.light,
          home: DiaryLibraryScreen(storage: storage),
        ),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('a student can create a diary, write a page, and save it', (
    tester,
  ) async {
    final storage = _MemoryDiaryStorage();
    await pumpLibrary(tester, storage);

    expect(find.text('Nothing written yet'), findsOneWidget);

    await tester.tap(find.widgetWithText(FloatingActionButton, 'New diary'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'Quiet thoughts');
    await tester.tap(find.text('Create diary'));
    await tester.pumpAndSettle();

    // Creating drops the student straight into the new, empty diary.
    expect(find.text('This diary is empty'), findsOneWidget);

    await tester.tap(find.widgetWithText(FloatingActionButton, 'New page'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField).first, 'First evening');
    await tester.enterText(
      find.byType(TextField).last,
      'Long day, but it passed.',
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('Save'));
    await tester.pumpAndSettle();

    expect(find.text('First evening'), findsOneWidget);
    expect(find.text('1 PAGE'), findsOneWidget);

    await tester.pageBack();
    await tester.pumpAndSettle();

    expect(find.text('Quiet thoughts'), findsOneWidget);
    expect(find.textContaining('1 page'), findsOneWidget);

    // The words reached storage, not just the screen.
    expect(storage.saved.single.pages.single.body, 'Long day, but it passed.');
  });

  testWidgets('existing diaries are listed with their page counts', (
    tester,
  ) async {
    final diary = Diary.create(title: 'Quiet thoughts')
        .withPage(DiaryPage.blank().copyWith(title: 'One'))
        .withPage(DiaryPage.blank().copyWith(title: 'Two'));
    await pumpLibrary(tester, _MemoryDiaryStorage([diary]));

    expect(find.text('Quiet thoughts'), findsOneWidget);
    expect(find.textContaining('2 pages'), findsOneWidget);
    expect(find.text('1 DIARY'), findsOneWidget);
  });

  testWidgets('the privacy promise is stated before anything is written', (
    tester,
  ) async {
    await pumpLibrary(tester, _MemoryDiaryStorage());

    expect(find.text('Yours, and kept for you'), findsOneWidget);
    // Both halves of the promise: unreadable in our records, and it comes back.
    expect(find.textContaining('stored scrambled'), findsOneWidget);
    expect(find.textContaining('staff or admin screen'), findsOneWidget);
    expect(find.textContaining('sign in again'), findsOneWidget);
  });

  testWidgets('the note no longer claims the diary stays on the phone', (
    tester,
  ) async {
    // The diary is synced now, so the old wording would be a false promise.
    await pumpLibrary(tester, _MemoryDiaryStorage());

    expect(find.textContaining('never sent to SheZen'), findsNothing);
    expect(find.textContaining('erased if you uninstall'), findsNothing);
  });

  testWidgets('a refused write reports the failure instead of pretending', (
    tester,
  ) async {
    await pumpLibrary(tester, _FailingDiaryStorage());

    await tester.tap(find.widgetWithText(FloatingActionButton, 'New diary'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'Quiet thoughts');
    await tester.tap(find.text('Create diary'));
    await tester.pumpAndSettle();

    expect(find.text('Could not save to this device.'), findsOneWidget);
    // The list still shows the truth: nothing was stored.
    expect(find.text('Nothing written yet'), findsOneWidget);
  });

  testWidgets('unreadable storage offers a retry rather than an empty diary', (
    tester,
  ) async {
    await pumpLibrary(tester, _UnreadableDiaryStorage());

    expect(find.text('Couldn\'t open your diary'), findsOneWidget);
    expect(find.text('Try again'), findsOneWidget);
    expect(find.text('Nothing written yet'), findsNothing);
  });
}

class _MemoryDiaryStorage extends DiaryStorage {
  _MemoryDiaryStorage([List<Diary> initial = const []]) : saved = [...initial];

  List<Diary> saved;
  List<DiaryTombstone> tombstones = [];

  /// The student's PIN, kept in memory so these tests never reach for the
  /// secure-storage plugin.
  DiaryLockState? lockState;

  @override
  Future<DiaryLockState?> readLock() async => lockState;

  @override
  Future<void> writeLock(DiaryLockState state) async => lockState = state;

  @override
  Future<List<DiaryTombstone>> readTombstones() async => tombstones;

  @override
  Future<void> writeTombstones(List<DiaryTombstone> next) async =>
      tombstones = [...next];

  @override
  Future<List<Diary>> readAll() async => saved;

  @override
  Future<void> writeAll(List<Diary> diaries) async => saved = [...diaries];
}

class _FailingDiaryStorage extends DiaryStorage {
  @override
  Future<List<Diary>> readAll() async => const [];

  @override
  Future<void> writeAll(List<Diary> diaries) async =>
      throw const DiaryStorageException('Could not save to this device.');

  // Answered in memory so the test never reaches the secure-storage plugin;
  // what is under test here is the refused write, not the PIN.
  @override
  Future<DiaryLockState?> readLock() async => null;

  @override
  Future<void> writeLock(DiaryLockState state) async {}
}

class _UnreadableDiaryStorage extends DiaryStorage {
  @override
  Future<List<Diary>> readAll() async =>
      throw const DiaryStorageException('The saved diary could not be read.');

  @override
  Future<void> writeAll(List<Diary> diaries) async {}
}
