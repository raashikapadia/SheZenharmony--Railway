import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../../core/network/api_service.dart';
import '../../../../core/theme/app_theme.dart';
import '../../../auth/application/auth_provider.dart';
import '../../data/support_content.dart';
import '../../data/managed_quiz.dart';
import 'gratitude_jar_screen.dart';
import 'mindful_spark_screen.dart';
import 'mindful_memory_screen.dart';

/// Which part of the screen to bring into view when it opens. Positive
/// Engagement lists Games and Quizzes as separate areas, and both land here.
enum GamesQuizzesSection { games, quizzes }

class GamesQuizzesScreen extends StatefulWidget {
  const GamesQuizzesScreen({super.key, this.focus = GamesQuizzesSection.games});

  final GamesQuizzesSection focus;

  @override
  State<GamesQuizzesScreen> createState() => _GamesQuizzesScreenState();
}

class _GamesQuizzesScreenState extends State<GamesQuizzesScreen> {
  final GlobalKey _quizzesKey = GlobalKey();
  late final ApiService _api;
  late Future<List<PositiveContent>> _games;
  late Future<List<ManagedQuiz>> _quizzes;

  @override
  void initState() {
    super.initState();
    _api = ApiService();
    _games = widget.focus == GamesQuizzesSection.games
        ? _api.games()
        : Future.value(const <PositiveContent>[]);
    _quizzes = widget.focus == GamesQuizzesSection.quizzes
        ? _api.managedQuizzes()
        : Future.value(const <ManagedQuiz>[]);
    if (widget.focus != GamesQuizzesSection.quizzes) return;
    WidgetsBinding.instance.addPostFrameCallback((_) {
      final context = _quizzesKey.currentContext;
      if (context == null) return;
      Scrollable.ensureVisible(
        context,
        duration: const Duration(milliseconds: 350),
        curve: Curves.easeOut,
        alignment: 0.05,
      );
    });
  }

  @override
  void dispose() {
    _api.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Games & Quizzes'), centerTitle: true),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ============================================================
              // HEADER
              // ============================================================
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(22),
                decoration: BoxDecoration(
                  color: AppColors.softLavender,
                  borderRadius: BorderRadius.circular(24),
                ),
                child: Column(
                  children: [
                    Container(
                      width: 64,
                      height: 64,
                      decoration: BoxDecoration(
                        color: AppColors.surface,
                        shape: BoxShape.circle,
                      ),
                      child: const Icon(
                        Icons.auto_awesome_rounded,
                        size: 32,
                        color: AppColors.primary,
                      ),
                    ),

                    const SizedBox(height: 14),

                    Text(
                      'Take a mindful break',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: AppColors.primary,
                          ),
                    ),

                    const SizedBox(height: 6),

                    const Text(
                      'Explore simple activities and quizzes designed '
                      'to help you pause, reflect and reset.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AppColors.muted, height: 1.45),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 28),

              if (widget.focus == GamesQuizzesSection.games) ...[
                // ============================================================
                // GAMES
                // ============================================================
                Text(
                  'GAMES',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: AppColors.primary,
                    letterSpacing: 1.2,
                  ),
                ),

                const SizedBox(height: 14),

                FutureBuilder<List<PositiveContent>>(
                  future: _games,
                  builder: (context, snapshot) {
                    if (snapshot.connectionState == ConnectionState.waiting) {
                      return const Center(child: CircularProgressIndicator());
                    }
                    if (snapshot.hasError ||
                        (snapshot.data ?? const []).isEmpty) {
                      return const _QuizNotice(
                        message: 'No games are available right now.',
                      );
                    }
                    return Column(
                      children: [
                        for (final game in snapshot.data!) ...[
                          _GameCard(
                            icon: _gameIcon(game),
                            title: game.title,
                            description: game.description,
                            color: _gameColor(game),
                            onTap: () => _openGame(game),
                          ),
                          const SizedBox(height: 20),
                        ],
                      ],
                    );
                  },
                ),

                const SizedBox(height: 32),
              ],

              if (widget.focus == GamesQuizzesSection.quizzes) ...[
                // ============================================================
                // QUIZZES
                // ============================================================
                Text(
                  key: _quizzesKey,
                  'QUIZZES',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                    color: AppColors.primary,
                    letterSpacing: 1.2,
                  ),
                ),

                const SizedBox(height: 14),

                const Text(
                  'Choose a quiz and take it one question at a time.',
                  style: TextStyle(color: AppColors.muted, height: 1.4),
                ),

                const SizedBox(height: 16),

                FutureBuilder<List<ManagedQuiz>>(
                  future: _quizzes,
                  builder: (context, snapshot) {
                    if (snapshot.connectionState == ConnectionState.waiting) {
                      return const Center(child: CircularProgressIndicator());
                    }
                    if (snapshot.hasError) {
                      return _QuizNotice(
                        message: 'Quizzes could not be loaded right now.',
                        onRetry: () => setState(() {
                          _quizzes = _api.managedQuizzes();
                        }),
                      );
                    }
                    final quizzes = snapshot.data ?? const <ManagedQuiz>[];
                    if (quizzes.isEmpty) {
                      return const _QuizNotice(
                        message: 'No quizzes are available right now.',
                      );
                    }
                    return Column(
                      children: [
                        for (final quiz in quizzes) ...[
                          _QuizCard(
                            quiz: quiz,
                            onTap: () => Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => QuizStartScreen(quiz: quiz),
                              ),
                            ),
                          ),
                          const SizedBox(height: 20),
                        ],
                      ],
                    );
                  },
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }

