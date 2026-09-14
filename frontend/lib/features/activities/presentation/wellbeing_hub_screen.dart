import 'package:flutter/material.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../../diary/presentation/diary_library_screen.dart';
import '../data/support_content.dart';
import 'activity_palette.dart';
import 'wellbeing_collection_screen.dart';

/// Landing screen for "Wellbeing activities": the student picks a way in —
/// the video library, their own diary, or a single feeling — before seeing
/// any list.
///
/// The activities are fetched once here and handed to each collection, so the
/// counts on these cards and the lists behind them always agree, and searching
/// never waits on the network.
class WellbeingHubScreen extends StatefulWidget {
  const WellbeingHubScreen({
    super.key,
    ApiService? apiService,
    this.embedded = false,
  }) : _injectedApiService = apiService;

  final ApiService? _injectedApiService;

  /// True when shown inside the bottom-navigation shell, which supplies its
  /// own app bar. Pushed routes keep their own.
  final bool embedded;

  @override
  State<WellbeingHubScreen> createState() => _WellbeingHubScreenState();
}

class _WellbeingHubScreenState extends State<WellbeingHubScreen> {
  late final ApiService _api;
  late Future<List<WellbeingActivity>> _activities;

  @override
  void initState() {
    super.initState();
    _api = widget._injectedApiService ?? ApiService();
    _load();
  }

  void _load() => _activities = _api.wellbeingActivities();

  Future<void> _refresh() async {
    final activities = _api.wellbeingActivities();
    setState(() => _activities = activities);
    await activities;
  }

  @override
  void dispose() {
    if (widget._injectedApiService == null) _api.close();
    super.dispose();
  }

  /// The diary is device-only, so it takes no data from this screen's fetch.
  void _openDiary() {
    Navigator.of(
      context,
    ).push(MaterialPageRoute(builder: (_) => const DiaryLibraryScreen()));
  }

  void _openCollection({
    required String title,
    required List<WellbeingActivity> activities,
    String? subtitle,
    String searchHint = 'Search by name or feeling',
    String unitLabel = 'activity',
    bool showThumbnails = false,
  }) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => WellbeingCollectionScreen(
          title: title,
          activities: activities,
          subtitle: subtitle,
          searchHint: searchHint,
          unitLabel: unitLabel,
          showThumbnails: showThumbnails,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: widget.embedded
        ? null
        : AppBar(title: const Text('Wellbeing activities')),
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

          final activities = (snapshot.data ?? const <WellbeingActivity>[])
              .where(
                (activity) => !hiddenWellbeingActivityTitles.contains(
                  activity.title.trim().toLowerCase(),
                ),
              )
              .toList();

          if (activities.isEmpty) {
            return const AppStateView(
              icon: Icons.spa_outlined,
              title: 'Activities are being prepared',
              message:
                  'New wellbeing activities will appear here when they are published.',
            );
          }

          return RefreshIndicator(
            onRefresh: _refresh,
            child: _HubBody(
              activities: activities,
              onOpenCollection: _openCollection,
              onOpenDiary: _openDiary,
            ),
          );
        },
      ),
    ),
  );
}

/// Signature the hub body uses to hand a chosen collection back up to the
/// screen that owns the navigator.
typedef _OpenCollection =
    void Function({
      required String title,
      required List<WellbeingActivity> activities,
      String? subtitle,
      String searchHint,
      String unitLabel,
      bool showThumbnails,
    });

class _HubBody extends StatelessWidget {
  const _HubBody({
    required this.activities,
    required this.onOpenCollection,
    required this.onOpenDiary,
  });

  final List<WellbeingActivity> activities;
  final _OpenCollection onOpenCollection;
  final VoidCallback onOpenDiary;

