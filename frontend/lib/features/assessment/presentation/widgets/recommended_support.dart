import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../../core/theme/app_theme.dart';
import '../../data/assessment_result.dart';

const _months = [
  'January',
  'February',
  'March',
  'April',
  'May',
  'June',
  'July',
  'August',
  'September',
  'October',
  'November',
  'December',
];

/// "2 September 2026" — used on the result and detail screens.
String formatLongDate(DateTime? date) {
  if (date == null) return 'Today';
  final local = date.toLocal();
  return '${local.day} ${_months[local.month - 1]} ${local.year}';
}

/// Renders the admin-published interventions matched to the student's stress
/// band. Hidden entirely when there is nothing to show. Items are never
/// hardcoded — the list comes straight from the API response.
class RecommendedSupportSection extends StatelessWidget {
  const RecommendedSupportSection({super.key, required this.items});

  final List<RecommendedIntervention> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          'Recommended Support',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: AppSpacing.sm),
        for (final item in items) ...[
          _SupportCard(item: item),
          const SizedBox(height: AppSpacing.sm),
        ],
      ],
    );
  }
}

class _SupportCard extends StatelessWidget {
  const _SupportCard({required this.item});

  final RecommendedIntervention item;

  @override
  Widget build(BuildContext context) {
    return Card(
      clipBehavior: Clip.antiAlias,
      child: ListTile(
        leading: const CircleAvatar(
          backgroundColor: AppColors.softSage,
          child: Icon(Icons.favorite_outline_rounded, color: AppColors.primary),
        ),
        title: Text(item.title),
        subtitle: item.description != null && item.description!.isNotEmpty
            ? Text(
                item.description!,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              )
            : null,
        trailing: const Icon(Icons.chevron_right_rounded),
        onTap: () => showModalBottomSheet<void>(
          context: context,
          showDragHandle: true,
          isScrollControlled: true,
          builder: (_) => _SupportDetailSheet(item: item),
        ),
      ),
    );
  }
}

class _SupportDetailSheet extends StatelessWidget {
  const _SupportDetailSheet({required this.item});

  final RecommendedIntervention item;

  @override
  Widget build(BuildContext context) {
    final url = item.externalUrl;
    return SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.xl,
          0,
          AppSpacing.xl,
          AppSpacing.xl,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              item.title,
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
            ),
            if (item.description != null && item.description!.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                item.description!,
                style: const TextStyle(color: AppColors.muted),
              ),
            ],
            if (item.instructions != null && item.instructions!.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.lg),
              Text(
                'How to begin',
                style: Theme.of(context).textTheme.titleSmall,
              ),
              const SizedBox(height: AppSpacing.xs),
              Text(
                item.instructions!,
                style: Theme.of(context).textTheme.bodyLarge,
              ),
            ],
            if (url != null && url.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.lg),
              Text(
                'Related resource',
                style: Theme.of(context).textTheme.titleSmall,
              ),
              const SizedBox(height: AppSpacing.xs),
              SelectableText(
                url,
                style: const TextStyle(color: AppColors.muted),
              ),
              const SizedBox(height: AppSpacing.sm),
              FilledButton.tonalIcon(
                onPressed: () async {
                  await Clipboard.setData(ClipboardData(text: url));
                  if (!context.mounted) return;
                  ScaffoldMessenger.of(
                    context,
                  ).showSnackBar(const SnackBar(content: Text('Link copied.')));
                },
                icon: const Icon(Icons.copy_rounded),
                label: const Text('Copy link'),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
