import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_theme.dart';
import '../../activities/presentation/wellbeing_activities_screen.dart';
import '../../auth/application/auth_provider.dart';
import '../data/assessment_result.dart';
import 'widgets/recommended_support.dart';
import 'widgets/section_breakdown.dart';

class AssessmentResultScreen extends StatelessWidget {
  const AssessmentResultScreen({
    super.key,
    required this.result,
    this.mandatory = false,
  });

  final AssessmentResult result;
  final bool mandatory;

  void _continue(BuildContext context) {
    if (mandatory) context.read<AuthProvider>().markAssessmentCompleted();
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return PopScope(
      canPop: !mandatory,
      child: Scaffold(
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(24),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 520),
                child: Column(
                  children: [
                    Container(
                      width: 84,
                      height: 84,
                      decoration: BoxDecoration(
                        color: colors.primaryContainer,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        Icons.check_rounded,
                        size: 46,
                        color: colors.primary,
                      ),
                    ),
                    const SizedBox(height: 22),
                    Text(
                      'Check-in complete',
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(fontWeight: FontWeight.w800),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'Thank you for taking a moment for yourself.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: colors.onSurfaceVariant),
                    ),
                    const SizedBox(height: 26),
                    Card(
                      color: colors.primaryContainer,
                      child: Padding(
                        padding: const EdgeInsets.all(24),
                        child: Column(
                          children: [
                            Text(
                              'Your Result',
                              style: Theme.of(context).textTheme.titleSmall
                                  ?.copyWith(color: colors.onPrimaryContainer),
                            ),
                            const SizedBox(height: 10),
                            Text(
                              '${result.totalScore} / ${result.scoreOutOf}',
                              textAlign: TextAlign.center,
                              style: Theme.of(context).textTheme.displaySmall
                                  ?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    color: colors.primary,
                                  ),
                            ),
                            // The percentage is only meaningful when the
                            // result is not already reported out of 100.
                            if (result.percentage != null &&
                                result.scoreOutOf != 100) ...[
                              const SizedBox(height: 4),
                              Text(
                                '${formatPercentage(result.percentage!)} of the maximum',
                                style: TextStyle(
                                  color: colors.onPrimaryContainer,
                                ),
                              ),
                            ],
                            const SizedBox(height: 18),
                            Text(
                              'Result Level',
                              style: Theme.of(context).textTheme.titleSmall
                                  ?.copyWith(color: colors.onPrimaryContainer),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              result.bandLabel,
                              textAlign: TextAlign.center,
                              style: Theme.of(context).textTheme.headlineSmall
                                  ?.copyWith(
                                    fontWeight: FontWeight.w800,
                                    color: colors.primary,
                                  ),
                            ),
                            if (result.bandDescription?.isNotEmpty == true) ...[
                              const SizedBox(height: 6),
                              Text(
                                result.bandDescription!,
                                textAlign: TextAlign.center,
                                style: TextStyle(
                                  color: colors.onPrimaryContainer,
                                ),
                              ),
                            ],
                            const SizedBox(height: 14),
                            Text(
                              'Assessment Date',
                              style: Theme.of(context).textTheme.titleSmall
                                  ?.copyWith(color: colors.onPrimaryContainer),
                            ),
                            const SizedBox(height: 4),
                            Text(
                              formatLongDate(result.completedAt),
                              style: const TextStyle(
                                fontWeight: FontWeight.w700,
                              ),
                            ),
                          ],
                        ),
                      ),
                    ),
                    const SizedBox(height: 22),
                    Container(
                      width: double.infinity,
                      padding: const EdgeInsets.all(AppSpacing.lg),
                      decoration: BoxDecoration(
                        color: AppColors.softSage,
                        borderRadius: BorderRadius.circular(AppRadii.card),
                      ),
                      child: Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          const Icon(
                            Icons.favorite_outline_rounded,
                            color: AppColors.primary,
                          ),
                          const SizedBox(width: AppSpacing.md),
                          Expanded(
                            child: Text(
                              _supportiveMessage(result),
                              style: Theme.of(context).textTheme.bodyLarge,
                            ),
                          ),
                        ],
                      ),
                    ),
                    if (result.sections.isNotEmpty) ...[
                      const SizedBox(height: 22),
                      SectionBreakdown(sections: result.sections),
                    ],
                    if (result.recommendedInterventions.isNotEmpty) ...[
                      const SizedBox(height: 22),
                      RecommendedSupportSection(
                        items: result.recommendedInterventions,
                      ),
                    ],
                    const SizedBox(height: 22),
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Icon(Icons.info_outline_rounded, color: colors.primary),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'This result comes from today\'s check-in and is not a medical diagnosis. Your wellbeing can change over time.',
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(
                                  color: colors.onSurfaceVariant,
                                  height: 1.45,
                                ),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 30),
                    SizedBox(
                      width: double.infinity,
                      child: FilledButton(
                        onPressed: () => _continue(context),
                        child: Text(
                          mandatory ? 'Continue to SheZen' : 'Back to Home',
                        ),
                      ),
                    ),
                    if (!mandatory) ...[
                      const SizedBox(height: AppSpacing.md),
                      SizedBox(
                        width: double.infinity,
                        child: OutlinedButton.icon(
                          onPressed: () => Navigator.of(context).push(
                            MaterialPageRoute(
                              builder: (_) => const WellbeingActivitiesScreen(),
                            ),
                          ),
                          icon: const Icon(Icons.spa_outlined),
                          label: const Text('View wellbeing activities'),
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  /// The message the admin wrote for the matched level. Nothing here guesses
  /// from the level's name — a questionnaire may call its levels anything.
  String _supportiveMessage(AssessmentResult result) {
    final message = result.bandMessage?.trim();
    if (message != null && message.isNotEmpty) return message;
    return 'Keep noticing what supports your wellbeing. Small, regular moments of rest can help you stay connected to how you feel.';
  }
}
