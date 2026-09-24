import 'dart:math';

import 'package:flutter/material.dart';

import '../../../../core/theme/app_theme.dart';

/// A word search built from the vocabulary students need in order to describe
/// what is happening to them and ask for the right thing.
///
/// A crossword needs a keyboard and a lot of screen; a word search gives the
/// same puzzle satisfaction with two taps and reads well on a phone. Finding
/// a word is not the lesson — the short definition it reveals is, which is why
/// every word carries one.
class WellbeingWordSearchScreen extends StatefulWidget {
  const WellbeingWordSearchScreen({super.key});

  @override
  State<WellbeingWordSearchScreen> createState() =>
      _WellbeingWordSearchScreenState();
}

class _WellbeingWordSearchScreenState extends State<WellbeingWordSearchScreen> {
  static const _size = 9;

  /// How many words a single puzzle asks for.
  static const _wordsPerPuzzle = 6;

  /// The vocabulary the game draws from. Every entry fits the grid, and each
  /// one is a thing a student can actually do rather than a mood they are
  /// told to have.
  ///
  /// A puzzle takes [_wordsPerPuzzle] of these at random, so coming back to
  /// the game teaches something new rather than rehearsing the same six. The
  /// pool is deliberately much larger than one puzzle needs.
  static const _pool = <_Target>[
    _Target(
      word: 'BREATHE',
      meaning:
          'Make the out-breath longer than the in-breath. Your heart rate '
          'follows within a minute.',
    ),
    _Target(
      word: 'PAUSE',
      meaning:
          'The gap between what happens and how you answer it. It is short, '
          'and it is where the choice lives.',
    ),
    _Target(
      word: 'GROUND',
      meaning:
          'Use your senses to come back to the room you are actually in, '
          'rather than the one in your head.',
    ),
    _Target(
      word: 'REST',
      meaning:
          'Not a reward for finishing the work. Part of how the work gets '
          'done at all.',
    ),
    _Target(
      word: 'SUPPORT',
      meaning:
          'Asking for it early costs far less than asking for it once things '
          'have already come apart.',
    ),
    _Target(
      word: 'BOUNDARY',
      meaning:
          'A limit you set so you can keep giving to the things that matter. '
          'It needs no justification.',
    ),
    _Target(
      word: 'ROUTINE',
      meaning:
          'A few fixed points in the day. They hold the week together when '
          'everything around them wobbles.',
    ),
    _Target(
      word: 'KINDNESS',
      meaning:
          'Speak to yourself the way you would speak to a friend in the same '
          'situation. You would not call them a failure.',
    ),
    _Target(
      word: 'SLEEP',
      meaning:
          'The one thing that changes how big every other problem feels '
          'tomorrow. Protect it before you protect anything else.',
    ),
    _Target(
      word: 'NOTICE',
      meaning:
          'Name what you are feeling before you argue with it. Putting it '
          'into words already lowers its volume.',
    ),
    _Target(
      word: 'REFRAME',
      meaning:
          'Look at the same fact from another angle. Not pretending it is '
          'fine — finding the reading that is equally true and less cruel.',
    ),
    _Target(
      word: 'JOURNAL',
      meaning:
          'Writing a worry down moves it out of the loop in your head and '
          'onto a page where you can answer it.',
    ),
    _Target(
      word: 'CONNECT',
      meaning:
          'Reaching one person beats managing alone. Isolation makes a hard '
          'week feel like a permanent state.',
    ),
    _Target(
      word: 'BALANCE',
      meaning:
          'Not splitting everything evenly. Making sure the week holds more '
          'than the thing that is stressing you.',
    ),
    _Target(
      word: 'PATIENCE',
      meaning:
          'Recovery is rarely a straight line. A bad day after a good one is '
          'the normal shape of it, not a relapse.',
    ),
    _Target(
      word: 'STRETCH',
      meaning:
          'Tension settles in the shoulders, jaw and back. Moving them is the '
          'fastest way to tell your body the emergency is over.',
    ),
    _Target(
      word: 'GRATITUDE',
      meaning:
          'Naming one specific good thing. It does not cancel the hard parts, '
          'it just stops them being the only thing you can see.',
    ),
    _Target(
      word: 'PRESENT',
      meaning:
          'Most anxiety lives in a future that has not happened. Coming back '
          'to right now is where you can actually act.',
    ),
  ];

