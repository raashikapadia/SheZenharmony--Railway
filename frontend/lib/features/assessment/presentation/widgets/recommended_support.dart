import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../core/theme/app_theme.dart';
import '../../data/assessment_result.dart';
import 'intervention_destination.dart';

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

/// What to call the action for a support item, from the kind of content
/// the admin published it as. Anything unrecognised gets a neutral verb.
String ctaLabelFor(RecommendedIntervention item) {
  final hasLink = item.externalUrl?.isNotEmpty == true;
  return switch (item.contentType) {
    'journaling' || 'activity' || 'exercise' => 'Start activity',
    'guidance' || 'affirmation' || 'motivational' => 'View guidance',
    'resource' || 'helpline' => 'Explore resources',
    _ => hasLink ? 'Open resource' : 'View details',
  };
}

/// Opens [url] outside the app, falling back to the platform default and
/// then to a message rather than failing silently.
Future<void> openSupportLink(BuildContext context, String url) async {
  final messenger = ScaffoldMessenger.of(context);
  try {
    final uri = Uri.parse(url);
    var launched = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!launched) {
      launched = await launchUrl(uri, mode: LaunchMode.platformDefault);
    }
    if (!launched) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Could not open this link.')),
      );
    }
  } catch (_) {
    messenger.showSnackBar(
      const SnackBar(content: Text('Could not open this link.')),
    );
  }
}

/// Renders the admin-published interventions matched to the student's
/// result range. Hidden entirely when there is nothing to show. Items are
/// never hardcoded — the list, its order and the one shown first all come
/// from what the admin linked to the range.
class RecommendedSupportSection extends StatelessWidget {
  const RecommendedSupportSection({super.key, required this.items});

  final List<RecommendedIntervention> items;

  @override
  Widget build(BuildContext context) {
    if (items.isEmpty) return const SizedBox.shrink();

    final first = items.first;
    final others = items.skip(1).toList();

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          'Recommended Support',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: AppSpacing.xs),
        const Text(
          'Chosen for the result you got today.',
          style: TextStyle(color: AppColors.muted),
        ),
        const SizedBox(height: AppSpacing.md),
        _FeaturedSupportCard(item: first),
        if (others.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.lg),
          Text(
            'You might also like',
            style: Theme.of(context).textTheme.titleSmall,
          ),
          const SizedBox(height: AppSpacing.sm),
          for (final item in others) ...[
            _SupportCard(item: item),
            const SizedBox(height: AppSpacing.sm),
          ],
        ],
      ],
    );
  }
}

void _showDetails(BuildContext context, RecommendedIntervention item) {
  showModalBottomSheet<void>(
    context: context,
    showDragHandle: true,
    isScrollControlled: true,
    builder: (_) => _SupportDetailSheet(item: item),
  );
}

/// What tapping a support item does, in the order the admin configured it:
/// open the SheZen Harmony screen they pointed it at, else its link, else
/// its details. Nothing here is per-item — the destination comes from the API.
void _openSupport(BuildContext context, RecommendedIntervention item) {
  if (openInterventionScreen(context, item)) return;

  final url = item.externalUrl;
  if (url != null && url.isNotEmpty) {
    openSupportLink(context, url);
    return;
  }

  _showDetails(context, item);
}

/// The one the admin linked to this range: the personalised recommendation,
/// with its action right there rather than behind a tap.
class _FeaturedSupportCard extends StatelessWidget {
  const _FeaturedSupportCard({required this.item});

  final RecommendedIntervention item;

  @override
  Widget build(BuildContext context) {
    final url = item.externalUrl;
    final hasLink = url != null && url.isNotEmpty;
    final inApp = opensInApp(item);
    return Container(
      padding: const EdgeInsets.all(AppSpacing.lg),
      decoration: BoxDecoration(
        color: AppColors.softSage,
        borderRadius: BorderRadius.circular(AppRadii.card),
        border: Border.all(color: Colors.white.withValues(alpha: 0.7)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const CircleAvatar(
                backgroundColor: AppColors.surface,
                child: Icon(
                  Icons.favorite_outline_rounded,
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (item.description != null &&
                        item.description!.isNotEmpty) ...[
                      const SizedBox(height: AppSpacing.xs),
                      Text(
                        item.description!,
                        style: const TextStyle(
                          color: AppColors.muted,
                          height: 1.4,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          Row(
            children: [
              Expanded(
                child: FilledButton.icon(
                  onPressed: () => _openSupport(context, item),
                  icon: Icon(
                    hasLink && !inApp
                        ? Icons.open_in_new_rounded
                        : Icons.arrow_forward_rounded,
                    size: 18,
                  ),
                  label: Text(ctaLabelFor(item)),
                ),
              ),
              if (hasLink || inApp) ...[
                const SizedBox(width: AppSpacing.sm),
                TextButton(
                  onPressed: () => _showDetails(context, item),
                  child: const Text('Details'),
                ),
              ],
            ],
          ),
        ],
      ),
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
        onTap: () => opensInApp(item)
            ? _openSupport(context, item)
            : _showDetails(context, item),
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
    final inApp = opensInApp(item);
    final hasLink = url != null && url.isNotEmpty;
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
            if (inApp || hasLink) ...[
              const SizedBox(height: AppSpacing.xl),
              SizedBox(
                width: double.infinity,
                child: FilledButton.icon(
                  // Close the sheet before navigating, so coming back from the
                  // screen lands on the result rather than behind the sheet.
                  onPressed: () {
                    if (!inApp) {
                      openSupportLink(context, url!);
                      return;
                    }
                    final navigator = Navigator.of(context);
                    final screen = interventionDestination(item);
                    navigator.pop();
                    if (screen != null) {
                      navigator.push(MaterialPageRoute(builder: (_) => screen));
                    }
                  },
                  icon: Icon(
                    inApp
                        ? Icons.arrow_forward_rounded
                        : Icons.open_in_new_rounded,
                    size: 18,
                  ),
                  label: Text(ctaLabelFor(item)),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
