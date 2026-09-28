import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_theme.dart';
import '../../activities/presentation/games/games_quizzes_screen.dart';
import '../../activities/presentation/positive_engagement_screen.dart';
import '../../activities/presentation/wellbeing_hub_screen.dart';
import '../data/personal_guidance.dart';

/// The fuller explanation behind a piece of guidance: when it helps, the steps
/// to try, and a way through to the rest of the wellbeing experience.
Future<void> showGuidanceDetail(
  BuildContext context,
  PersonalGuidance guidance,
) => showModalBottomSheet<void>(
  context: context,
  isScrollControlled: true,
  showDragHandle: true,
  builder: (sheetContext) => DraggableScrollableSheet(
    expand: false,
    initialChildSize: 0.75,
    maxChildSize: 0.95,
    builder: (_, controller) =>
        _GuidanceDetail(guidance: guidance, controller: controller),
  ),
);

class _GuidanceDetail extends StatelessWidget {
  const _GuidanceDetail({required this.guidance, required this.controller});

  final PersonalGuidance guidance;
  final ScrollController controller;

  @override
  Widget build(BuildContext context) => ListView(
    controller: controller,
    padding: const EdgeInsets.fromLTRB(24, 4, 24, 32),
    children: [
      Text(
        guidance.title ?? 'Guidance',
        style: Theme.of(context).textTheme.headlineSmall,
      ),

      if (guidance.durationMinutes != null) ...[
        const SizedBox(height: AppSpacing.xs),
        Row(
          children: [
            const Icon(
              Icons.schedule_rounded,
              size: 16,
              color: AppColors.muted,
            ),
            const SizedBox(width: 6),
            Text(
              'Takes about ${guidance.durationMinutes} minutes',
              style: const TextStyle(color: AppColors.muted),
            ),
          ],
        ),
      ],

      if (guidance.whenItHelps != null) ...[
        const SizedBox(height: AppSpacing.lg),
        _Label(text: 'When this might help'),
        const SizedBox(height: AppSpacing.xs),
        Text(
          guidance.whenItHelps!,
          style: Theme.of(context).textTheme.bodyLarge,
        ),
      ],

      if (guidance.steps.isNotEmpty) ...[
        const SizedBox(height: AppSpacing.lg),
        _Label(text: 'How to try it'),
        const SizedBox(height: AppSpacing.sm),
        for (var i = 0; i < guidance.steps.length; i++)
          Padding(
            padding: const EdgeInsets.only(bottom: AppSpacing.sm),
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Container(
                  width: 26,
                  height: 26,
                  alignment: Alignment.center,
                  decoration: const BoxDecoration(
                    color: AppColors.softLavender,
                    shape: BoxShape.circle,
                  ),
                  child: Text(
                    '${i + 1}',
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w700,
                      color: AppColors.primary,
                    ),
                  ),
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: Text(
                    guidance.steps[i],
                    style: Theme.of(context).textTheme.bodyLarge,
                  ),
                ),
              ],
            ),
          ),
      ],

      if (guidance.content.trim().isNotEmpty) ...[
        const SizedBox(height: AppSpacing.lg),
        _Label(text: 'Why this helps'),
        const SizedBox(height: AppSpacing.xs),
        Text(
          guidance.content,
          style: Theme.of(context).textTheme.bodyLarge?.copyWith(height: 1.5),
        ),
      ],

      if (guidance.resourceUrl != null) ...[
        const SizedBox(height: AppSpacing.lg),
        _ResourceLinkButton(url: guidance.resourceUrl!),
      ],

      if (guidance.relatedActivity != null) ...[
        const SizedBox(height: AppSpacing.lg),
        _Label(text: 'An activity that goes with this'),
        const SizedBox(height: AppSpacing.sm),
        Container(
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(
            color: AppColors.softSage,
            borderRadius: BorderRadius.circular(AppRadii.card),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  const Icon(Icons.spa_rounded, color: AppColors.primary),
                  const SizedBox(width: AppSpacing.sm),
                  Expanded(
                    child: Text(
                      guidance.relatedActivity!.title,
                      style: Theme.of(context).textTheme.titleSmall,
                    ),
                  ),
                ],
              ),
              if (guidance.relatedActivity!.instructions.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.sm),
                Text(guidance.relatedActivity!.instructions),
              ],
            ],
          ),
        ),
      ],

      const SizedBox(height: AppSpacing.xxl),
      _Label(text: 'Where to go next'),
      const SizedBox(height: AppSpacing.sm),
      _NextStep(
        icon: Icons.air_rounded,
        label: 'Wellbeing activities',
        description: 'Breathing, grounding, and mindful breaks.',
        onTap: () => _replace(context, const WellbeingHubScreen()),
      ),
      const SizedBox(height: AppSpacing.sm),
      _NextStep(
        icon: Icons.videogame_asset_rounded,
        label: 'Games and quizzes',
        description: 'A light distraction when you need one.',
        onTap: () => _replace(
          context,
          const GamesQuizzesScreen(focus: GamesQuizzesSection.games),
        ),
      ),
      const SizedBox(height: AppSpacing.sm),
      _NextStep(
        icon: Icons.wb_sunny_rounded,
        label: 'A little encouragement',
        description: 'Short motivational messages.',
        onTap: () => _replace(context, const PositiveEngagementScreen()),
      ),
    ],
  );

  /// Closes the sheet before navigating, so the student lands on a clean screen
  /// and Back returns them to the toolkit.
  void _replace(BuildContext context, Widget screen) {
    final navigator = Navigator.of(context);
    navigator.pop();
    navigator.push(MaterialPageRoute(builder: (_) => screen));
  }
}