  @override
  Widget build(BuildContext context) {
    final videos = activities.where((a) => a.hasVideo).toList();

    // Categories in the order the API returned them, so a newly published
    // category shows up without needing a hard-coded entry here.
    //
    // Journaling is the exception: "Journaling" on this hub is the student's
    // own private diary, so admin-published journaling content (the seeded
    // "Gratitude reflection", for one) is not surfaced here at all. It stays
    // reachable through the flat list the assessment result screen opens.
    //
    // Resource is excluded for the same reason: it has its own bottom-nav
    // Resource tab, which leads with the admin-published helplines, so a
    // feeling tile here would be a second door to the same records.
    final byCategory = <String, List<WellbeingActivity>>{};
    final labels = <String, String>{};
    for (final activity in activities) {
      final label = activity.category.trim();
      if (label.isEmpty) continue;
      final key = label.toLowerCase();
      if (key == 'journaling' || key == 'resource') continue;
      labels.putIfAbsent(key, () => label);
      byCategory.putIfAbsent(key, () => []).add(activity);
    }

    return ListView(
      physics: const AlwaysScrollableScrollPhysics(),
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.page,
        AppSpacing.sm,
        AppSpacing.page,
        AppSpacing.xxxl,
      ),
      children: [
        const _HubHeader(),

        const SizedBox(height: AppSpacing.xxl),

        const _SectionLabel('COLLECTIONS'),
        const SizedBox(height: AppSpacing.md),

        _VideoHeroCard(
          count: videos.length,
          onTap: () => onOpenCollection(
            title: 'Wellbeing videos',
            activities: videos,
            subtitle: 'Calming clips published by the SheZen team.',
            searchHint: 'Search videos',
            unitLabel: 'video',
            showThumbnails: true,
          ),
        ),

        const SizedBox(height: AppSpacing.md),
        // Sits in Collections rather than the feeling grid: the diary is a
        // place the student writes, not a slice of published content, and it
        // is here whether or not anything has been published.
        _OptionCard(
          icon: Icons.edit_note_rounded,
          title: 'Journaling',
          description: 'Your private diary, kept only on this phone.',
          tint: AppColors.softGold,
          isPrivate: true,
          onTap: onOpenDiary,
        ),

        if (byCategory.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.xxl),
          const _SectionLabel('BROWSE BY FEELING'),
          const SizedBox(height: AppSpacing.md),
          LayoutBuilder(
            builder: (context, constraints) {
              final isNarrow = constraints.maxWidth < 320;
              final width = isNarrow
                  ? constraints.maxWidth
                  : (constraints.maxWidth - AppSpacing.md) / 2;

              return Wrap(
                spacing: AppSpacing.md,
                runSpacing: AppSpacing.md,
                children: [
                  for (final entry in byCategory.entries)
                    SizedBox(
                      width: width,
                      child: _CategoryTile(
                        label: labels[entry.key]!,
                        subtitle: entry.value.length == 1
                            ? '1 activity'
                            : '${entry.value.length} activities',
                        onTap: () => onOpenCollection(
                          title: labels[entry.key]!,
                          activities: entry.value,
                          searchHint:
                              'Search ${labels[entry.key]!.toLowerCase()}',
                          showThumbnails: true,
                        ),
                      ),
                    ),
                ],
              );
            },
          ),
        ],
      ],
    );
  }
}

/// Welcome panel: the same plum wash, drifting blobs, and sparkles the activity
/// detail header uses, so the hub reads as the front door to that place.
class _HubHeader extends StatelessWidget {
  const _HubHeader();

  @override
  Widget build(BuildContext context) => ClipRRect(
    borderRadius: BorderRadius.circular(28),
    child: Stack(
      children: [
        Positioned.fill(
          child: DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                // Deeper than the raw pastels, which read as grey once they
                // cover an area this large.
                colors: [
                  Color.lerp(AppColors.softLavender, AppColors.primary, 0.1)!,
                  AppColors.softBlush,
                  Color.lerp(AppColors.softGold, Colors.white, 0.45)!,
                ],
                stops: const [0, 0.55, 1],
              ),
            ),
          ),
        ),
        Positioned(
          top: -70,
          right: -46,
          child: WellbeingBlob(
            size: 210,
            color: AppColors.primary.withValues(alpha: 0.22),
          ),
        ),
        Positioned(
          bottom: -56,
          left: -36,
          child: WellbeingBlob(
            size: 170,
            color: AppColors.secondary.withValues(alpha: 0.2),
          ),
        ),
        Positioned(
          top: 52,
          right: 26,
          child: WellbeingBlob(
            size: 72,
            color: Colors.white.withValues(alpha: 0.6),
          ),
        ),
        Positioned(
          top: 20,
          left: 24,
          child: WellbeingSparkle(
            size: 15,
            color: AppColors.primary.withValues(alpha: 0.35),
          ),
        ),
        Positioned(
          top: 74,
          left: 52,
          child: WellbeingSparkle(
            size: 10,
            color: AppColors.primary.withValues(alpha: 0.26),
          ),
        ),
        Positioned(
          bottom: 26,
          right: 32,
          child: WellbeingSparkle(
            size: 19,
            color: AppColors.primary.withValues(alpha: 0.3),
          ),
        ),
        Positioned(
          bottom: 60,
          left: 28,
          child: WellbeingSparkle(
            size: 12,
            color: AppColors.secondary.withValues(alpha: 0.38),
          ),
        ),
        Padding(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.xxl,
            AppSpacing.xl,
            AppSpacing.xxl,
            AppSpacing.xxl,
          ),
          child: Column(
            children: [
              const WellbeingIconTile(icon: Icons.spa_rounded, size: 66),
              const SizedBox(height: AppSpacing.md),
              Text(
                'Your calm corner',
                textAlign: TextAlign.center,
                style: Theme.of(
                  context,
                ).textTheme.headlineSmall?.copyWith(color: AppColors.primary),
              ),
              const SizedBox(height: AppSpacing.xs),
              const Text(
                'Choose how you would like to unwind today — watch, write, '
                'or browse by how you are feeling.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: AppColors.muted,
                  height: 1.45,
                  fontSize: 13.5,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.text);

  final String text;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      WellbeingSparkle(
        size: 13,
        color: AppColors.secondary.withValues(alpha: 0.75),
      ),
      const SizedBox(width: AppSpacing.sm),
      Text(
        text,
        style: Theme.of(context).textTheme.titleSmall?.copyWith(
          color: AppColors.primary,
          fontWeight: FontWeight.w800,
          letterSpacing: 1.2,
        ),
      ),
    ],
  );
}

