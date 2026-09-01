import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../data/support_content.dart';

class WellbeingActivitiesScreen extends StatefulWidget {
  const WellbeingActivitiesScreen({super.key, ApiService? apiService})
    : _injectedApiService = apiService;

  final ApiService? _injectedApiService;

  @override
  State<WellbeingActivitiesScreen> createState() =>
      _WellbeingActivitiesScreenState();
}

class _WellbeingActivitiesScreenState extends State<WellbeingActivitiesScreen> {
  late final ApiService _api;
  late Future<List<WellbeingActivity>> _activities;

  @override
  void initState() {
    super.initState();
    _api = widget._injectedApiService ?? ApiService();
    _load();
  }

  void _load() => _activities = _api.wellbeingActivities();

  @override
  void dispose() {
    if (widget._injectedApiService == null) _api.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Wellbeing activities')),
    body: SafeArea(
      child: FutureBuilder<List<WellbeingActivity>>(
        future: _activities,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const AppLoadingView(message: 'Finding gentle activities…');
          }
          if (snapshot.hasError) {
            return AppStateView(
              icon: Icons.cloud_off_outlined,
              title: 'Couldn\'t load activities',
              message: 'Check your connection and try again.',
              actionLabel: 'Try again',
              onAction: () => setState(_load),
            );
          }
          final activities = snapshot.data ?? const [];
          if (activities.isEmpty) {
            return const AppStateView(
              icon: Icons.spa_outlined,
              title: 'Activities are being prepared',
              message:
                  'New wellbeing activities will appear here when they are published.',
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
            itemCount: activities.length + 1,
            separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
            itemBuilder: (context, index) {
              if (index == 0) {
                return const Padding(
                  padding: EdgeInsets.only(bottom: AppSpacing.sm),
                  child: AppSectionHeader(
                    title: 'Choose a moment for you',
                    subtitle: 'Simple activities published by the SheZen team.',
                  ),
                );
              }
              final activity = activities[index - 1];
              return _ActivityCard(activity: activity);
            },
          );
        },
      ),
    ),
  );
}

class _ActivityCard extends StatelessWidget {
  const _ActivityCard({required this.activity});

  final WellbeingActivity activity;

  @override
  Widget build(BuildContext context) => Card(
    child: Padding(
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 52,
            height: 52,
            decoration: BoxDecoration(
              color: AppColors.softSage,
              borderRadius: BorderRadius.circular(16),
            ),
            child: Icon(
              activity.category.toLowerCase() == 'breathing'
                  ? Icons.air_rounded
                  : Icons.self_improvement_rounded,
              color: AppColors.primary,
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  activity.category.toUpperCase(),
                  style: Theme.of(context).textTheme.labelSmall?.copyWith(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w800,
                    letterSpacing: .8,
                  ),
                ),
                const SizedBox(height: AppSpacing.xs),
                Text(
                  activity.title,
                  style: Theme.of(context).textTheme.titleMedium,
                ),
                if (activity.description.isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.xs),
                  Text(
                    activity.description,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(color: AppColors.muted),
                  ),
                ],
                const SizedBox(height: AppSpacing.md),
                FilledButton.tonal(
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) => _ActivityDetailScreen(activity: activity),
                    ),
                  ),
                  child: const Text('View activity'),
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _ActivityDetailScreen extends StatelessWidget {
  const _ActivityDetailScreen({required this.activity});

  final WellbeingActivity activity;

  @override
  Widget build(BuildContext context) => AppPageScaffold(
    title: activity.title,
    subtitle: activity.description,
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Container(
          height: activity.hasVideo ? 190 : 140,
          decoration: BoxDecoration(
            color: AppColors.softSage,
            borderRadius: BorderRadius.circular(AppRadii.card),
          ),
          child: Icon(
            activity.hasVideo
                ? Icons.play_circle_outline_rounded
                : Icons.self_improvement_rounded,
            size: activity.hasVideo ? 72 : 58,
            color: AppColors.primary,
          ),
        ),
        const SizedBox(height: AppSpacing.xl),
        if (activity.instructions.isNotEmpty) ...[
          Text('How to begin', style: Theme.of(context).textTheme.titleMedium),
          const SizedBox(height: AppSpacing.sm),
          Text(
            activity.instructions,
            style: Theme.of(context).textTheme.bodyLarge,
          ),
          const SizedBox(height: AppSpacing.xl),
        ],
        if (activity.sourceUrl.isNotEmpty) ...[
          Text(
            activity.hasVideo ? 'Activity video' : 'Related resource',
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            activity.sourceUrl,
            style: const TextStyle(color: AppColors.muted),
          ),
          const SizedBox(height: AppSpacing.lg),
          FilledButton.icon(
            onPressed: () async {
              await Clipboard.setData(ClipboardData(text: activity.sourceUrl));
              if (!context.mounted) return;
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(content: Text('Activity link copied.')),
              );
            },
            icon: const Icon(Icons.copy_rounded),
            label: const Text('Copy activity link'),
          ),
        ],
        const SizedBox(height: AppSpacing.md),
        const Text(
          'Choose a safe, comfortable space before beginning. Stop at any time if the activity does not feel right for you.',
          style: TextStyle(color: AppColors.muted),
        ),
      ],
    ),
  );
}
