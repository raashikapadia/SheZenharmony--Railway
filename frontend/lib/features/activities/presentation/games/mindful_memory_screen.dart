import 'package:flutter/material.dart';

import '../../../../core/theme/app_theme.dart';

class MindfulMemoryScreen extends StatefulWidget {
  const MindfulMemoryScreen({super.key});

  @override
  State<MindfulMemoryScreen> createState() => _MindfulMemoryScreenState();
}

class _MindfulMemoryScreenState extends State<MindfulMemoryScreen> {
  final List<String> _pairSymbols = [
    '🌙',
    '🌿',
    '🌊',
    '🕊️',
    '🌸',
    '☁️',
    '🌙',
    '🌿',
    '🌊',
    '🕊️',
    '🌸',
    '☁️',
  ];

  late List<String> _cards;
  late List<bool> _revealed;
  late List<bool> _matched;

  int? _firstCardIndex;
  bool _isChecking = false;
  int _matchedPairs = 0;

  @override
  void initState() {
    super.initState();
    _startGame();
  }

  void _startGame() {
    _cards = List<String>.from(_pairSymbols);
    _cards.shuffle();

    _revealed = List<bool>.filled(_cards.length, false);

    _matched = List<bool>.filled(_cards.length, false);

    _firstCardIndex = null;
    _isChecking = false;
    _matchedPairs = 0;
  }

  Future<void> _selectCard(int index) async {
    if (_isChecking) {
      return;
    }

    if (_revealed[index] || _matched[index]) {
      return;
    }

    setState(() {
      _revealed[index] = true;
    });

    // First card selected.
    if (_firstCardIndex == null) {
      _firstCardIndex = index;
      return;
    }

    final firstIndex = _firstCardIndex!;

    setState(() {
      _isChecking = true;
    });

    await Future.delayed(const Duration(milliseconds: 700));

    if (!mounted) {
      return;
    }

    // Matching pair.
    if (_cards[firstIndex] == _cards[index]) {
      setState(() {
        _matched[firstIndex] = true;
        _matched[index] = true;
        _matchedPairs++;
        _isChecking = false;
      });

      _firstCardIndex = null;

      if (_matchedPairs == _pairSymbols.length ~/ 2) {
        _showCompletionDialog();
      }
    } else {
      // Not a match.
      setState(() {
        _revealed[firstIndex] = false;
        _revealed[index] = false;
        _isChecking = false;
      });

      _firstCardIndex = null;
    }
  }

  void _restartGame() {
    setState(() {
      _startGame();
    });
  }

  void _showCompletionDialog() {
    showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return AlertDialog(
          title: const Text('Well done!'),
          content: const Text(
            'You found all the matching pairs. Take a moment to enjoy your achievement.',
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(context);
                _restartGame();
              },
              child: const Text('Play Again'),
            ),
            FilledButton(
              onPressed: () {
                Navigator.pop(context);
                Navigator.pop(context);
              },
              child: const Text('Done'),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mindful Memory')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Column(
            children: [
              const Text(
                'Find the matching pairs',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
              ),

              const SizedBox(height: 8),

              const Text(
                'Take your time and notice each symbol as you find its pair.',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.muted, height: 1.4),
              ),

              const SizedBox(height: 18),

              // PAIRS FOUND
              Card(
                color: AppColors.softLavender,
                child: Padding(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 20,
                    vertical: 12,
                  ),
                  child: Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      const Icon(
                        Icons.auto_awesome_rounded,
                        color: AppColors.primary,
                      ),
                      const SizedBox(width: 10),
                      Text(
                        'Pairs found: $_matchedPairs / 6',
                        style: const TextStyle(fontWeight: FontWeight.w700),
                      ),
                    ],
                  ),
                ),
              ),

              const SizedBox(height: 16),

              // MEMORY CARDS
              // 3 COLUMNS x 4 ROWS
              Expanded(
                child: GridView.builder(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 8,
                  ),
                  itemCount: _cards.length,
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 4,
                    crossAxisSpacing: 8,
                    mainAxisSpacing: 8,

                    // Makes the boxes slightly smaller.
                    childAspectRatio: 1,
                  ),
                  itemBuilder: (context, index) {
                    final isVisible = _revealed[index] || _matched[index];

                    return GestureDetector(
                      onTap: () => _selectCard(index),
                      child: AnimatedContainer(
                        duration: const Duration(milliseconds: 200),
                        decoration: BoxDecoration(
                          // Matched/revealed cards.
                          // Hidden cards are light purple.
                          color: isVisible
                              ? AppColors.softSage
                              : const Color(0xFFEDE7F6),

                          borderRadius: BorderRadius.circular(12),

                          border: Border.all(
                            color: isVisible
                                ? AppColors.primary
                                : const Color(0xFFD1C4E9),
                            width: 1.5,
                          ),

                          boxShadow: [
                            BoxShadow(
                              blurRadius: 4,
                              offset: const Offset(0, 2),
                              color: Colors.black.withValues(alpha: 0.06),
                            ),
                          ],
                        ),

                        child: Center(
                          child: AnimatedSwitcher(
                            duration: const Duration(milliseconds: 200),

                            child: isVisible
                                ? Text(
                                    _cards[index],
                                    key: ValueKey('visible-$index'),
                                    style: const TextStyle(fontSize: 27),
                                  )
                                : const Text(
                                    '?',
                                    key: ValueKey('hidden'),
                                    style: TextStyle(
                                      fontSize: 25,
                                      fontWeight: FontWeight.bold,
                                      color: AppColors.primary,
                                    ),
                                  ),
                          ),
                        ),
                      ),
                    );
                  },
                ),
              ),

              const SizedBox(height: 12),

              // RESTART BUTTON
              OutlinedButton.icon(
                onPressed: _restartGame,
                icon: const Icon(Icons.refresh_rounded),
                label: const Text('Restart Game'),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