  /// Each game is a screen in this app, keyed by the backend slug so that
  /// renaming a game in the admin does not change what it opens. Titles are
  /// only consulted for rows saved before slugs were published.
  static const _gameKeys = {
    'breathing-challenge': 'Breathing Challenge',
    'gratitude-jar': 'Gratitude Jar',
    'memory-spark': 'Memory Spark',
    'mindful-memory': 'Mindful Memory',
  };

  String? _gameKey(PositiveContent game) =>
      _gameKeys[game.slug] ??
      (_gameKeys.containsValue(game.title) ? game.title : null);

  IconData _gameIcon(PositiveContent game) => switch (_gameKey(game)) {
    'Breathing Challenge' => Icons.air_rounded,
    'Gratitude Jar' => Icons.favorite_rounded,
    'Memory Spark' => Icons.auto_awesome_rounded,
    _ => Icons.psychology_outlined,
  };

  Color _gameColor(PositiveContent game) => switch (_gameKey(game)) {
    'Gratitude Jar' => AppColors.softBlush,
    'Memory Spark' => AppColors.softLavender,
    _ => AppColors.softSage,
  };

  void _openGame(PositiveContent game) {
    final Widget? screen = switch (_gameKey(game)) {
      'Breathing Challenge' => const BreathingGameScreen(),
      'Gratitude Jar' => const GratitudeJarScreen(),
      'Memory Spark' => const MindfulSparkScreen(),
      'Mindful Memory' => const MindfulMemoryScreen(),
      _ => null,
    };

    // An unrecognised game has no screen in this build. Saying so beats
    // quietly opening a different game than the one that was tapped.
    if (screen == null) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('${game.title} needs a newer version of the app.'),
        ),
      );
      return;
    }

    final token = context.read<AuthProvider>().session?.token;
    if (token != null && game.id != null) {
      _api.recordGamePlay(token, game.id!).catchError((_) {});
    }
    Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));
  }
}

// ============================================================
// GAME CARD
// ============================================================

class _QuizCard extends StatelessWidget {
  const _QuizCard({required this.quiz, required this.onTap});

