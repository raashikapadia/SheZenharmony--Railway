import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../../auth/application/auth_provider.dart';
import '../data/personal_guidance.dart';
import 'guidance_detail_sheet.dart';

/// The student's personal wellbeing toolkit. Everything shown here is authored
/// and matched by the admin; this screen only arranges it.
class PersonalGuidanceScreen extends StatefulWidget {
  const PersonalGuidanceScreen({super.key, ApiService? apiService})
    : _injectedApiService = apiService;

  final ApiService? _injectedApiService;

  @override
  State<PersonalGuidanceScreen> createState() => _PersonalGuidanceScreenState();
}

class _PersonalGuidanceScreenState extends State<PersonalGuidanceScreen> {
  late final ApiService _api;
  late Future<GuidanceToolkit> _toolkit;

  /// Which quick tip is on screen, so "Try something different" can move on
  /// without reloading the whole toolkit.
  int _tipIndex = 0;

  @override
  void initState() {
    super.initState();
    _api = widget._injectedApiService ?? ApiService();
    _load();
  }

  void _load() {
    final token = context.read<AuthProvider>().session?.token ?? '';
    _toolkit = _api.guidanceToolkit(token);
  }

  @override
  void dispose() {
    if (widget._injectedApiService == null) _api.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: SafeArea(
      child: FutureBuilder<GuidanceToolkit>(
        future: _toolkit,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const AppLoadingView(message: 'Putting your toolkit together…');
          }

          if (snapshot.hasError) {
            return AppStateView(
              icon: Icons.cloud_off_outlined,
              title: 'Couldn\'t load your guidance',
              message: 'Check your connection and try again.',
              actionLabel: 'Try again',
              onAction: () => setState(_load),
            );
          }

          final toolkit = snapshot.data;

          if (toolkit == null || toolkit.items.isEmpty) {
            return AppStateView(
              icon: Icons.spa_outlined,
              title: 'Your toolkit is being prepared',
              message:
                  'Guidance published by the SheZen team will appear here.',
              actionLabel: 'Check again',
              onAction: () => setState(_load),
            );
          }

          return _buildToolkit(context, toolkit);
        },
      ),
    ),
  );

  Widget _buildToolkit(BuildContext context, GuidanceToolkit toolkit) {
    final tips = toolkit.quickTips;
    final strategies = toolkit.strategies;

    return RefreshIndicator(
      onRefresh: () async => setState(_load),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        children: [
          _ForYouHeader(
            headline: toolkit.headline,
            subline: toolkit.subline,
            bandMessage: toolkit.bandMessage,
          ),

          if (tips.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.xxl),
            const AppSectionHeader(
              title: 'A quick thought',
              subtitle: 'Nothing to do here — just something to read.',
            ),
            const SizedBox(height: AppSpacing.md),
            _QuickTipCard(
              tip: tips[_tipIndex % tips.length],
              canShuffle: tips.length > 1,
              onAnother: () => setState(() => _tipIndex = _tipIndex + 1),
            ),
          ],

          if (strategies.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.xxl),
            const AppSectionHeader(
              title: 'Things you can try',
              subtitle:
                  'Short, practical steps. Pick one if it suits you — there is no order to follow.',
            ),
            const SizedBox(height: AppSpacing.md),
            for (final strategy in strategies) ...[
              _StrategyCard(
                strategy: strategy,
                onOpen: () => showGuidanceDetail(context, strategy),
              ),
              const SizedBox(height: AppSpacing.md),
            ],
          ],
        ],
      ),
    );
  }
}

/// The "For You" lead-in. Deliberately warm and non-clinical: it never names a
/// score or a level, only that some support may help right now.
class _ForYouHeader extends StatelessWidget {
  const _ForYouHeader({
    required this.headline,
    required this.subline,
    this.bandMessage,
  });