  /// Letters that fill the gaps. Drawn from the words themselves so the grid
  /// reads as one puzzle rather than a word sitting in visible noise.
  static const _filler = 'AEIOURSTNLMBCDGPHY';

  /// The words this puzzle asks for, drawn fresh from [_pool] each time.
  late List<_Target> _targets;

  late List<String> _grid;

  /// Cells belonging to a word already found, kept so they stay marked.
  late Set<int> _solved;

  /// Words found so far, in the order they were found.
  late List<String> _found;

  /// First cell of a selection in progress, if any.
  int? _anchor;

  String? _notice;

  @override
  void initState() {
    super.initState();
    _newPuzzle();
  }

  void _newPuzzle() {
    final rng = Random();
    _targets = _drawWords(rng);
    _grid = _buildGrid(rng);
    _solved = <int>{};
    _found = <String>[];
    _anchor = null;
    _notice = null;
  }

  /// Takes [_wordsPerPuzzle] distinct words from the pool.
  ///
  /// Shuffling a copy rather than picking at random keeps the draw distinct
  /// without retrying, and the result is left in shuffled order so the word
  /// list underneath the grid is not in the same sequence every time either.
  List<_Target> _drawWords(Random rng) =>
      (List<_Target>.of(_pool)..shuffle(rng)).take(_wordsPerPuzzle).toList();

  // ==============================================================
  // GRID CONSTRUCTION
  // ==============================================================

  /// Lays every word into a fresh grid, retrying the whole board when a word
  /// cannot be placed. A board that cannot be built after [_maxBoards]
  /// attempts falls back to one row per word, which always fits because there
  /// are fewer words than rows and none is longer than a row.
  static const _maxBoards = 60;

  List<String> _buildGrid(Random rng) {
    for (var attempt = 0; attempt < _maxBoards; attempt++) {
      final grid = _tryPlaceAll(rng);
      if (grid != null) return _fill(grid, rng);
    }
    return _fill(_rowsFallback(), rng);
  }

  /// Right, down, down-right and up-right. Words never run backwards: a
  /// reversed word is a different kind of difficulty and not the point here.
  static const _directions = <(int, int)>[(0, 1), (1, 0), (1, 1), (-1, 1)];

  List<String>? _tryPlaceAll(Random rng) {
    final grid = List<String>.filled(_size * _size, '');

    // Longest first: the awkward words get the empty board.
    final words = _targets.map((target) => target.word).toList()
      ..sort((a, b) => b.length.compareTo(a.length));

    for (final word in words) {
      if (!_place(grid, word, rng)) return null;
    }
    return grid;
  }

  bool _place(List<String> grid, String word, Random rng) {
    for (var attempt = 0; attempt < 200; attempt++) {
      final (dr, dc) = _directions[rng.nextInt(_directions.length)];
      final row = rng.nextInt(_size);
      final column = rng.nextInt(_size);

      final endRow = row + dr * (word.length - 1);
      final endColumn = column + dc * (word.length - 1);
      if (endRow < 0 ||
          endRow >= _size ||
          endColumn < 0 ||
          endColumn >= _size) {
        continue;
      }

      // A cell may already hold the same letter — crossings are welcome.
      var fits = true;
      for (var i = 0; i < word.length; i++) {
        final cell = grid[(row + dr * i) * _size + (column + dc * i)];
        if (cell.isNotEmpty && cell != word[i]) {
          fits = false;
          break;
        }
      }
      if (!fits) continue;

      for (var i = 0; i < word.length; i++) {
        grid[(row + dr * i) * _size + (column + dc * i)] = word[i];
      }
      return true;
    }
    return false;
  }

