import 'package:flutter/material.dart';

import '../../../../core/theme/app_theme.dart';
import 'learning_game_chrome.dart';

/// Sorts common claims about stress and mental health into myth or fact.
///
/// Most of what students believe about coping was absorbed rather than
/// learned, and the beliefs that stop someone asking for help are usually the
/// unexamined ones. Each card names a claim plainly and then explains it, so
/// the game leaves behind a reason rather than a verdict.
class MythOrFactScreen extends StatefulWidget {
  const MythOrFactScreen({super.key});

  @override
  State<MythOrFactScreen> createState() => _MythOrFactScreenState();
}

class _MythOrFactScreenState extends State<MythOrFactScreen> {
  /// Deliberately mixed so the answer is never a run of one button, and
  /// deliberately short — this is a two-minute game, not a lecture.
  static const _claims = <_Claim>[
    _Claim(
      statement: 'Stress is always bad for you.',
      isFact: false,
      why:
          'Short bursts of stress sharpen focus and help you meet a deadline. '
          'What wears people down is stress that never lets up and never gets '
          'a recovery period after it.',
    ),
    _Claim(
      statement: 'How well you slept changes how well you cope tomorrow.',
      isFact: true,
      why:
          'A short night leaves the emotional part of the brain more reactive '
          'the next day. The same small problem genuinely does feel bigger on '
          'five hours of sleep — you are not being dramatic.',
    ),
    _Claim(
      statement: 'Talking about how you feel usually makes it worse.',
      isFact: false,
      why:
          'Putting a feeling into words to someone you trust tends to lower '
          'its intensity rather than raise it. Avoiding it is what keeps it '
          'at full strength.',
    ),
    _Claim(
      statement: 'You need a diagnosis before you deserve support.',
      isFact: false,
      why:
          'Support is for anyone who is struggling. You do not have to earn '
          'it with a label, and waiting until things are bad enough to qualify '
          'usually means waiting longer than you needed to.',
    ),
    _Claim(
      statement: 'Anxiety conditions often start before the age of 25.',
      isFact: true,
      why:
          'Most mental health conditions first appear in adolescence or early '
          'adulthood. Being young is not a reason to assume what you are '
          'feeling is just a phase.',
    ),
    _Claim(
      statement: 'Someone who seems fine cannot be struggling underneath.',
      isFact: false,
      why:
          'Distress is often well hidden, especially by people who are used to '
          'holding things together for everyone else. Seeming fine is a skill, '
          'not evidence.',
    ),
    _Claim(
      statement: 'Regular movement can genuinely lift a low mood.',
      isFact: true,
      why:
          'Regular exercise is one of the better-supported everyday things for '
          'mood. It is not a replacement for support when you need it, but the '
          'effect is real and it does not take much.',
    ),
  ];

  int _index = 0;
  int _correct = 0;
  bool? _answeredFact;

  _Claim get _current => _claims[_index];
  bool get _hasAnswered => _answeredFact != null;
  bool get _isLast => _index == _claims.length - 1;

  void _answer(bool saidFact) {
    if (_hasAnswered) return;
    setState(() {
      _answeredFact = saidFact;
      if (saidFact == _current.isFact) _correct++;
    });
  }

  void _next() {
    if (!_isLast) {
      setState(() {
        _index++;
        _answeredFact = null;
      });
      return;
    }

    showLearningSummary(
      context,
      correct: _correct,
      total: _claims.length,
      takeaway:
          'The beliefs worth checking are the ones you never questioned — '
          'especially the ones that would stop you asking for help.',
      onPlayAgain: _restart,
    );
  }

  void _restart() {
    setState(() {
      _index = 0;
      _correct = 0;
      _answeredFact = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Myth or Fact')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              LearningProgressBar(
                index: _index,
                total: _claims.length,
                correct: _correct,
                tint: AppColors.softLavender,
              ),

              const SizedBox(height: AppSpacing.xl),

              // ============================================================
              // THE CLAIM
              // ============================================================
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(AppSpacing.xxl),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(AppRadii.card),
                  border: Border.all(color: AppColors.outline, width: 1.5),
                ),
                child: Column(
                  children: [
                    const Icon(
                      Icons.format_quote_rounded,
                      color: AppColors.brand,
                      size: 32,
                    ),
                    const SizedBox(height: AppSpacing.md),
                    Text(
                      _current.statement,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        fontSize: 20,
                        fontWeight: FontWeight.w700,
                        color: AppColors.ink,
                        height: 1.4,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: AppSpacing.xl),

              Row(
                children: [
                  Expanded(
                    child: _VerdictButton(
                      label: 'Myth',
                      icon: Icons.close_rounded,
                      tint: AppColors.softCoral,
                      // After an answer both buttons show what was true, so
                      // the card teaches even when the guess was wrong.
                      revealed: _hasAnswered,
                      isTruth: !_current.isFact,
                      onTap: () => _answer(false),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: _VerdictButton(
                      label: 'Fact',
                      icon: Icons.check_rounded,
                      tint: AppColors.softSage,
                      revealed: _hasAnswered,
                      isTruth: _current.isFact,
                      onTap: () => _answer(true),
                    ),
                  ),
                ],
              ),

              if (_hasAnswered) ...[
                const SizedBox(height: AppSpacing.xl),
                LearningExplanation(
                  wasCorrect: _answeredFact == _current.isFact,
                  heading: _current.isFact
                      ? 'That one is true.'
                      : 'That one is a myth.',
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

class _VerdictButton extends StatelessWidget {
  const _VerdictButton({
    required this.label,
    required this.icon,
    required this.tint,
    required this.revealed,
    required this.isTruth,
    required this.onTap,
  });

  final String label;
  final IconData icon;
  final Color tint;

  /// Whether the answer for this card has been given yet.
  final bool revealed;

  /// Whether this button is the true answer for the card on screen.
  final bool isTruth;

  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    // Before answering both buttons look equally available; afterwards the
    // true one stays lit and the other recedes.
    final highlighted = !revealed || isTruth;

    return Material(
      color: highlighted ? tint : AppColors.background,
      borderRadius: BorderRadius.circular(AppRadii.input),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.input),
        child: Container(
          padding: const EdgeInsets.symmetric(vertical: AppSpacing.xl),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadii.input),
            border: Border.all(
              color: highlighted ? AppColors.primary : AppColors.outline,
              width: 1.5,
            ),
          ),
          child: Column(
            children: [
              Icon(
                icon,
                color: highlighted ? AppColors.primary : AppColors.muted,
              ),
              const SizedBox(height: AppSpacing.xs),
              Text(
                label,
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  color: highlighted ? AppColors.primary : AppColors.muted,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _Claim {
  const _Claim({
    required this.statement,
    required this.isFact,
    required this.why,
  });

  final String statement;
  final bool isFact;
  final String why;
}