/// The headline way in. Taller and warmer than the other option cards because
/// the video library is what most students come here for.
class _VideoHeroCard extends StatelessWidget {
  const _VideoHeroCard({required this.count, required this.onTap});

  final int count;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(26),
      child: Ink(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(26),
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            // Enough saturation to read as plum and rose rather than as two
            // shades of off-white.
            colors: [Color(0xFFE6D5F0), Color(0xFFF9DCE6), Color(0xFFFFEDCE)],
            stops: [0, 0.6, 1],
          ),
          border: Border.all(color: AppColors.primary.withValues(alpha: 0.1)),
          boxShadow: [
            BoxShadow(
              color: AppColors.primary.withValues(alpha: 0.1),
              blurRadius: 18,
              offset: const Offset(0, 8),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(26),
          child: Stack(
            children: [
              Positioned(
                top: -34,
                right: -22,
                child: WellbeingBlob(
                  size: 140,
                  color: Colors.white.withValues(alpha: 0.75),
                ),
              ),
              Positioned(
                bottom: 14,
                right: 96,
                child: WellbeingSparkle(
                  size: 13,
                  color: AppColors.primary.withValues(alpha: 0.3),
                ),
              ),
              Padding(
                padding: const EdgeInsets.all(AppSpacing.xl),
                child: Row(
                  children: [
                    Container(
                      width: 62,
                      height: 62,
                      decoration: BoxDecoration(
                        color: AppColors.surface,
                        borderRadius: BorderRadius.circular(20),
                        boxShadow: [
                          BoxShadow(
                            color: AppColors.primary.withValues(alpha: 0.18),
                            blurRadius: 14,
                            offset: const Offset(0, 6),
                          ),
                        ],
                      ),
                      child: const Icon(
                        Icons.play_circle_fill_rounded,
                        size: 34,
                        color: AppColors.primary,
                      ),
                    ),
                    const SizedBox(width: AppSpacing.lg),
                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            'Wellbeing videos',
                            style: Theme.of(context).textTheme.titleLarge
                                ?.copyWith(color: AppColors.primary),
                          ),
                          const SizedBox(height: AppSpacing.xs),
                          const Text(
                            'Guided clips you can watch right here.',
                            style: TextStyle(
                              color: AppColors.muted,
                              fontSize: 13,
                              height: 1.4,
                            ),
                          ),
                          if (count > 0) ...[
                            const SizedBox(height: AppSpacing.md),
                            _CountPill(
                              label: count == 1 ? '1 video' : '$count videos',
                            ),
                          ],
                        ],
                      ),
                    ),
                    const SizedBox(width: AppSpacing.sm),
                    const _Chevron(),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}

class _OptionCard extends StatelessWidget {
  const _OptionCard({
    required this.icon,
    required this.title,
    required this.description,
    required this.tint,
    required this.onTap,
    this.isPrivate = false,
  });

  final IconData icon;
  final String title;
  final String description;
  final Color tint;
  final VoidCallback onTap;

  /// Marks the card as the student's own space rather than published content,
  /// so the diary is not mistaken for something the team can read.
  final bool isPrivate;

  @override
  Widget build(BuildContext context) => Material(
    color: tint,
    clipBehavior: Clip.antiAlias,
    shape: RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(AppRadii.card),
      side: BorderSide(color: AppColors.primary.withValues(alpha: 0.09)),
    ),
    child: InkWell(
      onTap: onTap,
      child: Stack(
        children: [
          Positioned(
            top: -46,
            right: -30,
            child: WellbeingBlob(
              size: 150,
              color: Colors.white.withValues(alpha: 0.75),
            ),
          ),
          Positioned(
            bottom: 12,
            right: 84,
            child: WellbeingSparkle(
              size: 12,
              color: AppColors.primary.withValues(alpha: 0.28),
            ),
          ),
          Padding(
            padding: const EdgeInsets.all(AppSpacing.lg),
            child: Row(
              children: [
                Container(
                  width: 52,
                  height: 52,
                  decoration: BoxDecoration(
                    color: Colors.white,
                    shape: BoxShape.circle,
                    boxShadow: [
                      BoxShadow(
                        color: AppColors.primary.withValues(alpha: 0.14),
                        blurRadius: 12,
                        offset: const Offset(0, 5),
                      ),
                    ],
                  ),
                  child: Icon(icon, color: AppColors.primary, size: 25),
                ),
                const SizedBox(width: AppSpacing.md),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Text(
                        title,
                        style: const TextStyle(
                          color: AppColors.primary,
                          fontSize: 15.5,
                          fontWeight: FontWeight.w800,
                          letterSpacing: -0.2,
                        ),
                      ),
                      const SizedBox(height: 3),
                      Text(
                        description,
                        style: const TextStyle(
                          color: AppColors.muted,
                          fontSize: 12.5,
                          height: 1.35,
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(width: AppSpacing.sm),
                Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const _Chevron(),
                    if (isPrivate) ...[
                      const SizedBox(height: AppSpacing.sm),
                      const Icon(
                        Icons.lock_rounded,
                        size: 13,
                        color: AppColors.primary,
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    ),
  );
}

class _CategoryTile extends StatelessWidget {
  const _CategoryTile({
    required this.label,
    required this.subtitle,
    required this.onTap,
  });

  final String label;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final tint = WellbeingPalette.tintFor(label);

    // The wash carries the tile instead of a white card with a small swatch
    // in it: at this size a tinted chip reads as one cheerful object, where
    // the outlined version read as an empty box.
    return Material(
      color: tint,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(AppRadii.card),
        side: BorderSide(color: AppColors.primary.withValues(alpha: 0.07)),
      ),
      child: InkWell(
        onTap: onTap,
        child: Stack(
          children: [
            Positioned(
              top: -34,
              right: -24,
              child: WellbeingBlob(
                size: 86,
                color: Colors.white.withValues(alpha: 0.55),
              ),
            ),
            Positioned(
              bottom: -18,
              left: -14,
              child: WellbeingBlob(
                size: 68,
                color: AppColors.primary.withValues(alpha: 0.07),
              ),
            ),
            Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Container(
                    width: 46,
                    height: 46,
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(15),
                      boxShadow: [
                        BoxShadow(
                          color: AppColors.primary.withValues(alpha: 0.12),
                          blurRadius: 10,
                          offset: const Offset(0, 4),
                        ),
                      ],
                    ),
                    child: Icon(
                      WellbeingPalette.iconFor(label),
                      color: AppColors.primary,
                      size: 23,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    label,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppColors.ink,
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      letterSpacing: -0.2,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    subtitle,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: TextStyle(
                      color: AppColors.muted,
                      fontSize: 11.5,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _CountPill extends StatelessWidget {
  const _CountPill({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 5),
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadii.pill),
    ),
    child: Text(
      label,
      style: const TextStyle(
        color: AppColors.primary,
        fontSize: 11.5,
        fontWeight: FontWeight.w800,
        letterSpacing: 0.2,
      ),
    ),
  );
}

class _Chevron extends StatelessWidget {
  const _Chevron();

  @override
  Widget build(BuildContext context) => Container(
    width: 32,
    height: 32,
    decoration: BoxDecoration(
      color: AppColors.surface,
      shape: BoxShape.circle,
    ),
    child: const Icon(
      Icons.arrow_forward_ios_rounded,
      size: 14,
      color: AppColors.primary,
    ),
  );
}