/// A plain sentence-case heading. Deliberately not upper-case: all-caps runs
/// are slower to read, and this screen should feel calm rather than shouty.
class _Label extends StatelessWidget {
  const _Label({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Text(
    text,
    style: Theme.of(
      context,
    ).textTheme.titleSmall?.copyWith(color: AppColors.primary),
  );
}

/// A tappable card for the optional external link an admin attached to a
/// piece of advice — a helpline page, an article, or similar.
class _ResourceLinkButton extends StatelessWidget {
  const _ResourceLinkButton({required this.url});

  final String url;

  Future<void> _open(BuildContext context) async {
    final messenger = ScaffoldMessenger.of(context);
    final uri = Uri.tryParse(url);
    if (uri == null) return;

    var opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened) {
      opened = await launchUrl(uri, mode: LaunchMode.platformDefault);
    }
    if (!opened && context.mounted) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Could not open this resource.')),
      );
    }
  }

  @override
  Widget build(BuildContext context) => Material(
    color: AppColors.softLavender,
    borderRadius: BorderRadius.circular(AppRadii.card),
    child: InkWell(
      borderRadius: BorderRadius.circular(AppRadii.card),
      onTap: () => _open(context),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Row(
          children: [
            const Icon(Icons.open_in_new_rounded, color: AppColors.primary),
            const SizedBox(width: AppSpacing.md),
            const Expanded(
              child: Text(
                'Visit this helpful resource',
                style: TextStyle(
                  color: AppColors.primary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
            const Icon(Icons.chevron_right_rounded, color: AppColors.primary),
          ],
        ),
      ),
    ),
  );
}

class _NextStep extends StatelessWidget {
  const _NextStep({
    required this.icon,
    required this.label,
    required this.description,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final String description;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: AppColors.softBlush,
    borderRadius: BorderRadius.circular(AppRadii.card),
    child: InkWell(
      borderRadius: BorderRadius.circular(AppRadii.card),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Row(
          children: [
            Icon(icon, color: AppColors.primary),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(label, style: Theme.of(context).textTheme.titleSmall),
                  Text(
                    description,
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 12,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded),
          ],
        ),
      ),
    ),
  );
}
