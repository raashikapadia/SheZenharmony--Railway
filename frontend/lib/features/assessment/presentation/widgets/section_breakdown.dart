import 'package:flutter/material.dart';

import '../../../../core/theme/app_theme.dart';
import '../../data/assessment_result.dart';

/// "72%", or "72.5%" when the backend's figure has a meaningful decimal.
/// A trailing ".0" is dropped so whole numbers read cleanly.
String formatPercentage(double value) {
  final rounded = (value * 10).roundToDouble() / 10;
  final text = rounded == rounded.roundToDouble()
      ? rounded.toStringAsFixed(0)
      : rounded.toStringAsFixed(1);
  return '$text%';
}

/// How each section of the questionnaire contributed to the result.
///
/// Every figure shown here was computed and stored by the backend — the app
/// only formats it. Sections appear in the order the engine returned them,
/// and the supporting line adapts to whatever the questionnaire was
/// configured to record: raw points, a section weight, both, or neither.
class SectionBreakdown extends StatelessWidget {
  const SectionBreakdown({super.key, required this.sections});

  final List<SectionScore> sections;

  @override
  Widget build(BuildContext context) {
    if (sections.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          'Section Breakdown',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: AppSpacing.xs),
        const Text(
          'How each part of the questionnaire contributed.',
          style: TextStyle(color: AppColors.muted),
        ),
        const SizedBox(height: AppSpacing.md),
        Container(
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(AppRadii.card),
            border: Border.all(color: AppColors.outline),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              for (var i = 0; i < sections.length; i++) ...[
                if (i > 0) const SizedBox(height: AppSpacing.lg),
                _SectionRow(section: sections[i]),
              ],
            ],
          ),
        ),
      ],
    );
  }
}

class _SectionRow extends StatelessWidget {
  const _SectionRow({required this.section});

  final SectionScore section;

  /// "12 of 20 points · 25% of the result" — only the parts the
  /// questionnaire actually configured.
  String? get _detail {
    final parts = <String>[];
    final raw = section.rawScore;
    final max = section.maxPossibleScore;
    if (raw != null && max != null) {
      parts.add('${_points(raw)} of ${_points(max)} points');
    }
    final weight = section.weight;
    if (weight != null && weight > 0) {
      parts.add('${formatPercentage(weight)} of the result');
    }
    return parts.isEmpty ? null : parts.join(' · ');
  }

  static String _points(double value) => value == value.roundToDouble()
      ? value.toStringAsFixed(0)
      : value.toStringAsFixed(1);

  @override
  Widget build(BuildContext context) {
    final percentage = section.percentage;
    final detail = _detail;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Text(
                section.title,
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w700),
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            Text(
              formatPercentage(percentage),
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                fontWeight: FontWeight.w800,
                color: AppColors.primary,
              ),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.sm),
        ClipRRect(
          borderRadius: BorderRadius.circular(AppRadii.pill),
          child: LinearProgressIndicator(
            value: (percentage / 100).clamp(0.0, 1.0),
            minHeight: 6,
            backgroundColor: AppColors.softLavender,
            semanticsLabel: section.title,
            semanticsValue: formatPercentage(percentage),
          ),
        ),
        if (detail != null) ...[
          const SizedBox(height: AppSpacing.xs),
          Text(
            detail,
            style: const TextStyle(color: AppColors.muted, fontSize: 12.5),
          ),
        ],
      ],
    );
  }
}