  List<String> _rowsFallback() {
    final grid = List<String>.filled(_size * _size, '');
    for (var index = 0; index < _targets.length; index++) {
      final word = _targets[index].word;
      for (var i = 0; i < word.length; i++) {
        grid[index * _size + i] = word[i];
      }
    }
    return grid;
  }

  List<String> _fill(List<String> grid, Random rng) => [
    for (final cell in grid)
      cell.isEmpty ? _filler[rng.nextInt(_filler.length)] : cell,
  ];

  // ==============================================================
  // SELECTION
  // ==============================================================

  void _tapCell(int index) {
    if (_anchor == null) {
      setState(() {
        _anchor = index;
        _notice = null;
      });
      return;
    }

    // Tapping the anchor again is how you change your mind.
    if (_anchor == index) {
      setState(() => _anchor = null);
      return;
    }

    final line = _lineBetween(_anchor!, index);
    if (line == null) {
      setState(() {
        _anchor = index;
        _notice = 'Words run in a straight line — across, down or diagonally.';
      });
      return;
    }

    final letters = line.map((cell) => _grid[cell]).join();
    final match = _targets.firstWhere(
      // Either end may be tapped first, so the reverse counts too.
      (target) =>
          target.word == letters ||
          target.word == letters.split('').reversed.join(),
      orElse: () => const _Target(word: '', meaning: ''),
    );

    if (match.word.isEmpty || _found.contains(match.word)) {
      setState(() {
        _anchor = null;
        _notice = match.word.isEmpty ? null : '${match.word} is already found.';
      });
      return;
    }

    setState(() {
      _solved.addAll(line);
      _found.add(match.word);
      _anchor = null;
      _notice = null;
    });

    if (_found.length == _targets.length) _celebrate();
  }

  /// The cells from [from] to [to] when the two sit on one straight line, or
  /// null when they do not.
  List<int>? _lineBetween(int from, int to) {
    final fromRow = from ~/ _size;
    final fromColumn = from % _size;
    final toRow = to ~/ _size;
    final toColumn = to % _size;

    final rowStep = (toRow - fromRow).sign;
    final columnStep = (toColumn - fromColumn).sign;
    final rowSpan = (toRow - fromRow).abs();
    final columnSpan = (toColumn - fromColumn).abs();

    final straight = rowSpan == 0 || columnSpan == 0 || rowSpan == columnSpan;
    if (!straight) return null;

    final steps = max(rowSpan, columnSpan);
    return [
      for (var i = 0; i <= steps; i++)
        (fromRow + rowStep * i) * _size + (fromColumn + columnStep * i),
    ];
  }

