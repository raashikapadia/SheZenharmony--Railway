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
    tester.view.physicalSize = const Size(430, 1200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    // Signed out, so the screen stays on the device copy and never reaches for
    // the network — these tests are about the PIN, not syncing.
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

  /// Taps the pad keys for [pin], the way a student enters it.
  Future<void> enterPin(WidgetTester tester, String pin) async {
    for (final digit in pin.split('')) {
      await tester.tap(find.widgetWithText(InkWell, digit));
      await tester.pumpAndSettle();
    }
  }

  Future<void> openDiaryMenu(WidgetTester tester) async {
    await tester.tap(find.byTooltip('Diary options'));
    await tester.pumpAndSettle();
  }

  testWidgets('a student can lock a new diary with a PIN as they create it', (
    tester,
  ) async {
    final storage = _MemoryDiaryStorage();
    await pumpLibrary(tester, storage);

    await tester.tap(find.widgetWithText(FloatingActionButton, 'New diary'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'Quiet thoughts');
    await tester.tap(find.byType(Switch));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Create diary'));
    await tester.pumpAndSettle();

    // Chosen once, then confirmed.
    expect(find.text('Choose your PIN'), findsOneWidget);
    await enterPin(tester, '2468');
    expect(find.text('Type it once more'), findsOneWidget);
    await enterPin(tester, '2468');
    await tester.pumpAndSettle();

    // Straight into the diary — they just proved the PIN by typing it twice.
    expect(find.text('This diary is empty'), findsOneWidget);

    expect(storage.saved.single.isLocked, isTrue);
    expect(storage.lockState!.lock!.matches('2468'), isTrue);
  });

  testWidgets('a mistyped confirmation starts the PIN over instead of saving', (
    tester,
  ) async {
    final storage = _MemoryDiaryStorage();
    await pumpLibrary(tester, storage);

    await tester.tap(find.widgetWithText(FloatingActionButton, 'New diary'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'Quiet thoughts');
    await tester.tap(find.byType(Switch));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Create diary'));
    await tester.pumpAndSettle();

    await enterPin(tester, '2468');
    await enterPin(tester, '1357');
    await tester.pumpAndSettle();

    expect(find.textContaining('did not match'), findsOneWidget);
    // Nothing was created from a PIN the student could not repeat.
    expect(storage.saved, isEmpty);
  });

  testWidgets('backing out of the PIN abandons the diary entirely', (
    tester,
  ) async {
    final storage = _MemoryDiaryStorage();
    await pumpLibrary(tester, storage);

    await tester.tap(find.widgetWithText(FloatingActionButton, 'New diary'));
    await tester.pumpAndSettle();
    await tester.enterText(find.byType(TextField), 'Quiet thoughts');
    await tester.tap(find.byType(Switch));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Create diary'));
    await tester.pumpAndSettle();

    expect(find.text('Choose your PIN'), findsOneWidget);
    // Dismiss the sheet without choosing anything.
    await tester.tapAt(const Offset(10, 10));
    await tester.pumpAndSettle();

    // No half-made, accidentally unlocked diary left behind.
    expect(storage.saved, isEmpty);
    expect(find.text('Nothing written yet'), findsOneWidget);
  });

  testWidgets('a locked diary asks for its PIN before it opens', (
    tester,
  ) async {
    final diary = Diary.create(
      title: 'Quiet thoughts',
      isLocked: true,
    ).withPage(DiaryPage.blank().copyWith(title: 'First evening'));
    await pumpLibrary(tester, _MemoryDiaryStorage([diary], '2468'));

    await tester.tap(find.text('Quiet thoughts'));
    await tester.pumpAndSettle();

    // The pages stay out of sight until the PIN is right.
    expect(find.text('First evening'), findsNothing);
    expect(find.textContaining('PIN to open it'), findsOneWidget);

    await enterPin(tester, '2468');
    await tester.pumpAndSettle();

    expect(find.text('First evening'), findsOneWidget);
  });

  testWidgets('a wrong PIN keeps the diary shut and says so', (tester) async {
    final diary = Diary.create(
      title: 'Quiet thoughts',
      isLocked: true,
    ).withPage(DiaryPage.blank().copyWith(title: 'First evening'));
    await pumpLibrary(tester, _MemoryDiaryStorage([diary], '2468'));

    await tester.tap(find.text('Quiet thoughts'));
    await tester.pumpAndSettle();
    await enterPin(tester, '1357');
    await tester.pumpAndSettle();

    expect(find.textContaining('did not open'), findsOneWidget);
    expect(find.text('First evening'), findsNothing);
  });

  testWidgets('the way out of a forgotten PIN appears only after real tries', (
    tester,
  ) async {
    final diary = Diary.create(title: 'Quiet thoughts', isLocked: true);
    await pumpLibrary(tester, _MemoryDiaryStorage([diary], '2468'));

    await tester.tap(find.text('Quiet thoughts'));
    await tester.pumpAndSettle();

    // Not offered to someone who has simply started typing.
    expect(find.text('Forgot your PIN?'), findsNothing);

    for (var attempt = 0; attempt < 3; attempt++) {
      await enterPin(tester, '1357');
      await tester.pumpAndSettle();
    }

    expect(find.text('Forgot your PIN?'), findsOneWidget);
  });

  testWidgets('deleting a diary behind a forgotten PIN is confirmed first', (
    tester,
  ) async {
    final storage = _MemoryDiaryStorage([
      Diary.create(
        title: 'Quiet thoughts',
        isLocked: true,
      ).withPage(DiaryPage.blank().copyWith(title: 'First evening')),
    ], '2468');
    await pumpLibrary(tester, storage);

    await tester.tap(find.text('Quiet thoughts'));
    await tester.pumpAndSettle();
    for (var attempt = 0; attempt < 3; attempt++) {
      await enterPin(tester, '1357');
      await tester.pumpAndSettle();
    }

    await tester.tap(find.text('Forgot your PIN?'));
    await tester.pumpAndSettle();

    // The dialog is honest about why there is no reset.
    expect(find.textContaining('cannot be recovered'), findsOneWidget);

    // Backing out leaves the diary exactly where it was.
    await tester.tap(find.text('Keep it locked'));
    await tester.pumpAndSettle();
    expect(storage.saved, hasLength(1));

    await tester.tap(find.text('Forgot your PIN?'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Delete'));
    await tester.pumpAndSettle();

    expect(storage.saved, isEmpty);
    expect(find.text('Nothing written yet'), findsOneWidget);
  });

  testWidgets('a diary made without a PIN can be locked afterwards', (
    tester,
  ) async {
    final storage = _MemoryDiaryStorage([
      Diary.create(title: 'Quiet thoughts'),
    ]);
    await pumpLibrary(tester, storage);

    await tester.tap(find.text('Quiet thoughts'));
    await tester.pumpAndSettle();

    await openDiaryMenu(tester);
    expect(find.text('Stop locking this diary'), findsNothing);
    await tester.tap(find.text('Lock with a PIN'));
    await tester.pumpAndSettle();

    await enterPin(tester, '2468');
    await enterPin(tester, '2468');
    await tester.pumpAndSettle();

    expect(storage.saved.single.isLocked, isTrue);
    expect(storage.lockState!.lock!.matches('2468'), isTrue);
  });

  testWidgets('a second diary is locked without asking for another PIN', (
    tester,
  ) async {
    // The point of one shared PIN: the student is never asked to invent or
    // remember a second one.
    final storage = _MemoryDiaryStorage([
      Diary.create(title: 'Already locked', isLocked: true),
      Diary.create(title: 'Not yet locked'),
    ], '2468');
    await pumpLibrary(tester, storage);

    await tester.tap(find.text('Not yet locked'));
    await tester.pumpAndSettle();

    await openDiaryMenu(tester);
    // Worded to say the PIN already exists.
    expect(find.text('Lock with your PIN'), findsOneWidget);
    await tester.tap(find.text('Lock with your PIN'));
    await tester.pumpAndSettle();

    // No PIN pad at all — it locked straight away.
    expect(find.text('Choose your PIN'), findsNothing);
    expect(find.text('Type it once more'), findsNothing);

    final locked = storage.saved.firstWhere(
      (diary) => diary.title == 'Not yet locked',
    );
    expect(locked.isLocked, isTrue);
    // And the PIN is untouched — still the one they already had.
    expect(storage.lockState!.lock!.matches('2468'), isTrue);
  });

  testWidgets('the same PIN opens every diary the student locked', (
    tester,
  ) async {
    final storage = _MemoryDiaryStorage([
      Diary.create(
        title: 'First one',
        isLocked: true,
      ).withPage(DiaryPage.blank().copyWith(title: 'One')),
      Diary.create(
        title: 'Second one',
        isLocked: true,
      ).withPage(DiaryPage.blank().copyWith(title: 'Two')),
    ], '2468');
    await pumpLibrary(tester, storage);

    await tester.tap(find.text('First one'));
    await tester.pumpAndSettle();
    await enterPin(tester, '2468');
    await tester.pumpAndSettle();
    expect(find.text('One'), findsOneWidget);

    await tester.pageBack();
    await tester.pumpAndSettle();

    await tester.tap(find.text('Second one'));
    await tester.pumpAndSettle();
    await enterPin(tester, '2468');
    await tester.pumpAndSettle();
    expect(find.text('Two'), findsOneWidget);
  });

  testWidgets('changing the PIN changes it for every locked diary', (
    tester,
  ) async {
    final storage = _MemoryDiaryStorage([
      Diary.create(title: 'Quiet thoughts', isLocked: true),
    ], '2468');
    await pumpLibrary(tester, storage);

    await tester.tap(find.text('Quiet thoughts'));
    await tester.pumpAndSettle();
    await enterPin(tester, '2468');
    await tester.pumpAndSettle();

    await openDiaryMenu(tester);
    await tester.tap(find.text('Change your PIN'));
    await tester.pumpAndSettle();

    await enterPin(tester, '1357');
    await enterPin(tester, '1357');
    await tester.pumpAndSettle();

    expect(storage.lockState!.lock!.matches('1357'), isTrue);
    expect(storage.lockState!.lock!.matches('2468'), isFalse);
  });

  testWidgets('unlocking one diary is confirmed and keeps the PIN', (
    tester,
  ) async {
    final storage = _MemoryDiaryStorage([
      Diary.create(
        title: 'Quiet thoughts',
        isLocked: true,
      ).withPage(DiaryPage.blank().copyWith(title: 'First evening')),
    ], '2468');
    await pumpLibrary(tester, storage);

    await tester.tap(find.text('Quiet thoughts'));
    await tester.pumpAndSettle();
    await enterPin(tester, '2468');
    await tester.pumpAndSettle();

    await openDiaryMenu(tester);
    await tester.tap(find.text('Stop locking this diary'));
    await tester.pumpAndSettle();
    await tester.tap(find.widgetWithText(FilledButton, 'Stop locking'));
    await tester.pumpAndSettle();

    final saved = storage.saved.single;
    expect(saved.isLocked, isFalse);
    expect(saved.pages.single.title, 'First evening');
    // The PIN itself survives — their other locked diaries still need it.
    expect(storage.lockState!.lock!.matches('2468'), isTrue);
  });

  testWidgets('the library marks which diaries are locked', (tester) async {
    await pumpLibrary(
      tester,
      _MemoryDiaryStorage([
        Diary.create(title: 'Locked one', isLocked: true),
        Diary.create(title: 'Open one'),
      ], '2468'),
    );

    // Only the locked one carries the badge, so the list tells a student
    // which diaries will ask for something before they tap.
    expect(
      find.byWidgetPredicate(
        (widget) =>
            widget is Icon && widget.semanticLabel == 'Locked with a PIN',
      ),
      findsOneWidget,
    );
  });
}

class _MemoryDiaryStorage extends DiaryStorage {
  _MemoryDiaryStorage([List<Diary> initial = const [], String? pin])
    : saved = [...initial],
      lockState = pin == null
          ? null
          : DiaryLockState(
              lock: DiaryLock.fromPin(pin),
              updatedAt: DateTime(2026, 9, 1),
            );

  DiaryLockState? lockState;

  @override
  Future<DiaryLockState?> readLock() async => lockState;

  @override
  Future<void> writeLock(DiaryLockState state) async => lockState = state;

  List<Diary> saved;
  List<DiaryTombstone> tombstones = [];

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
