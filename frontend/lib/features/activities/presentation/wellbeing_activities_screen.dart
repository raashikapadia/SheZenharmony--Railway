import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:youtube_player_iframe/youtube_player_iframe.dart';

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

class _ActivityCard extends StatefulWidget {
  const _ActivityCard({required this.activity});

  final WellbeingActivity activity;

  @override
  State<_ActivityCard> createState() => _ActivityCardState();
}

class _ActivityCardState extends State<_ActivityCard> with SingleTickerProviderStateMixin {
  late AnimationController _hoverController;
  late Animation<double> _hoverAnimation;

  @override
  void initState() {
    super.initState();
    _hoverController = AnimationController(
      duration: const Duration(milliseconds: 300),
      vsync: this,
    );
    _hoverAnimation = Tween<double>(begin: 0, end: 8).animate(
      CurvedAnimation(parent: _hoverController, curve: Curves.easeInOut),
    );
  }

  @override
  void dispose() {
    _hoverController.dispose();
    super.dispose();
  }

  void _onHover(bool isHovering) {
    if (isHovering) {
      _hoverController.forward();
    } else {
      _hoverController.reverse();
    }
  }

  @override
  Widget build(BuildContext context) {
    final gradients = {
      'breathing': LinearGradient(
        colors: [const Color(0xFFE8F5E9), const Color(0xFFC8E6C9)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      'meditation': LinearGradient(
        colors: [const Color(0xFFF3E5F5), const Color(0xFFE1BEE7)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      'yoga': LinearGradient(
        colors: [const Color(0xFFFFF3E0), const Color(0xFFFFE0B2)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
      'grounding': LinearGradient(
        colors: [const Color(0xFFE0F2F1), const Color(0xFFB2DFDB)],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
    };

    final icons = {
      'breathing': Icons.air_rounded,
      'meditation': Icons.self_improvement_rounded,
      'yoga': Icons.self_improvement_rounded,
      'grounding': Icons.nature_rounded,
    };

    final categoryLower = widget.activity.category.toLowerCase();
    final gradient = gradients[categoryLower] ?? gradients['breathing']!;
    final icon = icons[categoryLower] ?? Icons.spa_rounded;

    return MouseRegion(
      onEnter: (_) => _onHover(true),
      onExit: (_) => _onHover(false),
      child: AnimatedBuilder(
        animation: _hoverAnimation,
        builder: (context, child) {
          return Transform.translate(
            offset: Offset(0, -_hoverAnimation.value),
            child: GestureDetector(
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => _ActivityDetailScreen(activity: widget.activity),
                ),
              ),
              child: Container(
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(20),
                  boxShadow: [
                    BoxShadow(
                      color: AppColors.primary.withOpacity(0.15),
                      blurRadius: 12 + _hoverAnimation.value,
                      offset: Offset(0, 4 + _hoverAnimation.value),
                    ),
                  ],
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(20),
                  child: Stack(
                    children: [
                      Container(
                        decoration: BoxDecoration(gradient: gradient),
                      ),
                      Padding(
                        padding: const EdgeInsets.all(20),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Container(
                              width: 60,
                              height: 60,
                              decoration: BoxDecoration(
                                color: Colors.white.withOpacity(0.7),
                                borderRadius: BorderRadius.circular(16),
                                boxShadow: [
                                  BoxShadow(
                                    color: AppColors.primary.withOpacity(0.2),
                                    blurRadius: 8,
                                  ),
                                ],
                              ),
                              child: Icon(
                                icon,
                                color: AppColors.primary,
                                size: 32,
                              ),
                            ),
                            const SizedBox(width: 16),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    widget.activity.category.toUpperCase(),
                                    style: Theme.of(context).textTheme.labelSmall?.copyWith(
                                      color: AppColors.primary,
                                      fontWeight: FontWeight.w800,
                                      letterSpacing: .8,
                                    ),
                                  ),
                                  const SizedBox(height: 8),
                                  Text(
                                    widget.activity.title,
                                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                                      fontWeight: FontWeight.bold,
                                      color: Colors.black87,
                                    ),
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  if (widget.activity.description.isNotEmpty) ...[
                                    const SizedBox(height: 8),
                                    Text(
                                      widget.activity.description,
                                      maxLines: 2,
                                      overflow: TextOverflow.ellipsis,
                                      style: const TextStyle(
                                        color: Colors.black54,
                                        fontSize: 14,
                                        height: 1.4,
                                      ),
                                    ),
                                  ],
                                  const SizedBox(height: 12),
                                  Container(
                                    padding: const EdgeInsets.symmetric(
                                      horizontal: 12,
                                      vertical: 6,
                                    ),
                                    decoration: BoxDecoration(
                                      color: Colors.white.withOpacity(0.6),
                                      borderRadius: BorderRadius.circular(8),
                                    ),
                                    child: Row(
                                      mainAxisSize: MainAxisSize.min,
                                      children: [
                                        Icon(
                                          Icons.arrow_forward_rounded,
                                          size: 16,
                                          color: AppColors.primary,
                                        ),
                                        const SizedBox(width: 6),
                                        Text(
                                          'Start',
                                          style: TextStyle(
                                            color: AppColors.primary,
                                            fontWeight: FontWeight.w600,
                                            fontSize: 12,
                                          ),
                                        ),
                                      ],
                                    ),
                                  ),
                                ],
                              ),
                            ),
                          ],
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          );
        },
      ),
    );
  }
}

class _ActivityDetailScreen extends StatefulWidget {
  const _ActivityDetailScreen({required this.activity});

  final WellbeingActivity activity;

  @override
  State<_ActivityDetailScreen> createState() => _ActivityDetailScreenState();
}

class _ActivityDetailScreenState extends State<_ActivityDetailScreen> {
  /// Non-null when the activity points at a YouTube video that this platform
  /// can play inline. Windows and Linux have no webview implementation, so
  /// they keep the tap-to-open card instead.
  YoutubePlayerController? _controller;

  WellbeingActivity get activity => widget.activity;

  @override
  void initState() {
    super.initState();

    if (!activity.hasVideo || !_supportsInlinePlayback) return;

    final videoId = YoutubePlayerController.convertUrlToId(_normalisedUrl);
    if (videoId == null) return;

    _controller = YoutubePlayerController.fromVideoId(
      videoId: videoId,
      params: const YoutubePlayerParams(
        showFullscreenButton: true,
        // Keep the player quiet until the student chooses to start.
        showControls: true,
      ),
    );
  }

  @override
  void dispose() {
    _controller?.close();
    super.dispose();
  }

  bool get _supportsInlinePlayback {
    if (kIsWeb) return true;
    return defaultTargetPlatform == TargetPlatform.android ||
        defaultTargetPlatform == TargetPlatform.iOS ||
        defaultTargetPlatform == TargetPlatform.macOS;
  }

  /// Admins often paste links without a scheme ("youtu.be/..."), which parse
  /// as relative URIs that neither the id parser nor an external app handles.
  String get _normalisedUrl {
    final url = activity.sourceUrl.trim();
    if (url.isEmpty) return url;
    if (url.startsWith(RegExp(r'[a-zA-Z][a-zA-Z0-9+.-]*:'))) return url;
    return 'https://$url';
  }

  void _launchVideo() async {
    final messenger = ScaffoldMessenger.of(context);
    final url = _normalisedUrl;

    if (url.isEmpty) {
      messenger.showSnackBar(
        const SnackBar(content: Text('Video URL not available')),
      );
      return;
    }

    try {
      final uri = Uri.parse(url);
      // Don't gate on canLaunchUrl: it reports false whenever package
      // visibility hides the handling app, even though the launch succeeds.
      var launched = await launchUrl(
        uri,
        mode: LaunchMode.externalApplication,
      );
      if (!launched) {
        launched = await launchUrl(uri, mode: LaunchMode.platformDefault);
      }
      if (!launched) {
        messenger.showSnackBar(
          const SnackBar(content: Text('Could not open video')),
        );
      }
    } catch (e) {
      messenger.showSnackBar(
        SnackBar(content: Text('Could not open video: $e')),
      );
    }
  }

  Widget _buildInlinePlayer(YoutubePlayerController controller) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      Container(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: AppColors.primary.withOpacity(0.15),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(16),
          child: YoutubePlayer(controller: controller),
        ),
      ),
      const SizedBox(height: 8),
      Align(
        alignment: Alignment.centerRight,
        child: TextButton.icon(
          onPressed: _launchVideo,
          icon: const Icon(Icons.open_in_new_rounded, size: 18),
          label: const Text('Open in YouTube'),
          style: TextButton.styleFrom(foregroundColor: AppColors.primary),
        ),
      ),
    ],
  );

  Widget _buildLaunchCard() => Container(
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(16),
      boxShadow: [
        BoxShadow(
          color: AppColors.primary.withOpacity(0.15),
          blurRadius: 12,
          offset: const Offset(0, 4),
        ),
      ],
    ),
    child: ClipRRect(
      borderRadius: BorderRadius.circular(16),
      child: GestureDetector(
        onTap: _launchVideo,
        child: Container(
          height: 220,
          color: AppColors.softSage,
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  color: AppColors.primary.withOpacity(0.2),
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  Icons.play_circle_filled_rounded,
                  size: 56,
                  color: AppColors.primary,
                ),
              ),
              const SizedBox(height: 16),
              Text(
                'Tap to watch video',
                style: TextStyle(
                  color: AppColors.primary,
                  fontWeight: FontWeight.w600,
                  fontSize: 16,
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );

  @override
  Widget build(BuildContext context) => Scaffold(
    body: CustomScrollView(
      slivers: [
        SliverAppBar(
          expandedHeight: 300,
          floating: false,
          pinned: true,
          stretch: true,
          elevation: 0,
          backgroundColor: AppColors.primary,
          foregroundColor: Colors.white,
          // Rounds off the bottom of the whole bar, artwork included.
          shape: const RoundedRectangleBorder(
            borderRadius: BorderRadius.vertical(bottom: Radius.circular(32)),
          ),
          flexibleSpace: LayoutBuilder(
            builder: (context, constraints) {
              final collapsedHeight =
                  kToolbarHeight + MediaQuery.of(context).padding.top;
              // 1.0 while fully expanded, 0.0 once pinned at collapsed height.
              final expansion =
                  ((constraints.maxHeight - collapsedHeight) /
                          (300 - collapsedHeight))
                      .clamp(0.0, 1.0);

              return FlexibleSpaceBar(
                centerTitle: true,
                // Parallax offsets the background past the bar, which would
                // push the curved lip below the visible area.
                collapseMode: CollapseMode.none,
                stretchModes: const [StretchMode.zoomBackground],
                titlePadding: const EdgeInsets.symmetric(
                  horizontal: 56,
                  vertical: 16,
                ),
                // The big title lives in the header artwork; this one only
                // fades in once that artwork has scrolled away.
                title: Opacity(
                  opacity: 1 - expansion,
                  child: Text(
                    activity.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: Colors.white,
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
                // Material only paints `shape`, it does not clip children, so
                // the artwork needs its own clip to get a curved bottom.
                background: ClipRRect(
                  borderRadius: const BorderRadius.vertical(
                    bottom: Radius.circular(32),
                  ),
                  child: _ActivityHeader(
                    activity: activity,
                    expansion: expansion,
                  ),
                ),
              );
            },
          ),
        ),
        SliverToBoxAdapter(
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                // Video Section
                if (activity.hasVideo && activity.sourceUrl.isNotEmpty) ...[
                  if (_controller case final controller?)
                    _buildInlinePlayer(controller)
                  else
                    _buildLaunchCard(),
                  const SizedBox(height: 32),
                ],

                // Instructions Section
                if (activity.instructions.isNotEmpty) ...[
                  Text(
                    'How to begin',
                    style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.bold,
                      color: AppColors.primary,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Container(
                    padding: const EdgeInsets.all(16),
                    decoration: BoxDecoration(
                      color: Colors.white,
                      borderRadius: BorderRadius.circular(12),
                      border: Border.all(
                        color: AppColors.primary.withOpacity(0.1),
                      ),
                    ),
                    child: Text(
                      activity.instructions,
                      style: const TextStyle(
                        fontSize: 15,
                        height: 1.6,
                        color: Colors.black87,
                      ),
                    ),
                  ),
                  const SizedBox(height: 32),
                ],

                // Safety Notice
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: const Color(0xFFFFF3E0).withOpacity(0.5),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: const Color(0xFFFFB74D).withOpacity(0.3),
                    ),
                  ),
                  child: Row(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Icon(
                        Icons.favorite_rounded,
                        color: AppColors.primary,
                        size: 20,
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          'Choose a safe, comfortable space before beginning. Stop at any time if the activity does not feel right for you.',
                          style: TextStyle(
                            color: Colors.black87,
                            fontSize: 14,
                            height: 1.5,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),
                const SizedBox(height: 32),
              ],
            ),
          ),
        ),
      ],
    ),
  );
}

/// The expanded artwork behind the activity title: a soft gradient with
/// floating bubbles, a badged category icon, and a curved lip that melts into
/// the page below. Fades out as the app bar collapses.
class _ActivityHeader extends StatelessWidget {
  const _ActivityHeader({required this.activity, required this.expansion});

  final WellbeingActivity activity;

  /// 1.0 when the app bar is fully expanded, 0.0 once it is collapsed.
  final double expansion;

  static IconData _iconFor(String category) => switch (category.toLowerCase()) {
    'breathing' => Icons.air_rounded,
    'grounding' => Icons.spa_rounded,
    'meditation' || 'mindfulness' => Icons.self_improvement_rounded,
    'relaxation' => Icons.bedtime_rounded,
    'yoga' => Icons.accessibility_new_rounded,
    'resource' => Icons.menu_book_rounded,
    _ => Icons.favorite_rounded,
  };

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: const BoxDecoration(
      gradient: LinearGradient(
        colors: [Color(0xFF8E6494), AppColors.primary, AppColors.secondary],
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
      ),
    ),
    child: Stack(
      fit: StackFit.expand,
      children: [
        Positioned(top: -34, right: -26, child: _Bubble(size: 170, opacity: 0.12)),
        Positioned(top: 72, left: -44, child: _Bubble(size: 130, opacity: 0.10)),
        Positioned(bottom: 54, right: 46, child: _Bubble(size: 74, opacity: 0.09)),
        Positioned(bottom: 96, left: 40, child: _Bubble(size: 38, opacity: 0.12)),

        Opacity(
          opacity: expansion,
          child: SafeArea(
            bottom: false,
            child: Padding(
              padding: const EdgeInsets.fromLTRB(24, 40, 24, 44),
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Container(
                    width: 84,
                    height: 84,
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.white.withOpacity(0.18),
                      border: Border.all(
                        color: Colors.white.withOpacity(0.4),
                        width: 1.5,
                      ),
                    ),
                    child: Icon(
                      _iconFor(activity.category),
                      size: 40,
                      color: Colors.white,
                    ),
                  ),
                  const SizedBox(height: 18),
                  if (activity.category.isNotEmpty) ...[
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 6,
                      ),
                      decoration: BoxDecoration(
                        color: Colors.white.withOpacity(0.22),
                        borderRadius: BorderRadius.circular(AppRadii.pill),
                      ),
                      child: Text(
                        activity.category.toUpperCase(),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                          letterSpacing: 1.3,
                        ),
                      ),
                    ),
                    const SizedBox(height: 14),
                  ],
                  Flexible(
                    child: Text(
                      activity.title,
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 24,
                        height: 1.25,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),

      ],
    ),
  );
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.size, required this.opacity});

  final double size;
  final double opacity;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      color: Colors.white.withOpacity(opacity),
    ),
  );
}
