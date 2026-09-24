import 'package:flutter/material.dart';

import '../../../../core/theme/app_theme.dart';
import 'learning_game_chrome.dart';

/// Matches a stressful student moment to a coping strategy that fits it.
///
/// The other games in this section give a student somewhere to put a hard
/// moment. This one builds the vocabulary to name what would help, which is
/// what the personal guidance and recommendation screens go on to use. The
/// wrong options are deliberately the ones students actually reach for —
/// avoidance, rumination, pushing through — so the explanation has something
/// real to push against.
class CopingMatchScreen extends StatefulWidget {
  const CopingMatchScreen({super.key});

  @override
  State<CopingMatchScreen> createState() => _CopingMatchScreenState();
}

class _CopingMatchScreenState extends State<CopingMatchScreen> {
  /// Fixed order. A round is short, and walking the same path each time lets
  /// a student come back to a scenario they remember getting wrong.
  static const _scenarios = <_Scenario>[
    _Scenario(
      situation:
          'Three deadlines land in the same week and you cannot decide '
          'what to start.',
      options: [
        'Break each one into small steps and start with the smallest',
        'Stay up all night and try to finish everything at once',
        'Wait until you feel motivated enough to begin',
      ],
      answer: 0,
      why:
          'A pile of work feels like one impossible task until you split it '
          'up. Starting with the smallest step is not avoidance — it gets you '
          'moving, and momentum is easier to keep than to find.',
    ),
    _Scenario(
      situation: 'Your heart is racing in the minutes before a presentation.',
      options: [
        'Breathe out for longer than you breathe in, a few times',
        'Have another coffee to sharpen up',
        'Tell yourself firmly to stop panicking',
      ],
      answer: 0,
      why:
          'A long out-breath is the one part of the stress response you can '
          'steer directly — it settles your heart rate within a minute or two. '
          'Ordering yourself to calm down does the opposite, and caffeine adds '
          'to exactly the symptoms you are trying to settle.',
    ),
    _Scenario(
      situation:
          'You argued with a close friend and keep replaying it in your head.',
      options: [
        'Write down what you actually feel, then pick one thing to say',
        'Read back through the messages again looking for what went wrong',
        'Say nothing and wait for it to blow over',
      ],
      answer: 0,
      why:
          'Replaying an argument feels like problem-solving, but it just keeps '
          'the feeling switched on. Putting it into words moves it from a loop '
          'in your head to something you can actually do something about.',
    ),
    _Scenario(
      situation: 'It is one in the morning and your mind will not switch off.',
      options: [
        'Get up and do something calm and dim for a few minutes',
        'Scroll your phone until you feel sleepy',
        'Lie still and try harder to fall asleep',
      ],
      answer: 0,
      why:
          'Lying awake trying to sleep teaches your body that bed is a place '
          'to be alert. Getting up briefly breaks that link. A screen keeps '
          'you alert and hands your racing mind more to chew on.',
    ),
    _Scenario(
      situation: 'You failed a module and feel like giving up on the course.',
      options: [
        'Ask your tutor what went wrong and what the options are now',
        'Avoid opening the results page again',
        'Accept that you are probably not cut out for this',
      ],
      answer: 0,
      why:
          'One result is information about one attempt, not a verdict on you. '
          'Tutors deal with this constantly and usually know about resits and '
          'support you have not heard of — but only if you ask.',
    ),
    _Scenario(
      situation:
          'You are running on empty and people keep asking you for favours.',
      options: [
        'Turn down one thing this week without explaining yourself',
        'Say yes now and cancel nearer the time',
        'Take it all on so that nobody is disappointed',
      ],
      answer: 0,
      why:
          'A boundary is a skill, not a rejection, and it does not need a '
          'justification to be valid. Saying yes and cancelling later costs '
          'you the worry in between and costs them the notice.',
    ),
  ];

  int _index = 0;
  int _correct = 0;
  int? _chosen;

  _Scenario get _current => _scenarios[_index];
  bool get _hasAnswered => _chosen != null;
  bool get _isLast => _index == _scenarios.length - 1;

  void _choose(int option) {
    if (_hasAnswered) return;
    setState(() {
      _chosen = option;
      if (option == _current.answer) _correct++;
    });
  }

  void _next() {
    if (!_isLast) {
      setState(() {
        _index++;
        _chosen = null;
      });
      return;
    }

    showLearningSummary(
      context,
      correct: _correct,
      total: _scenarios.length,
      takeaway:
          'There is rarely one right answer to a hard week — but naming what '
          'would help is the part that gets easier with practice.',
      onPlayAgain: _restart,
    );
  }

  void _restart() {
    setState(() {
      _index = 0;
      _correct = 0;
      _chosen = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Coping Match')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              LearningProgressBar(
                index: _index,
                total: _scenarios.length,
                correct: _correct,
                tint: AppColors.softSky,
              ),

              const SizedBox(height: AppSpacing.xl),

              const Text(
                'WHAT WOULD HELP?',
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  color: AppColors.primary,
                  letterSpacing: 1.2,
                  fontSize: 12,
                ),
              ),

              const SizedBox(height: AppSpacing.sm),

              Text(
                _current.situation,
                style: const TextStyle(
                  fontSize: 20,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ink,
                  height: 1.35,
                ),
              ),

              const SizedBox(height: AppSpacing.xl),

              for (var option = 0; option < _current.options.length; option++)
                Padding(
                  padding: const EdgeInsets.only(bottom: AppSpacing.md),
                  child: _OptionTile(
                    label: _current.options[option],
                    // Once answered, the helpful strategy is always marked,
                    // so a student who guessed wrong still leaves knowing
                    // which one it was.
                    state: !_hasAnswered
                        ? _OptionState.open
                        : option == _current.answer
                        ? _OptionState.helpful
                        : option == _chosen
                        ? _OptionState.chosenInstead
                        : _OptionState.open,
                    onTap: () => _choose(option),
                  ),
                ),

              if (_hasAnswered) ...[
                const SizedBox(height: AppSpacing.sm),
                LearningExplanation(
                  wasCorrect: _chosen == _current.answer,
                  heading: _current.options[_current.answer],
                  body: _current.why,
                  onNext: _next,
                  isLast: _isLast,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

enum _OptionState { open, helpful, chosenInstead }

class _OptionTile extends StatelessWidget {
  const _OptionTile({
    required this.label,
    required this.state,
    required this.onTap,
  });

  final String label;
  final _OptionState state;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final (background, border, icon) = switch (state) {
      _OptionState.open => (AppColors.surface, AppColors.outline, null),
      _OptionState.helpful => (
        AppColors.softSage,
        AppColors.primary,
        Icons.check_circle_rounded,
      ),
      _OptionState.chosenInstead => (
        AppColors.softCoral,
        AppColors.secondary,
        Icons.remove_circle_outline_rounded,
      ),
    };

    return Material(
      color: background,
      borderRadius: BorderRadius.circular(AppRadii.input),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.input),
        child: Container(
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadii.input),
            border: Border.all(color: border, width: 1.5),
          ),
          child: Row(
            children: [
              Expanded(
                child: Text(
                  label,
                  style: const TextStyle(color: AppColors.ink, height: 1.4),
                ),
              ),
              if (icon != null) ...[
                const SizedBox(width: AppSpacing.sm),
                Icon(icon, color: border, size: 20),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

class _Scenario {
  const _Scenario({
    required this.situation,
    required this.options,
    required this.answer,
    required this.why,
  });

  final String situation;
  final List<String> options;

  /// Index into [options] of the strategy the explanation backs.
  final int answer;
  final String why;
}