  void _celebrate() {
    showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (dialogContext) => AlertDialog(
        title: const Text('All found'),
        content: const Text(
          'Six things you can do, not six things you should feel. Having a '
          'word for what would help is what makes it easier to ask for.',
        ),
        actions: [
          TextButton(
            onPressed: () {
              Navigator.pop(dialogContext);
              setState(_newPuzzle);
            },
            child: const Text('New Puzzle'),
          ),
          FilledButton(
            onPressed: () {
              Navigator.pop(dialogContext);
              Navigator.pop(context);
            },
            child: const Text('Done'),
          ),
        ],
      ),
    );
  }

  // ==============================================================
  // UI
  // ==============================================================

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Wellbeing Word Search'),
        actions: [
          IconButton(
            tooltip: 'New puzzle',
            onPressed: () => setState(_newPuzzle),
            icon: const Icon(Icons.refresh_rounded),
          ),
        ],
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(AppSpacing.lg),
                decoration: BoxDecoration(
                  color: AppColors.softSky,
                  borderRadius: BorderRadius.circular(AppRadii.input),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      'Found ${_found.length} of ${_targets.length}',
                      style: const TextStyle(
                        fontWeight: FontWeight.w700,
                        color: AppColors.primary,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    const Text(
                      'Tap the first letter, then the last. Each word you '
                      'find explains itself below.',
                      style: TextStyle(color: AppColors.muted, height: 1.4),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: AppSpacing.xl),

              // ============================================================
              // GRID
              // ============================================================
              GridView.builder(
                shrinkWrap: true,
                physics: const NeverScrollableScrollPhysics(),
                itemCount: _grid.length,
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: _size,
                  crossAxisSpacing: 3,
                  mainAxisSpacing: 3,
                ),
                itemBuilder: (context, index) {
                  final isSolved = _solved.contains(index);
                  final isAnchor = _anchor == index;

                  return GestureDetector(
                    onTap: () => _tapCell(index),
                    child: Container(
                      decoration: BoxDecoration(
                        color: isAnchor
                            ? AppColors.primary
                            : isSolved
                            ? AppColors.softSage
                            : AppColors.surface,
                        borderRadius: BorderRadius.circular(AppSpacing.sm),
                        border: Border.all(
                          color: isSolved || isAnchor
                              ? AppColors.primary
                              : AppColors.outline,
                        ),
                      ),
                      alignment: Alignment.center,
                      child: FittedBox(
                        fit: BoxFit.scaleDown,
                        child: Text(
                          _grid[index],
                          style: TextStyle(
                            fontWeight: FontWeight.w700,
                            fontSize: 16,
                            color: isAnchor ? AppColors.surface : AppColors.ink,
                          ),
                        ),
                      ),
                    ),
                  );
                },
              ),

              if (_notice != null) ...[
                const SizedBox(height: AppSpacing.md),
                Text(
                  _notice!,
                  style: const TextStyle(
                    color: AppColors.secondary,
                    height: 1.4,
                  ),
                ),
              ],

              const SizedBox(height: AppSpacing.xl),

              // ============================================================
              // WORD LIST
              // ============================================================
              const Text(
                'WORDS TO FIND',
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  color: AppColors.primary,
                  letterSpacing: 1.2,
                  fontSize: 12,
                ),
              ),

              const SizedBox(height: AppSpacing.md),

              for (final target in _targets)
                Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.md),
                  child: _TargetTile(
                    target: target,
                    found: _found.contains(target.word),
                  ),
                ),
            ],
          ),
        ),
      ),
    );
  }
}

/// A word in the list. Until it is found only its shape is shown, so the list
/// is a hint rather than an answer key; finding it reveals what it means.
class _TargetTile extends StatelessWidget {
  const _TargetTile({required this.target, required this.found});

  final _Target target;
  final bool found;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        color: found ? AppColors.softSage : AppColors.background,
        borderRadius: BorderRadius.circular(AppRadii.input),
        border: Border.all(
          color: found ? AppColors.primary : AppColors.outline,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                found
                    ? Icons.check_circle_rounded
                    : Icons.radio_button_unchecked_rounded,
                size: 18,
                color: found ? AppColors.primary : AppColors.muted,
              ),
              const SizedBox(width: AppSpacing.sm),
              Text(
                target.word,
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.5,
                  color: found ? AppColors.primary : AppColors.muted,
                ),
              ),
              const Spacer(),
              if (!found)
                Text(
                  '${target.word.length} letters',
                  style: const TextStyle(color: AppColors.muted, fontSize: 12),
                ),
            ],
          ),
          if (found) ...[
            const SizedBox(height: AppSpacing.sm),
            Text(
              target.meaning,
              style: const TextStyle(color: AppColors.ink, height: 1.5),
            ),
          ],
        ],
      ),
    );
  }
}

class _Target {
  const _Target({required this.word, required this.meaning});

  final String word;
  final String meaning;
}
