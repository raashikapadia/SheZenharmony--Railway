/// Shared furniture for the games that teach something a round at a time.
///
/// Coping Match, Myth or Fact and Body Signals all run the same loop: pose a
/// situation, take one answer, explain what the answer means, move on. Only
/// the middle step differs between them, so the progress header, the
/// explanation panel and the closing summary live here rather than being
/// written out three times.
library;

import 'package:flutter/material.dart';

import '../../../../core/theme/app_theme.dart';

/// How far through the round the player is, and how they are doing.
class LearningProgressBar extends StatelessWidget {
  const LearningProgressBar({
    super.key,
    required this.index,
    required this.total,
    required this.correct,
    required this.tint,
  });

  /// Zero-based position of the question on screen.
  final int index;
  final int total;
  final int correct;
  final Color tint;

  @override
  Widget build(BuildContext context) {
    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        color: tint,
        borderRadius: BorderRadius.circular(AppRadii.input),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                'Question ${index + 1} of $total',
                style: const TextStyle(
                  fontWeight: FontWeight.w700,
                  color: AppColors.primary,
                ),
              ),
              Text(
                '$correct right so far',
                style: const TextStyle(color: AppColors.muted),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          ClipRRect(
            borderRadius: BorderRadius.circular(AppRadii.pill),
            child: LinearProgressIndicator(
              value: total == 0 ? 0 : (index + 1) / total,
              minHeight: 8,
              backgroundColor: AppColors.surface,
              valueColor: const AlwaysStoppedAnimation(AppColors.primary),
            ),
          ),
        ],
      ),
    );
  }
}

/// The teaching half of a round: what the answer was, and why.
///
/// A wrong answer is never scolded. The panel leads with the correct answer
/// so the takeaway is the thing the player reads first, and the tone stays
/// the same whether they got it right or not — this is a learning game, and
/// a student who is already stressed does not need another thing to fail at.
class LearningExplanation extends StatelessWidget {
  const LearningExplanation({
    super.key,
    required this.wasCorrect,
    required this.heading,
    required this.body,
    required this.onNext,
    required this.isLast,
  });

  final bool wasCorrect;

  /// The answer itself, stated plainly.
  final String heading;

  /// Why that answer is the helpful one.
  final String body;

  final VoidCallback onNext;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(AppSpacing.xl),
      decoration: BoxDecoration(
        color: wasCorrect ? AppColors.softSage : AppColors.softPeach,
        borderRadius: BorderRadius.circular(AppRadii.input),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Icon(
                wasCorrect
                    ? Icons.check_circle_rounded
                    : Icons.lightbulb_rounded,
                color: AppColors.primary,
              ),
              const SizedBox(width: AppSpacing.sm),
              Expanded(
                child: Text(
                  wasCorrect ? 'That\'s it' : 'Worth knowing',
                  style: const TextStyle(
                    fontWeight: FontWeight.w800,
                    color: AppColors.primary,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.md),
          Text(
            heading,
            style: const TextStyle(
              fontWeight: FontWeight.w700,
              color: AppColors.ink,
              height: 1.4,
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            body,
            style: const TextStyle(color: AppColors.muted, height: 1.5),
          ),
          const SizedBox(height: AppSpacing.lg),
          SizedBox(
            width: double.infinity,
            child: FilledButton(
              onPressed: onNext,
              child: Text(isLast ? 'See how you did' : 'Next'),
            ),
          ),
        ],
      ),
    );
  }
}

/// Closes a round.
///
/// The score is reported without a pass mark: the point of these games is the
/// explanation that followed each answer, not the tally.
Future<void> showLearningSummary(
  BuildContext context, {
  required int correct,
  required int total,
  required String takeaway,
  required VoidCallback onPlayAgain,
}) {
  return showDialog<void>(
    context: context,
    barrierDismissible: false,
    builder: (dialogContext) => AlertDialog(
      title: const Text('Round finished'),
      content: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            'You got $correct of $total.',
            style: const TextStyle(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            takeaway,
            style: const TextStyle(color: AppColors.muted, height: 1.45),
          ),
        ],
      ),
      actions: [
        TextButton(
          onPressed: () {
            Navigator.pop(dialogContext);
            onPlayAgain();
          },
          child: const Text('Play Again'),
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
