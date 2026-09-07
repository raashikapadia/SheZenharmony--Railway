import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

/// Cover washes a student can pick for a diary. Stored by index, so entries
/// added here must go on the end to keep existing covers pointing at the same
/// colour.
const diaryCoverColors = <Color>[
  AppColors.softLavender,
  AppColors.softBlush,
  AppColors.softSage,
  AppColors.softGold,
  Color(0xFFE7F0F6),
  Color(0xFFF6EBF3),
];

Color diaryCoverColor(int index) =>
    diaryCoverColors[index % diaryCoverColors.length];

const _monthNames = [
  'Jan',
  'Feb',
  'Mar',
  'Apr',
  'May',
  'Jun',
  'Jul',
  'Aug',
  'Sep',
  'Oct',
  'Nov',
  'Dec',
];

/// Short, friendly date for diary lists — "Today", "Yesterday", "8 Sep", or
/// "8 Sep 2025" once the year differs. Written by hand rather than pulling in
/// `intl` for one format.
String formatDiaryDate(DateTime value, {DateTime? now}) {
  final today = now ?? DateTime.now();
  final day = DateTime(value.year, value.month, value.day);
  final reference = DateTime(today.year, today.month, today.day);
  final difference = reference.difference(day).inDays;

  if (difference == 0) return 'Today';
  if (difference == 1) return 'Yesterday';

  final month = _monthNames[value.month - 1];
  return value.year == today.year
      ? '${value.day} $month'
      : '${value.day} $month ${value.year}';
}

/// The promise the feature is built around, stated plainly wherever a student
/// is about to write something.
class DiaryPrivacyNote extends StatelessWidget {
  const DiaryPrivacyNote({super.key});

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(AppSpacing.lg),
    decoration: BoxDecoration(
      color: AppColors.softLavender,
      borderRadius: BorderRadius.circular(AppRadii.card),
      border: Border.all(color: AppColors.primary.withValues(alpha: 0.1)),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          width: 38,
          height: 38,
          decoration: const BoxDecoration(
            color: AppColors.surface,
            shape: BoxShape.circle,
          ),
          child: const Icon(
            Icons.lock_rounded,
            size: 19,
            color: AppColors.primary,
          ),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Only on this phone',
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(height: 3),
              const Text(
                'Your diary is never sent to SheZen, so nobody on the team '
                'can read it. It also means it is erased if you uninstall '
                'the app.',
                style: TextStyle(
                  color: AppColors.muted,
                  fontSize: 12.5,
                  height: 1.4,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

/// Cover swatch with a book glyph, used at both list sizes.
class DiaryCover extends StatelessWidget {
  const DiaryCover({
    super.key,
    required this.coverIndex,
    this.size = 56,
    this.icon = Icons.menu_book_rounded,
  });

  final int coverIndex;
  final double size;
  final IconData icon;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      color: diaryCoverColor(coverIndex),
      borderRadius: BorderRadius.circular(size * 0.32),
      border: Border.all(color: AppColors.primary.withValues(alpha: 0.08)),
    ),
    child: Icon(icon, color: AppColors.primary, size: size * 0.44),
  );
}
