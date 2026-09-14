import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../data/support_content.dart';
import 'games/games_quizzes_screen.dart';

class PositiveEngagementScreen extends StatefulWidget {
  const PositiveEngagementScreen({
    super.key,
    ApiService? apiService,
    this.embedded = false,
  }) : _injectedApiService = apiService;

  final ApiService? _injectedApiService;

  /// True when shown inside the bottom-navigation shell, which supplies its
  /// own app bar. Pushed routes keep their own.
  final bool embedded;

  @override
  State<PositiveEngagementScreen> createState() =>
      _PositiveEngagementScreenState();
}

class _PositiveEngagementScreenState extends State<PositiveEngagementScreen> {
  late final ApiService _api;
  late Future<List<PositiveContent>> _content;

  /// Messages are shown one at a time so the page stays calm; this points at
  /// the one currently on screen.
  int _messageIndex = 0;

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

  /// A short message is one whose title says everything there is to say.
  static bool _isShortMessage(PositiveContent item) =>
      item.contentType == 'motivation' && !_hasMoreToShow(item);

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: widget.embedded
        ? null
        : AppBar(title: const Text('Positive engagement')),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        children: [
          // The two areas that lead somewhere sit side by side so both are
          // visible without scrolling.
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Expanded(
                child: AppFeatureCard(
                  icon: Icons.videogame_asset_outlined,
                  title: 'Games',
                  description: 'Breathing, gratitude, and mindful play.',
                  tint: AppColors.softSage,
                  onTap: () => _openGamesQuizzes(GamesQuizzesSection.games),
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: AppFeatureCard(
                  icon: Icons.quiz_outlined,
                  title: 'Quizzes',
                  description: 'Quick wellbeing check-ins.',
                  tint: AppColors.softPeach,
                  onTap: () => _openGamesQuizzes(GamesQuizzesSection.quizzes),
                ),
              ),
            ],
          ),

          const SizedBox(height: AppSpacing.xxl),

          _buildContent(context),
        ],
      ),
    ),
  );

  void _openGamesQuizzes(GamesQuizzesSection section) => Navigator.of(
    context,
  ).push(MaterialPageRoute(builder: (_) => GamesQuizzesScreen(focus: section)));

  Widget _buildContent(
    BuildContext context,
  ) => FutureBuilder<List<PositiveContent>>(
    future: _content,
    builder: (context, snapshot) {
      if (snapshot.connectionState == ConnectionState.waiting) {
        return const Padding(
          padding: EdgeInsets.symmetric(vertical: AppSpacing.xxl),
          child: Center(child: CircularProgressIndicator()),
        );
      }
      if (snapshot.hasError) {
        return _InlineNotice(
          icon: Icons.cloud_off_outlined,
          message: 'Couldn\'t load messages just now.',
          actionLabel: 'Try again',
          onAction: () => setState(_load),
        );
      }

      final all = snapshot.data ?? const <PositiveContent>[];
      final messages = all.where(_isShortMessage).toList();
      final activities = all.where((i) => !_isShortMessage(i)).toList();

      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const AppSectionHeader(
            title: 'A lift for your day',
            subtitle: 'One short message at a time — see what today brings.',
          ),
          const SizedBox(height: AppSpacing.md),
          if (messages.isEmpty)
            const _InlineNotice(
              icon: Icons.wb_sunny_outlined,
              message: 'Messages will appear here soon.',
            )
          else
            _MessageOfTheMoment(
              message: messages[_messageIndex % messages.length].title,
              canShuffle: messages.length > 1,
              onAnother: () =>
                  setState(() => _messageIndex = _messageIndex + 1),
            ),
          if (activities.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.xxl),
            const AppSectionHeader(
              title: 'More to explore',
              subtitle: 'Little things to play with when you have a minute.',
            ),
            const SizedBox(height: AppSpacing.md),
            for (final item in activities) ...[
              _ActivityCard(
                item: item,
                label: _label(item.contentType),
                onTap: () => _showContent(context, item),
              ),
              const SizedBox(height: AppSpacing.md),
            ],
          ],
        ],
      );
    },
  );

  /// Whether opening [item] would reveal anything beyond its title.
  static bool _hasMoreToShow(PositiveContent item) =>
      item.instructions.trim().isNotEmpty ||
      item.description.trim().isNotEmpty ||
      item.externalUrl.trim().isNotEmpty;

  static String _label(String type) => switch (type) {
    'journaling' => 'REFLECTION',
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
            if (item.externalUrl.trim().isNotEmpty) ...[
              const SizedBox(height: AppSpacing.lg),
              SelectableText(
                item.externalUrl,
                style: const TextStyle(color: AppColors.muted),
              ),
              const SizedBox(height: AppSpacing.sm),
              OutlinedButton.icon(
                onPressed: () => _openRelatedLink(context, item.externalUrl),
                icon: const Icon(Icons.open_in_new_rounded),
                label: const Text('Open related link'),
              ),
            ],
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

  static Future<void> _openRelatedLink(
    BuildContext context,
    String value,
  ) async {
    final uri = Uri.tryParse(value.trim());
    if (uri == null || (uri.scheme != 'https' && uri.scheme != 'http')) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('This related link is not valid.')),
      );
      return;
    }

    try {
      final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
      if (!opened && context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Could not open the related link.')),
        );
      }
    } on Exception {
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Could not open the related link.')),
      );
    }
  }
}

/// The single motivational message currently on screen, with a gentle way to
/// ask for a different one.
class _MessageOfTheMoment extends StatelessWidget {
  const _MessageOfTheMoment({
    required this.message,
    required this.canShuffle,
    required this.onAnother,
  });

  final String message;
  final bool canShuffle;
  final VoidCallback onAnother;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(AppSpacing.xl),
    decoration: BoxDecoration(
      color: AppColors.softLavender,
      borderRadius: BorderRadius.circular(AppRadii.card),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.wb_sunny_rounded, color: AppColors.primary, size: 28),
        const SizedBox(height: AppSpacing.md),
        Text(
          message,
          style: Theme.of(context).textTheme.titleLarge?.copyWith(height: 1.35),
        ),
        if (canShuffle) ...[
          const SizedBox(height: AppSpacing.md),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: onAnother,
              icon: const Icon(Icons.refresh_rounded, size: 20),
              label: const Text('Another one'),
            ),
          ),
        ],
      ],
    ),
  );
}

/// A longer positive activity that opens for the full text.
class _ActivityCard extends StatelessWidget {
  const _ActivityCard({
    required this.item,
    required this.label,
    required this.onTap,
  });

  final PositiveContent item;
  final String label;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    color: AppColors.softBlush,
    child: InkWell(
      borderRadius: BorderRadius.circular(AppRadii.card),
      onTap: onTap,
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
                    label,
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: AppColors.secondary,
                    ),
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
                      style: const TextStyle(color: AppColors.muted),
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
}

/// A compact inline status line, used instead of a full-page state view so it
/// can sit inside the scrolling page.
class _InlineNotice extends StatelessWidget {
  const _InlineNotice({
    required this.icon,
    required this.message,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(AppSpacing.xl),
    decoration: BoxDecoration(
      color: AppColors.softLavender,
      borderRadius: BorderRadius.circular(AppRadii.card),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: AppColors.muted, size: 26),
        const SizedBox(height: AppSpacing.sm),
        Text(message, style: const TextStyle(color: AppColors.muted)),
        if (actionLabel != null && onAction != null) ...[
          const SizedBox(height: AppSpacing.sm),
          TextButton(onPressed: onAction, child: Text(actionLabel!)),
        ],
      ],
    ),
  );
}