  final ManagedQuiz quiz;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: Ink(
          width: double.infinity,
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [AppColors.softBlush, AppColors.softLavender],
            ),
            borderRadius: BorderRadius.circular(22),
            border: Border.all(
              color: AppColors.primary.withValues(alpha: 0.18),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 48,
                    height: 48,
                    decoration: const BoxDecoration(
                      color: AppColors.surface,
                      shape: BoxShape.circle,
                    ),
                    child: const Icon(
                      Icons.quiz_outlined,
                      color: AppColors.primary,
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      quiz.name,
                      style: const TextStyle(
                        color: AppColors.primary,
                        fontSize: 17,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                  const Icon(
                    Icons.arrow_forward_rounded,
                    color: AppColors.primary,
                  ),
                ],
              ),
              const SizedBox(height: 14),
              if (quiz.category.isNotEmpty)
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 10,
                    vertical: 5,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.surface.withValues(alpha: 0.78),
                    borderRadius: BorderRadius.circular(20),
                  ),
                  child: Text(
                    quiz.category,
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 12,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              if (quiz.description.isNotEmpty) ...[
                const SizedBox(height: 10),
                Text(
                  quiz.description,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(color: AppColors.muted, height: 1.4),
                ),
              ],
              const SizedBox(height: 14),
              Row(
                children: [
                  const Icon(
                    Icons.format_list_numbered_rounded,
                    size: 18,
                    color: AppColors.primary,
                  ),
                  const SizedBox(width: 6),
                  Text(
                    '${quiz.questions.length} questions',
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const Spacer(),
                  const Text(
                    'Open quiz',
                    style: TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _GameCard extends StatelessWidget {
  const _GameCard({
    required this.icon,
    required this.title,
    required this.description,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String description;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(22),
        child: Ink(
          width: double.infinity,
          padding: const EdgeInsets.all(18),
          decoration: BoxDecoration(
            color: color,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(
              color: AppColors.primary.withValues(alpha: 0.08),
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: 0.05),
                blurRadius: 12,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 56,
                height: 56,
                decoration: BoxDecoration(
                  color: AppColors.surface.withValues(alpha: 0.85),
                  shape: BoxShape.circle,
                ),
                child: Icon(icon, color: AppColors.primary, size: 28),
              ),

              const SizedBox(width: 16),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      title,
                      style: const TextStyle(
                        color: AppColors.primary,
                        fontSize: 16,
                        fontWeight: FontWeight.w700,
                      ),
                    ),

                    const SizedBox(height: 5),

                    Text(
                      description,
                      style: const TextStyle(
                        color: AppColors.muted,
                        fontSize: 13,
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(width: 8),

              Container(
                width: 34,
                height: 34,
                decoration: BoxDecoration(
                  color: AppColors.surface.withValues(alpha: 0.75),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.arrow_forward_ios_rounded,
                  size: 15,
                  color: AppColors.primary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ============================================================
// BREATHING CHALLENGE
// ============================================================

class BreathingGameScreen extends StatefulWidget {
  const BreathingGameScreen({super.key});

  @override
  State<BreathingGameScreen> createState() => _BreathingGameScreenState();
}

class _BreathingGameScreenState extends State<BreathingGameScreen>
    with SingleTickerProviderStateMixin {
  late AnimationController _controller;

  bool _started = false;
  int _round = 1;

  final int _totalRounds = 3;
  static const _phaseDuration = 4;
  static const _phaseCount = 4;

  @override
  void initState() {
    super.initState();

    _controller = AnimationController(
      vsync: this,
      duration: Duration(seconds: _totalRounds * _phaseCount * _phaseDuration),
    )..addListener(_syncBreathingState);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _syncBreathingState() {
    if (!mounted) return;
    final elapsedPhases = _controller.value * _totalRounds * _phaseCount;
    final nextRound = (elapsedPhases ~/ _phaseCount) + 1;
    if (_round != nextRound && nextRound <= _totalRounds) {
      setState(() => _round = nextRound);
    } else {
      setState(() {});
    }
  }

  Future<void> _startBreathing() async {
    _controller.stop();
    setState(() {
      _started = true;
      _round = 1;
    });

    try {
      await _controller.forward(from: 0);
      if (!mounted) return;
      setState(() => _started = false);
      _showCompleteDialog();
    } on TickerCanceled {
      // The controller is canceled when the screen is disposed.
    }
  }

  String get _phaseLabel {
    if (!_started) return _controller.value == 1 ? 'Complete' : 'Ready';
    final phase =
        ((_controller.value * _totalRounds * _phaseCount).floor()) %
        _phaseCount;
    return const ['Inhale', 'Hold', 'Exhale', 'Hold'][phase];
  }

  double get _phaseProgress {
    final progress = _controller.value * _totalRounds * _phaseCount;
    return progress - progress.floor();
  }

  int get _secondsRemaining {
    if (!_started) return 0;
    return (_phaseDuration * (1 - _phaseProgress)).ceil().clamp(1, 4);
  }

  void _showCompleteDialog() {
    showDialog<void>(
      context: context,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
          ),
          title: const Text('Well done'),
          content: const Text(
            'You completed the breathing challenge. '
            'Take a moment to notice how you feel.',
          ),
          actions: [
            FilledButton(
              onPressed: () {
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
      appBar: AppBar(title: const Text('Breathing Challenge')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            children: [
              const SizedBox(height: 20),

              const Icon(Icons.air_rounded, size: 44, color: AppColors.primary),

              const SizedBox(height: 16),

              Text(
                'Breathe with intention',
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),

              const SizedBox(height: 8),

              const Text(
                'Follow the circle and give yourself a quiet moment.',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.muted, height: 1.4),
              ),

              const SizedBox(height: 28),

              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(18),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(
                    color: AppColors.primary.withValues(alpha: 0.10),
                  ),
                ),
                child: Column(
                  children: [
                    Text(
                      _phaseLabel,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: AppColors.primary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      _started
                          ? '$_secondsRemaining seconds'
                          : 'Four calm phases',
                      style: const TextStyle(color: AppColors.muted),
                    ),
                    const SizedBox(height: 12),
                    LinearProgressIndicator(
                      value: _started ? _phaseProgress : 0,
                      minHeight: 7,
                      borderRadius: BorderRadius.circular(20),
                      backgroundColor: AppColors.softLavender,
                      color: AppColors.primary,
                    ),
                  ],
                ),
              ),

              Expanded(
                child: Center(
                  child: AnimatedBuilder(
                    animation: _controller,
                    builder: (context, child) {
                      final phase =
                          ((_controller.value * _totalRounds * _phaseCount)
                              .floor()) %
                          _phaseCount;
                      final phaseProgress = _phaseProgress;
                      final expanding = phase == 0 || phase == 1;
                      final breathingProgress = expanding
                          ? phaseProgress
                          : 1 - phaseProgress;
                      final scale = 0.74 + (breathingProgress * 0.26);

                      return Transform.scale(
                        scale: scale,
                        child: Container(
                          width: 210,
                          height: 210,
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.topLeft,
                              end: Alignment.bottomRight,
                              colors: [
                                AppColors.primary,
                                AppColors.softLavender,
                              ],
                            ),
                            shape: BoxShape.circle,
                            border: Border.all(
                              color: AppColors.primary.withValues(alpha: 0.95),
                              width: 8,
                            ),
                            boxShadow: [
                              BoxShadow(
                                color: AppColors.primary.withValues(
                                  alpha: 0.35,
                                ),
                                blurRadius: 18,
                                spreadRadius: 3,
                              ),
                            ],
                          ),
                          child: Center(
                            child: Text(
                              _phaseLabel,
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                color: AppColors.primary,
                                fontSize: 22,
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ),
                        ),
                      );
                    },
                  ),
                ),
              ),

              if (_started)
                Text(
                  'Round $_round / $_totalRounds',
                  style: const TextStyle(
                    color: AppColors.muted,
                    fontWeight: FontWeight.w600,
                  ),
                ),

              const SizedBox(height: 16),

              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _started ? null : _startBreathing,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(vertical: 5),
                    child: Text(_started ? 'Breathing...' : 'Start challenge'),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _QuizNotice extends StatelessWidget {
  const _QuizNotice({required this.message, this.onRetry});

  final String message;
  final VoidCallback? onRetry;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.softSage,
        borderRadius: BorderRadius.circular(18),
      ),
      child: Column(
        children: [
          Text(message, textAlign: TextAlign.center),
          if (onRetry != null) ...[
            const SizedBox(height: 10),
            TextButton(onPressed: onRetry, child: const Text('Try again')),
          ],
        ],
      ),
    );
  }
}

class QuizStartScreen extends StatelessWidget {
  const QuizStartScreen({super.key, required this.quiz});

  final ManagedQuiz quiz;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Select quiz')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                quiz.category.isEmpty ? 'Quiz' : quiz.category,
                style: const TextStyle(
                  color: AppColors.muted,
                  fontWeight: FontWeight.w700,
                  letterSpacing: 1.1,
                ),
              ),
              const SizedBox(height: 12),
              Text(
                quiz.name,
                style: const TextStyle(
                  color: AppColors.primary,
                  fontSize: 28,
                  fontWeight: FontWeight.w700,
                ),
              ),
              const SizedBox(height: 14),
              Text(
                quiz.description.isEmpty
                    ? 'Test your knowledge and learn as you go.'
                    : quiz.description,
                style: const TextStyle(color: AppColors.muted, height: 1.45),
              ),
              const SizedBox(height: 20),
              Text('${quiz.questions.length} questions'),
              const Spacer(),
              const Center(
                child: Text('🌟  🧠  ✨', style: TextStyle(fontSize: 34)),
              ),
              const SizedBox(height: 14),
              const Spacer(),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => ManagedQuizScreen(quiz: quiz),
                    ),
                  ),
                  icon: const Icon(Icons.play_arrow_rounded),
                  label: const Text('Start quiz'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class ManagedQuizScreen extends StatefulWidget {
  const ManagedQuizScreen({super.key, required this.quiz});

  final ManagedQuiz quiz;

  @override
  State<ManagedQuizScreen> createState() => _ManagedQuizScreenState();
}

class _ScoreFeedback extends StatelessWidget {
  const _ScoreFeedback({
    required this.score,
    required this.total,
    this.corrections = const [],
  });

  final int score;
  final int total;
  final List<_QuizCorrection> corrections;

  ({String title, String message}) get _feedback {
    if (score == total) {
      return (
        title: 'Excellent!',
        message: 'You got every question correct. Fantastic knowledge!',
      );
    }
    if (score >= 4) {
      return (
        title: 'Great job!',
        message:
            'You got most questions correct. Keep practising to achieve a perfect score!',
      );
    }
    if (score >= 3) {
      return (
        title: 'Good effort!',
        message:
            'You got more than half correct. Review the questions you missed and try again.',
      );
    }
    if (score == 2) {
      return (
        title: 'Nice try!',
        message:
            'You got some questions correct. Keep learning and practising to improve your score.',
      );
    }
    if (score == 1) {
      return (
        title: 'Keep going!',
        message:
            'You got one question correct. Review the topics and give the quiz another try.',
      );
    }
    return (
      title: "Don't give up!",
      message:
          'This is a great opportunity to learn. Review the answers and try again.',
    );
  }

  @override
  Widget build(BuildContext context) {
    final feedback = _feedback;

    return SingleChildScrollView(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Text('Score: $score/$total'),
          const SizedBox(height: 12),
          Text(
            feedback.title,
            style: const TextStyle(fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 4),
          Text(feedback.message),
          if (corrections.isNotEmpty) ...[
            const SizedBox(height: 16),
            const Text(
              'Review your answers',
              style: TextStyle(fontWeight: FontWeight.bold),
            ),
            const SizedBox(height: 8),
            for (final correction in corrections) ...[
              Text(
                correction.question,
                style: const TextStyle(fontWeight: FontWeight.w600),
              ),
              const SizedBox(height: 4),
              Text('Correct answer: ${correction.correctAnswer}'),
              if (correction.explanation.isNotEmpty)
                Text('Explanation: ${correction.explanation}'),
              const SizedBox(height: 12),
            ],
          ],
        ],
      ),
    );
  }
}

class _QuizCorrection {
  const _QuizCorrection({
    required this.question,
    required this.correctAnswer,
    required this.explanation,
  });

  final String question;
  final String correctAnswer;
  final String explanation;

  factory _QuizCorrection.fromJson(Map<String, dynamic> json) {
    return _QuizCorrection(
      question: json['question_text'] as String? ?? '',
      correctAnswer: json['correct_answer'] as String? ?? '',
      explanation: json['explanation'] as String? ?? '',
    );
  }
}

class _ManagedAnswerFeedback extends StatelessWidget {
  const _ManagedAnswerFeedback({
    required this.question,
    required this.selectedAnswer,
    required this.feedback,
  });

  final ManagedQuizQuestion question;
  final String selectedAnswer;
  final Map<String, dynamic> feedback;

  @override
  Widget build(BuildContext context) {
    final isCorrect = feedback['is_correct'] as bool? ?? false;

    return Container(
      width: double.infinity,
      margin: const EdgeInsets.only(top: 8),
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isCorrect ? AppColors.softSage : AppColors.softBlush,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            isCorrect ? 'Correct!' : 'Not quite!',
            style: const TextStyle(
              color: AppColors.primary,
              fontWeight: FontWeight.w700,
              fontSize: 17,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Your answer: ${question.options[selectedAnswer] ?? selectedAnswer}',
          ),
          const SizedBox(height: 4),
          Text('Correct answer: ${feedback['correct_answer']}'),
          const SizedBox(height: 8),
          Text(
            (feedback['explanation'] as String?)?.isNotEmpty == true
                ? feedback['explanation'] as String
                : isCorrect
                ? 'Great job! You got this one right.'
                : 'Review this answer to reinforce the topic.',
          ),
        ],
      ),
    );
  }
}

class _ManagedQuizScreenState extends State<ManagedQuizScreen> {
  final Map<int, String> _answers = {};
  late final ApiService _api = ApiService();
  int _currentQuestion = 0;
  String? _selectedAnswer;
  Map<String, dynamic>? _answerFeedback;
  bool _checkingAnswer = false;
  bool _submitting = false;

  @override
  void dispose() {
    _api.close();
    super.dispose();
  }

  void _selectAnswer(String answer) {
    if (_checkingAnswer || _answerFeedback != null) return;

    setState(() => _selectedAnswer = answer);
  }

  Future<void> _submitAnswer() async {
    if (_checkingAnswer || _answerFeedback != null || _selectedAnswer == null) {
      return;
    }
    final token = context.read<AuthProvider>().session?.token;
    if (token == null) return;

    final question = widget.quiz.questions[_currentQuestion];
    final answer = _selectedAnswer!;
    setState(() {
      _answers[question.id] = answer;
      _checkingAnswer = true;
    });
    try {
      final feedback = await _api.answerManagedQuizQuestion(
        token,
        widget.quiz.id,
        question.id,
        answer,
      );
      if (!mounted) return;
      setState(() {
        _answerFeedback = {
          ...feedback,
          'answer': question.options[answer] ?? answer,
        };
        _checkingAnswer = false;
      });
    } on ApiException catch (error) {
      if (!mounted) return;
      setState(() => _checkingAnswer = false);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    }
  }

  Future<void> _nextQuestion() async {
    if (_answerFeedback == null || _submitting) {
      return;
    }

    if (_currentQuestion < widget.quiz.questions.length - 1) {
      setState(() {
        _currentQuestion++;
        _selectedAnswer = null;
        _answerFeedback = null;
      });
      return;
    }

    await _submit();
  }

  Future<void> _submit() async {
    if (_answers.length != widget.quiz.questions.length || _submitting) {
      return;
    }
    final token = context.read<AuthProvider>().session?.token;
    if (token == null) return;

    setState(() => _submitting = true);
    try {
      final result = await _api.completeManagedQuiz(
        token,
        widget.quiz.id,
        _answers,
      );
      if (!mounted) return;
      final corrections =
          (result['incorrect_questions'] as List<dynamic>? ?? const [])
              .whereType<Map<String, dynamic>>()
              .map(_QuizCorrection.fromJson)
              .toList();
      await showDialog<void>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Final score'),
          content: _ScoreFeedback(
            score: result['score'] as int,
            total: result['total_questions'] as int,
            corrections: corrections,
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(context);
                setState(() {
                  _currentQuestion = 0;
                  _selectedAnswer = null;
                  _answerFeedback = null;
                  _answers.clear();
                });
              },
              child: const Text('Try again'),
            ),
            FilledButton(
              onPressed: () {
                Navigator.pop(context);
                Navigator.pop(context);
              },
              child: const Text('Done'),
            ),
          ],
        ),
      );
    } on ApiException catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(error.message)));
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final question = widget.quiz.questions[_currentQuestion];
    final selectedAnswer = _selectedAnswer;

    return Scaffold(
      appBar: AppBar(title: Text(widget.quiz.name)),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            if (widget.quiz.description.isNotEmpty)
              Text(
                widget.quiz.description,
                style: const TextStyle(color: AppColors.muted, height: 1.4),
              ),
            const SizedBox(height: 18),
            Text(
              'Question ${_currentQuestion + 1} of ${widget.quiz.questions.length}',
              style: const TextStyle(
                color: AppColors.muted,
                fontWeight: FontWeight.w600,
              ),
            ),
            const SizedBox(height: 12),
            if (_answerFeedback == null && !_checkingAnswer)
              const Text(
                'Select an answer, then submit to see the result.',
                style: TextStyle(color: AppColors.muted),
              ),
            if (_answerFeedback == null && !_checkingAnswer)
              const SizedBox(height: 12),
            Text(
              question.questionText,
              style: const TextStyle(
                color: AppColors.primary,
                fontSize: 18,
                fontWeight: FontWeight.w700,
                height: 1.4,
              ),
            ),
            const SizedBox(height: 16),
            for (final option in question.options.entries)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: InkWell(
                  onTap: _submitting || _checkingAnswer
                      ? null
                      : () => _selectAnswer(option.key),
                  borderRadius: BorderRadius.circular(14),
                  child: Container(
                    padding: const EdgeInsets.all(14),
                    decoration: BoxDecoration(
                      color: selectedAnswer == option.key
                          ? AppColors.softLavender
                          : AppColors.surface,
                      borderRadius: BorderRadius.circular(14),
                      border: Border.all(
                        color: selectedAnswer == option.key
                            ? AppColors.primary
                            : AppColors.primary.withValues(alpha: 0.10),
                      ),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          selectedAnswer == option.key
                              ? Icons.radio_button_checked
                              : Icons.radio_button_unchecked,
                          color: AppColors.primary,
                        ),
                        const SizedBox(width: 10),
                        Expanded(child: Text(option.value)),
                      ],
                    ),
                  ),
                ),
              ),
            if (_checkingAnswer)
              const Padding(
                padding: EdgeInsets.symmetric(vertical: 12),
                child: Center(child: CircularProgressIndicator()),
              ),
            if (_answerFeedback != null)
              _ManagedAnswerFeedback(
                question: question,
                selectedAnswer: selectedAnswer!,
                feedback: _answerFeedback!,
              ),
            const SizedBox(height: 18),
            FilledButton(
              onPressed: _answerFeedback == null
                  ? (selectedAnswer == null || _checkingAnswer
                        ? null
                        : _submitAnswer)
                  : _nextQuestion,
              child: Text(
                _answerFeedback == null
                    ? 'Submit answer'
                    : _currentQuestion == widget.quiz.questions.length - 1
                    ? 'See results'
                    : 'Next question',
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ============================================================
// LEGACY QUIZ SCREENS
// ============================================================

class WellbeingQuizScreen extends StatefulWidget {
  const WellbeingQuizScreen({super.key});

  @override
  State<WellbeingQuizScreen> createState() => _WellbeingQuizScreenState();
}

class _WellbeingQuizScreenState extends State<WellbeingQuizScreen> {
  final List<_QuizQuestion> _questions = const [
    _QuizQuestion(
      question:
          'Which activity can help you take a healthy break during a busy day?',
      options: [
        'Taking a short walk',
        'Skipping every break',
        'Ignoring how you feel',
        'Staying focused without resting',
      ],
      correctIndex: 0,
      explanation: 'A short walk gives your body and mind a refreshing break.',
    ),
    _QuizQuestion(
      question: 'What is a positive way to respond when you feel overwhelmed?',
      options: [
        'Take a moment to pause and breathe',
        'Keep everything to yourself',
        'Ignore the feeling',
        'Rush through everything',
      ],
      correctIndex: 0,
      explanation:
          'Pausing and breathing can help you feel calmer and more in control.',
    ),
    _QuizQuestion(
      question: 'Why can getting enough sleep support wellbeing?',
      options: [
        'It gives your body and mind time to recover',
        'It means you never need breaks',
        'It removes every problem',
        'It replaces healthy habits',
      ],
      correctIndex: 0,
      explanation:
          'Sleep supports the recovery and energy your body and mind need.',
    ),
    _QuizQuestion(
      question: 'Which is an example of positive self-care?',
      options: [
        'Making time for activities that help you recharge',
        'Never asking for help',
        'Ignoring your needs',
        'Constantly comparing yourself with others',
      ],
      correctIndex: 0,
      explanation:
          'Positive self-care includes activities that support your wellbeing.',
    ),
    _QuizQuestion(
      question: 'What can help build a positive daily routine?',
      options: [
        'Small realistic habits',
        'Trying to change everything at once',
        'Skipping meals and breaks',
        'Never adjusting your routine',
      ],
      correctIndex: 0,
      explanation:
          'Small realistic habits are easier to maintain and build into a routine.',
    ),
  ];

  int _currentQuestion = 0;
  int _score = 0;
  int? _selectedAnswer;
  bool _answered = false;
  late final List<int?> _answers = List<int?>.filled(_questions.length, null);

  void _selectAnswer(int index) {
    if (_answered) return;

    setState(() => _selectedAnswer = index);
  }

  void _submitAnswer() {
    if (_selectedAnswer == null || _answered) return;

    setState(() {
      _answered = true;
      _answers[_currentQuestion] = _selectedAnswer;

      if (_selectedAnswer == _questions[_currentQuestion].correctIndex) {
        _score++;
      }
    });
  }

  void _nextQuestion() {
    if (!_answered) return;

    if (_currentQuestion == _questions.length - 1) {
      _showResults();
      return;
    }

    setState(() {
      _currentQuestion++;
      _selectedAnswer = null;
      _answered = false;
    });
  }

  void _restartQuiz() {
    setState(() {
      _currentQuestion = 0;
      _score = 0;
      _selectedAnswer = null;
      _answered = false;
      _answers.fillRange(0, _answers.length, null);
    });
  }

  void _showResults() {
    final corrections = [
      for (var index = 0; index < _questions.length; index++)
        if (_answers[index] != _questions[index].correctIndex)
          _QuizCorrection(
            question: _questions[index].question,
            correctAnswer:
                _questions[index].options[_questions[index].correctIndex],
            explanation: 'Review this answer to reinforce the topic.',
          ),
    ];

    showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
          ),
          title: const Text('Quiz complete'),
          content: _ScoreFeedback(
            score: _score,
            total: _questions.length,
            corrections: corrections,
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(context);
                _restartQuiz();
              },
              child: const Text('Try again'),
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
    final question = _questions[_currentQuestion];

    return Scaffold(
      appBar: AppBar(title: const Text('Wellbeing Quiz')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              LinearProgressIndicator(
                value: (_currentQuestion + 1) / _questions.length,
                minHeight: 7,
                borderRadius: BorderRadius.circular(20),
                backgroundColor: AppColors.softLavender,
                color: AppColors.primary,
              ),

              const SizedBox(height: 20),

              Text(
                'Question ${_currentQuestion + 1} '
                'of ${_questions.length}',
                style: const TextStyle(
                  color: AppColors.muted,
                  fontWeight: FontWeight.w600,
                ),
              ),

              const SizedBox(height: 12),

              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(22),
                decoration: BoxDecoration(
                  color: AppColors.softLavender,
                  borderRadius: BorderRadius.circular(22),
                ),
                child: Text(
                  question.question,
                  style: const TextStyle(
                    color: AppColors.primary,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    height: 1.4,
                  ),
                ),
              ),

              const SizedBox(height: 20),

              ...List.generate(question.options.length, (index) {
                return Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _AnswerCard(
                    text: question.options[index],
                    optionIndex: index,
                    selected: _selectedAnswer == index,
                    answered: _answered,
                    correct: index == question.correctIndex,
                    onTap: () => _selectAnswer(index),
                  ),
                );
              }),

              const SizedBox(height: 12),

              if (_answered)
                _AnswerFeedback(
                  question: question,
                  selectedAnswer: _selectedAnswer!,
                ),

              const SizedBox(height: 20),

              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _answered
                      ? _nextQuestion
                      : _selectedAnswer == null
                      ? null
                      : _submitAnswer,
                  child: Text(
                    _answered
                        ? _currentQuestion == _questions.length - 1
                              ? 'See results'
                              : 'Next question'
                        : 'Submit answer',
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ============================================================
// MINDFULNESS QUIZ
// ============================================================

class MindfulnessQuizScreen extends StatefulWidget {
  const MindfulnessQuizScreen({super.key});

  @override
  State<MindfulnessQuizScreen> createState() => _MindfulnessQuizScreenState();
}

class _MindfulnessQuizScreenState extends State<MindfulnessQuizScreen> {
  final List<_QuizQuestion> _questions = const [
    _QuizQuestion(
      question: 'What does mindfulness encourage you to do?',
      options: [
        'Notice the present moment',
        'Think about everything at once',
        'Avoid every thought',
        'Rush through activities',
      ],
      correctIndex: 0,
      explanation: 'Mindfulness means paying attention to the present moment.',
    ),
    _QuizQuestion(
      question: 'Which can be part of a mindfulness practice?',
      options: [
        'Paying attention to your breathing',
        'Ignoring your surroundings',
        'Multitasking constantly',
        'Trying to control every thought',
      ],
      correctIndex: 0,
      explanation:
          'Noticing your breathing is a simple way to practise mindfulness.',
    ),
    _QuizQuestion(
      question: 'If your mind wanders during mindfulness, what can you do?',
      options: [
        'Gently bring your attention back',
        'Give up immediately',
        'Become frustrated with yourself',
        'Try to force your mind to stop',
      ],
      correctIndex: 0,
      explanation:
          'Gently returning your attention is a kind and practical response to a wandering mind.',
    ),
    _QuizQuestion(
      question: 'Mindfulness can be practised during which activity?',
      options: [
        'Everyday activities such as walking or eating',
        'Only during formal meditation',
        'Only before sleeping',
        'Only in complete silence',
      ],
      correctIndex: 0,
      explanation:
          'Mindfulness can be practised during ordinary activities throughout the day.',
    ),
    _QuizQuestion(
      question: 'What is a helpful attitude during mindfulness?',
      options: [
        'Curiosity and kindness toward your experience',
        'Judging yourself',
        'Expecting perfection',
        'Rushing to finish',
      ],
      correctIndex: 0,
      explanation:
          'Curiosity and kindness help you notice your experience without judging yourself.',
    ),
  ];

  int _currentQuestion = 0;
  int _score = 0;
  int? _selectedAnswer;
  bool _answered = false;
  late final List<int?> _answers = List<int?>.filled(_questions.length, null);

  void _selectAnswer(int index) {
    if (_answered) return;

    setState(() => _selectedAnswer = index);
  }

  void _submitAnswer() {
    if (_selectedAnswer == null || _answered) return;

    setState(() {
      _answered = true;
      _answers[_currentQuestion] = _selectedAnswer;

      if (_selectedAnswer == _questions[_currentQuestion].correctIndex) {
        _score++;
      }
    });
  }

  void _nextQuestion() {
    if (!_answered) return;

    if (_currentQuestion == _questions.length - 1) {
      _showResults();
      return;
    }

    setState(() {
      _currentQuestion++;
      _selectedAnswer = null;
      _answered = false;
    });
  }

  void _restartQuiz() {
    setState(() {
      _currentQuestion = 0;
      _score = 0;
      _selectedAnswer = null;
      _answered = false;
      _answers.fillRange(0, _answers.length, null);
    });
  }

  void _showResults() {
    final corrections = [
      for (var index = 0; index < _questions.length; index++)
        if (_answers[index] != _questions[index].correctIndex)
          _QuizCorrection(
            question: _questions[index].question,
            correctAnswer:
                _questions[index].options[_questions[index].correctIndex],
            explanation: 'Review this answer to reinforce the topic.',
          ),
    ];

    showDialog<void>(
      context: context,
      barrierDismissible: false,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
          ),
          title: const Text('Quiz complete'),
          content: _ScoreFeedback(
            score: _score,
            total: _questions.length,
            corrections: corrections,
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(context);
                _restartQuiz();
              },
              child: const Text('Try again'),
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
    final question = _questions[_currentQuestion];

    return Scaffold(
      appBar: AppBar(title: const Text('Mindfulness Quiz')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              LinearProgressIndicator(
                value: (_currentQuestion + 1) / _questions.length,
                minHeight: 7,
                borderRadius: BorderRadius.circular(20),
                backgroundColor: AppColors.softLavender,
                color: AppColors.primary,
              ),

              const SizedBox(height: 20),

              Text(
                'Question ${_currentQuestion + 1} '
                'of ${_questions.length}',
                style: const TextStyle(
                  color: AppColors.muted,
                  fontWeight: FontWeight.w600,
                ),
              ),

              const SizedBox(height: 12),

              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(22),
                decoration: BoxDecoration(
                  color: AppColors.softSage,
                  borderRadius: BorderRadius.circular(22),
                ),
                child: Text(
                  question.question,
                  style: const TextStyle(
                    color: AppColors.primary,
                    fontSize: 18,
                    fontWeight: FontWeight.w700,
                    height: 1.4,
                  ),
                ),
              ),

              const SizedBox(height: 20),

              ...List.generate(question.options.length, (index) {
                return Padding(
                  padding: const EdgeInsets.only(bottom: 12),
                  child: _AnswerCard(
                    text: question.options[index],
                    optionIndex: index,
                    selected: _selectedAnswer == index,
                    answered: _answered,
                    correct: index == question.correctIndex,
                    onTap: () => _selectAnswer(index),
                  ),
                );
              }),

              const SizedBox(height: 12),

              if (_answered)
                _AnswerFeedback(
                  question: question,
                  selectedAnswer: _selectedAnswer!,
                ),

              const SizedBox(height: 20),

              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: _answered
                      ? _nextQuestion
                      : _selectedAnswer == null
                      ? null
                      : _submitAnswer,
                  child: Text(
                    _answered
                        ? _currentQuestion == _questions.length - 1
                              ? 'See results'
                              : 'Next question'
                        : 'Submit answer',
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ============================================================
// QUIZ QUESTION MODEL
// ============================================================

class _QuizQuestion {
  const _QuizQuestion({
    required this.question,
    required this.options,
    required this.correctIndex,
    required this.explanation,
  });

  final String question;
  final List<String> options;
  final int correctIndex;
  final String explanation;
}

class _AnswerFeedback extends StatelessWidget {
  const _AnswerFeedback({required this.question, required this.selectedAnswer});

  final _QuizQuestion question;
  final int selectedAnswer;

  @override
  Widget build(BuildContext context) {
    final isCorrect = selectedAnswer == question.correctIndex;

    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: isCorrect ? AppColors.softSage : AppColors.softBlush,
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            isCorrect ? 'Correct!' : 'Not quite!',
            style: const TextStyle(
              color: AppColors.primary,
              fontWeight: FontWeight.w700,
              fontSize: 17,
            ),
          ),
          const SizedBox(height: 8),
          Text('Your answer: ${question.options[selectedAnswer]}'),
          const SizedBox(height: 4),
          Text('Correct answer: ${question.options[question.correctIndex]}'),
          const SizedBox(height: 8),
          Text(question.explanation),
        ],
      ),
    );
  }
}

// ============================================================
// ANSWER CARD
// ============================================================

class _AnswerCard extends StatelessWidget {
  const _AnswerCard({
    required this.text,
    required this.optionIndex,
    required this.selected,
    required this.answered,
    required this.correct,
    required this.onTap,
  });

  final String text;
  final int optionIndex;
  final bool selected;
  final bool answered;
  final bool correct;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    Color backgroundColor = AppColors.surface;

    Color borderColor = AppColors.primary.withValues(alpha: 0.12);

    if (answered && correct) {
      backgroundColor = AppColors.softSage;
      borderColor = AppColors.primary;
    } else if (answered && selected) {
      backgroundColor = AppColors.softBlush;
      borderColor = AppColors.primary;
    }

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: answered ? null : onTap,
        borderRadius: BorderRadius.circular(18),
        child: Ink(
          width: double.infinity,
          padding: const EdgeInsets.all(17),
          decoration: BoxDecoration(
            color: backgroundColor,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(color: borderColor, width: 1.5),
          ),
          child: Row(
            children: [
              Container(
                width: 32,
                height: 32,
                decoration: const BoxDecoration(
                  color: AppColors.softLavender,
                  shape: BoxShape.circle,
                ),
                child: Center(
                  child: Text(
                    String.fromCharCode(65 + optionIndex),
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),

              const SizedBox(width: 12),

              Expanded(
                child: Text(
                  text,
                  style: const TextStyle(
                    color: AppColors.primary,
                    fontSize: 14,
                    height: 1.35,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),

              if (answered && correct)
                const Icon(
                  Icons.check_circle_rounded,
                  color: AppColors.primary,
                ),
            ],
          ),
        ),
      ),
    );
  }
}
