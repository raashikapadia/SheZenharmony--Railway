import 'package:flutter/material.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../data/support_content.dart';

class PositiveEngagementScreen extends StatefulWidget {
  const PositiveEngagementScreen({super.key, ApiService? apiService})
    : _injectedApiService = apiService;

  final ApiService? _injectedApiService;

  @override
  State<PositiveEngagementScreen> createState() =>
      _PositiveEngagementScreenState();
}

class _PositiveEngagementScreenState extends State<PositiveEngagementScreen> {
  late final ApiService _api;
  late Future<List<PositiveContent>> _content;

  @override
  void initState() {
    super.initState();
    _api = widget._injectedApiService ?? ApiService();
    _load();
  }

  void _load() => _content = _api.positiveEngagement();

  @override
  void dispose() {
    if (widget._injectedApiService == null) _api.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Positive engagement')),
    body: SafeArea(
      child: FutureBuilder<List<PositiveContent>>(
        future: _content,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const AppLoadingView(
              message: 'Gathering something positive…',
            );
          }
          if (snapshot.hasError) {
            return AppStateView(
              icon: Icons.cloud_off_outlined,
              title: 'Couldn\'t load this space',
              message: 'Check your connection and try again.',
              actionLabel: 'Try again',
              onAction: () => setState(_load),
            );
          }
          final content = snapshot.data ?? const [];
          if (content.isEmpty) {
            return const AppStateView(
              icon: Icons.auto_awesome_outlined,
              title: 'Positive content is coming soon',
              message:
                  'Published affirmations, quizzes, and light activities will appear here.',
            );
          }
          return ListView.separated(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
            itemCount: content.length + 1,
            separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
            itemBuilder: (context, index) {
              if (index == 0) {
                return const Padding(
                  padding: EdgeInsets.only(bottom: AppSpacing.sm),
                  child: AppSectionHeader(
                    title: 'A brighter pause',
                    subtitle: 'Small prompts and activities for a gentle lift.',
                  ),
                );
              }
              final item = content[index - 1];
              return Card(
                color: AppColors.softBlush,
                child: InkWell(
                  borderRadius: BorderRadius.circular(AppRadii.card),
                  onTap: () => _showContent(context, item),
                  child: Padding(
                    padding: const EdgeInsets.all(AppSpacing.xl),
                    child: Row(
                      children: [
                        const Icon(
                          Icons.auto_awesome_rounded,
                          color: AppColors.secondary,
                          size: 30,
                        ),
                        const SizedBox(width: AppSpacing.md),
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                _label(item.contentType),
                                style: Theme.of(context).textTheme.labelMedium
                                    ?.copyWith(color: AppColors.secondary),
                              ),
                              const SizedBox(height: AppSpacing.xs),
                              Text(
                                item.title,
                                style: Theme.of(context).textTheme.titleMedium,
                              ),
                              if (item.description.isNotEmpty) ...[
                                const SizedBox(height: AppSpacing.xs),
                                Text(
                                  item.description,
                                  maxLines: 2,
                                  overflow: TextOverflow.ellipsis,
                                  style: const TextStyle(
                                    color: AppColors.muted,
                                  ),
                                ),
                              ],
                            ],
                          ),
                        ),
                        const Icon(Icons.chevron_right_rounded),
                      ],
                    ),
                  ),
                ),
              );
            },
          );
        },
      ),
    ),
  );

  static String _label(String type) => switch (type) {
    'journaling' => 'REFLECTION',
    'affirmation' => 'AFFIRMATION',
    'quiz' => 'LIGHT QUIZ',
    'motivation' => 'MOTIVATION',
    _ => 'POSITIVE ACTIVITY',
  };

  static Future<void> _showContent(
    BuildContext context,
    PositiveContent item,
  ) => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) => SafeArea(
      child: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(24, 4, 24, 32),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(item.title, style: Theme.of(context).textTheme.headlineSmall),
            const SizedBox(height: AppSpacing.md),
            Text(
              item.instructions.isNotEmpty
                  ? item.instructions
                  : item.description,
              style: Theme.of(context).textTheme.bodyLarge,
            ),
            const SizedBox(height: AppSpacing.xxl),
            SizedBox(
              width: double.infinity,
              child: FilledButton(
                onPressed: () => Navigator.of(context).pop(),
                child: const Text('Done'),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}
