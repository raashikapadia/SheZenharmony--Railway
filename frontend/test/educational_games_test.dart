import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/features/activities/presentation/games/body_signals_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/games/coping_match_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/games/myth_or_fact_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/games/wellbeing_wordsearch_screen.dart';

/// The educational games are built to teach rather than to score, so what
/// these tests guard is the teaching: a wrong answer must still leave the
/// player with the right one and the reason behind it.
void main() {
  group('Coping Match', () {
    testWidgets('a wrong answer still reveals the strategy that helps', (
      tester,
    ) async {
      await tester.pumpWidget(const MaterialApp(home: CopingMatchScreen()));

      expect(find.text('Question 1 of 6'), findsOneWidget);

      // The unhelpful option, chosen on purpose.
      await tester.tap(
        find.text('Stay up all night and try to finish everything at once'),
      );
      await tester.pumpAndSettle();

      expect(find.text('Worth knowing'), findsOneWidget);
      expect(
        find.textContaining('Starting with the smallest step is not avoidance'),
        findsOneWidget,
      );
      expect(find.text('0 right so far'), findsOneWidget);
    });

    testWidgets('a right answer is counted and moves the round on', (
      tester,
    ) async {
      await tester.pumpWidget(const MaterialApp(home: CopingMatchScreen()));

      await tester.tap(
        find.text(
          'Break each one into small steps and start with the smallest',
        ),
      );
      await tester.pumpAndSettle();

      expect(find.text('That\'s it'), findsOneWidget);
      expect(find.text('1 right so far'), findsOneWidget);

      // The explanation panel sits below the fold on the test surface.
      await tester.ensureVisible(find.text('Next'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Next'));
      await tester.pumpAndSettle();

      expect(find.text('Question 2 of 6'), findsOneWidget);
    });

    testWidgets('an answer cannot be changed once it is given', (tester) async {
      await tester.pumpWidget(const MaterialApp(home: CopingMatchScreen()));

      await tester.tap(
        find.text('Wait until you feel motivated enough to begin'),
      );
      await tester.pumpAndSettle();

      // Tapping the helpful option afterwards must not retroactively score it.
      // The explanation repeats that same text as its heading, so this takes
      // the option tile rather than the panel.
      await tester.tap(
        find
            .text('Break each one into small steps and start with the smallest')
            .first,
      );
      await tester.pumpAndSettle();

      expect(find.text('0 right so far'), findsOneWidget);
    });
  });

  group('Myth or Fact', () {
    testWidgets('calling a myth a fact is corrected with the reason', (
      tester,
    ) async {
      await tester.pumpWidget(const MaterialApp(home: MythOrFactScreen()));

      expect(find.text('Stress is always bad for you.'), findsOneWidget);

      await tester.tap(find.text('Fact'));
      await tester.pumpAndSettle();

      expect(find.text('That one is a myth.'), findsOneWidget);
      expect(
        find.textContaining('Short bursts of stress sharpen focus'),
        findsOneWidget,
      );
    });
  });

  group('Body Signals', () {
    testWidgets('tapping the wrong zone still names the right one', (
      tester,
    ) async {
      await tester.pumpWidget(const MaterialApp(home: BodySignalsScreen()));

      // The first signal belongs to the head; choose the legs instead. The
      // body map runs past the bottom of the test surface, so scroll first.
      await tester.ensureVisible(find.text('Legs'));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Legs'));
      await tester.pumpAndSettle();

      expect(find.text('Head.'), findsOneWidget);
      expect(find.text('Worth knowing'), findsOneWidget);
    });
  });

  group('Wellbeing Word Search', () {
    testWidgets('every word on the list is actually hidden in the grid', (
      tester,
    ) async {
      // The grid is laid out randomly, so build it repeatedly: a placement
      // that only usually works would strand a player on a word that is not
      // there.
      for (var round = 0; round < 12; round++) {
        await tester.pumpWidget(
          MaterialApp(
            key: ValueKey(round),
            home: const WellbeingWordSearchScreen(),
          ),
        );
        await tester.pumpAndSettle();

        final grid = _gridLetters(tester);
        expect(grid, hasLength(81));

        // Whatever six words this puzzle drew must all be findable.
        final listed = _listedWords(tester);
        expect(listed, hasLength(6));

        for (final word in listed) {
          expect(
            _containsWord(grid, word),
            isTrue,
            reason:
                '$word is on the list but is not in the grid (round $round)',
          );
        }
      }
    });

    testWidgets('a new puzzle asks for different words', (tester) async {
      final draws = <String>{};

      for (var round = 0; round < 8; round++) {
        await tester.pumpWidget(
          MaterialApp(
            key: ValueKey('draw-$round'),
            home: const WellbeingWordSearchScreen(),
          ),
        );
        await tester.pumpAndSettle();

        draws.add((_listedWords(tester)..sort()).join(','));
      }

      // Drawing six from a pool this size makes a repeat across eight rounds
      // vanishingly unlikely, so one single set means the draw is not random.
      expect(
        draws.length,
        greaterThan(1),
        reason: 'Every puzzle asked for the same six words.',
      );
    });

    testWidgets('the word list hints the length of each word it hides', (
      tester,
    ) async {
      await tester.pumpWidget(
        const MaterialApp(home: WellbeingWordSearchScreen()),
      );
      await tester.pumpAndSettle();

      for (final word in _listedWords(tester)) {
        expect(
          find.text('${word.length} letters'),
          findsWidgets,
          reason: 'No length hint shown for $word.',
        );
      }
    });

    testWidgets('finding a word reveals what it means', (tester) async {
      await tester.pumpWidget(
        const MaterialApp(home: WellbeingWordSearchScreen()),
      );
      await tester.pumpAndSettle();

      // Hidden until found, so the list is a hint and not an answer key.
      expect(find.textContaining('The gap between what happens'), findsNothing);
      expect(find.textContaining('Make the out-breath longer'), findsNothing);
      expect(find.text('Found 0 of 6'), findsOneWidget);
    });
  });
}

/// The words this puzzle is asking for, read off the list under the grid.
///
/// Grid cells hold a single letter and the headings contain spaces, so an
/// all-caps run of three or more letters is a word-list entry.
List<String> _listedWords(WidgetTester tester) => tester
    .widgetList<Text>(find.byType(Text))
    .map((text) => text.data ?? '')
    .where((data) => RegExp(r'^[A-Z]{3,}$').hasMatch(data))
    .toList();

/// The 81 grid letters in row order, read straight off the rendered cells.
List<String> _gridLetters(WidgetTester tester) => tester
    .widgetList<Text>(
      find.descendant(of: find.byType(GridView), matching: find.byType(Text)),
    )
    .map((text) => text.data ?? '')
    .toList();

/// Whether [word] runs in a straight line anywhere in the 9x9 [grid], in
/// either direction.
bool _containsWord(List<String> grid, String word) {
  const size = 9;
  const directions = <(int, int)>[
    (0, 1),
    (1, 0),
    (1, 1),
    (-1, 1),
    (0, -1),
    (-1, 0),
    (-1, -1),
    (1, -1),
  ];

  for (var row = 0; row < size; row++) {
    for (var column = 0; column < size; column++) {
      for (final (rowStep, columnStep) in directions) {
        final endRow = row + rowStep * (word.length - 1);
        final endColumn = column + columnStep * (word.length - 1);
        if (endRow < 0 ||
            endRow >= size ||
            endColumn < 0 ||
            endColumn >= size) {
          continue;
        }

        var matched = true;
        for (var i = 0; i < word.length; i++) {
          final cell =
              grid[(row + rowStep * i) * size + (column + columnStep * i)];
          if (cell != word[i]) {
            matched = false;
            break;
          }
        }
        if (matched) return true;
      }
    }
  }
  return false;
}