  final String headline;
  final String subline;
  final String? bandMessage;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(AppSpacing.xl),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [AppColors.softLavender, AppColors.softBlush],
      ),
      borderRadius: BorderRadius.circular(28),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            const Icon(
              Icons.favorite_rounded,
              color: AppColors.primary,
              size: 22,
            ),
            const SizedBox(width: AppSpacing.sm),
            Expanded(
              child: Text(
                headline,
                style: Theme.of(context).textTheme.titleLarge,
              ),
            ),
          ],
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(subline, style: const TextStyle(color: AppColors.muted)),
        if (bandMessage != null && bandMessage!.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.md),
          Text(
            bandMessage!,
            style: Theme.of(context).textTheme.bodyMedium,
          ),
        ],
        const SizedBox(height: AppSpacing.md),
        const Text(
          'Everything here is optional. Read what helps, skip what does not.',
          style: TextStyle(color: AppColors.muted, fontSize: 13),
        ),
      ],
    ),
  );
}

/// A short piece of advice, readable at a glance.
class _QuickTipCard extends StatelessWidget {
  const _QuickTipCard({
    required this.tip,
    required this.canShuffle,
    required this.onAnother,
  });

  final PersonalGuidance tip;
  final bool canShuffle;
  final VoidCallback onAnother;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(AppSpacing.xl),
    decoration: BoxDecoration(
      color: AppColors.softSage,
      borderRadius: BorderRadius.circular(AppRadii.card),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              child: Text(
                tip.lead,
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(height: 1.4),
              ),
            ),
          ],
        ),
        if (canShuffle) ...[
          const SizedBox(height: AppSpacing.sm),
          Align(
            alignment: Alignment.centerLeft,
            child: TextButton.icon(
              onPressed: onAnother,
              icon: const Icon(Icons.refresh_rounded, size: 20),
              label: const Text('Show me another'),
            ),
          ),
        ],
      ],
    ),
  );
}

/// A practical strategy: what it is, how long it takes, and a way in.
class _StrategyCard extends StatelessWidget {
  const _StrategyCard({required this.strategy, required this.onOpen});

  final PersonalGuidance strategy;
  final VoidCallback onOpen;

  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      borderRadius: BorderRadius.circular(AppRadii.card),
      onTap: onOpen,
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              strategy.title ?? strategy.lead,
              style: Theme.of(context).textTheme.titleMedium,
            ),
            if (strategy.summary != null) ...[
              const SizedBox(height: AppSpacing.xs),
              Text(
                strategy.summary!,
                style: const TextStyle(color: AppColors.muted),
              ),
            ],
            const SizedBox(height: AppSpacing.md),
            Wrap(
              spacing: AppSpacing.sm,
              runSpacing: AppSpacing.xs,
              crossAxisAlignment: WrapCrossAlignment.center,
              children: [
                if (strategy.durationMinutes != null)
                  _MetaChip(
                    icon: Icons.schedule_rounded,
                    label: 'About ${strategy.durationMinutes} min',
                  ),
                // Saying how many steps there are sets expectations before
                // the student commits to opening it.
                _MetaChip(
                  icon: Icons.format_list_numbered_rounded,
                  label: strategy.steps.length == 1
                      ? '1 step'
                      : '${strategy.steps.length} steps',
                ),
                if (strategy.relatedActivity != null)
                  const _MetaChip(
                    icon: Icons.spa_outlined,
                    label: 'Includes an activity',
                  ),
              ],
            ),
            const SizedBox(height: AppSpacing.sm),
            Align(
              alignment: Alignment.centerLeft,
              child: FilledButton.tonalIcon(
                onPressed: onOpen,
                icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                label: const Text('Show me how'),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class _MetaChip extends StatelessWidget {
  const _MetaChip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 5),
    decoration: BoxDecoration(
      color: AppColors.softLavender,
      borderRadius: BorderRadius.circular(AppRadii.pill),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15, color: AppColors.primary),
        const SizedBox(width: 5),
        Text(
          label,
          style: const TextStyle(fontSize: 12, color: AppColors.primary),
        ),
      ],
    ),
  );
}
