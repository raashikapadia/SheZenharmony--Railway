import 'package:flutter/material.dart';
import 'package:youtube_player_iframe/youtube_player_iframe.dart';

import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../data/support_content.dart';
import 'activity_palette.dart';
import 'wellbeing_activities_screen.dart';

/// A searchable slice of the wellbeing library — every video, one category, or
/// the guided practices.
///
/// The hub loads the activities once and hands the list down, so moving
/// between collections never refetches and search stays instant.
class WellbeingCollectionScreen extends StatefulWidget {
  const WellbeingCollectionScreen({
    super.key,
    required this.title,
    required this.activities,
    this.subtitle,
    this.searchHint = 'Search by name or feeling',
    this.unitLabel = 'activity',
    this.showThumbnails = false,
  });

  final String title;
  final List<WellbeingActivity> activities;
  final String? subtitle;
  final String searchHint;

  /// Singular noun for the result count. Pluralised with a trailing "s", so
  /// pass a word that survives it ("video", "practice", "activity" is special
  /// cased).
  final String unitLabel;

  /// Video collections lead with artwork; guided practices lead with their
  /// category icon.
  final bool showThumbnails;

  @override
  State<WellbeingCollectionScreen> createState() =>
      _WellbeingCollectionScreenState();
}

class _WellbeingCollectionScreenState extends State<WellbeingCollectionScreen> {
  final _searchController = TextEditingController();

  String _query = '';

  /// Lower-cased category the chips are filtering by; null means "all".
  String? _category;

  late final List<String> _categories = _collectCategories();

  /// First-seen casing for each distinct category, alphabetised so the chip
  /// row does not reshuffle when the API changes order.
  List<String> _collectCategories() {
    final seen = <String>{};
    final labels = <String>[];
    for (final activity in widget.activities) {
      final label = activity.category.trim();
      if (label.isEmpty) continue;
      if (seen.add(label.toLowerCase())) labels.add(label);
    }
    labels.sort((a, b) => a.toLowerCase().compareTo(b.toLowerCase()));
    return labels;
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  bool get _isFiltering => _query.trim().isNotEmpty || _category != null;

  List<WellbeingActivity> get _visible {
    final query = _query.trim().toLowerCase();
    return widget.activities.where((activity) {
      if (_category != null &&
          activity.category.trim().toLowerCase() != _category) {
        return false;
      }
      if (query.isEmpty) return true;
      return activity.title.toLowerCase().contains(query) ||
          activity.description.toLowerCase().contains(query) ||
          activity.category.toLowerCase().contains(query);
    }).toList();
  }

  String _countLabel(int shown) {
    final total = widget.activities.length;
    // In "1 of 2 videos" the noun agrees with the total, not the filtered
    // count, so both forms pluralise off the same number.
    final unit = total == 1
        ? widget.unitLabel
        : widget.unitLabel == 'activity'
        ? 'activities'
        : '${widget.unitLabel}s';
    return _isFiltering ? '$shown of $total $unit' : '$total $unit';
  }

  void _clearFilters() {
    _searchController.clear();
    setState(() {
      _query = '';
      _category = null;
    });
  }

  @override
  Widget build(BuildContext context) {
    final visible = _visible;

    return Scaffold(
      appBar: AppBar(title: Text(widget.title)),
      body: SafeArea(
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.page,
                AppSpacing.xs,
                AppSpacing.page,
                0,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  if (widget.subtitle case final subtitle?) ...[
                    Text(
                      subtitle,
                      style: Theme.of(
                        context,
                      ).textTheme.bodyMedium?.copyWith(color: AppColors.muted),
                    ),
                    const SizedBox(height: AppSpacing.md),
                  ],
                  _SearchField(
                    controller: _searchController,
                    hint: widget.searchHint,
                    onChanged: (value) => setState(() => _query = value),
                    onClear: () {
                      _searchController.clear();
                      setState(() => _query = '');
                    },
                  ),
                  if (_categories.length > 1) ...[
                    const SizedBox(height: AppSpacing.md),
                    _CategoryChipRow(
                      categories: _categories,
                      selected: _category,
                      onSelected: (value) => setState(() => _category = value),
                    ),
                  ],
                  const SizedBox(height: AppSpacing.md),
                  Text(
                    _countLabel(visible.length),
                    style: Theme.of(context).textTheme.labelMedium?.copyWith(
                      color: AppColors.muted,
                      fontWeight: FontWeight.w700,
                      letterSpacing: 0.3,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Expanded(
              child: visible.isEmpty
                  ? AppStateView(
                      icon: Icons.search_off_rounded,
                      title: 'Nothing matches that',
                      message: _isFiltering
                          ? 'Try a different word, or clear the filters to see everything again.'
                          : 'New content will appear here once the SheZen team publishes it.',
                      actionLabel: _isFiltering ? 'Clear search' : null,
                      onAction: _isFiltering ? _clearFilters : null,
                    )
                  : ListView.separated(
                      padding: const EdgeInsets.fromLTRB(
                        AppSpacing.page,
                        0,
                        AppSpacing.page,
                        AppSpacing.xxxl,
                      ),
                      itemCount: visible.length,
                      separatorBuilder: (_, _) =>
                          const SizedBox(height: AppSpacing.md),
                      itemBuilder: (context, index) => _ActivityTile(
                        activity: visible[index],
                        showThumbnail: widget.showThumbnails,
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }
}

/// Pill search box. The app's input theme paints a filled, bordered box, so the
/// decoration here strips that back to a single soft-shadowed capsule.
class _SearchField extends StatelessWidget {
  const _SearchField({
    required this.controller,
    required this.hint,
    required this.onChanged,
    required this.onClear,
  });

  final TextEditingController controller;
  final String hint;
  final ValueChanged<String> onChanged;
  final VoidCallback onClear;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadii.pill),
      border: Border.all(color: AppColors.outline),
      boxShadow: [
        BoxShadow(
          color: AppColors.primary.withValues(alpha: 0.06),
          blurRadius: 14,
          offset: const Offset(0, 6),
        ),
      ],
    ),
    child: ValueListenableBuilder<TextEditingValue>(
      valueListenable: controller,
      builder: (context, value, _) => TextField(
        controller: controller,
        onChanged: onChanged,
        textInputAction: TextInputAction.search,
        style: const TextStyle(color: AppColors.ink),
        decoration: InputDecoration(
          filled: false,
          isDense: true,
          hintText: hint,
          contentPadding: const EdgeInsets.symmetric(vertical: 15),
          prefixIcon: const Icon(
            Icons.search_rounded,
            color: AppColors.primary,
            size: 21,
          ),
          suffixIcon: value.text.isEmpty
              ? null
              : IconButton(
                  onPressed: onClear,
                  tooltip: 'Clear search',
                  icon: const Icon(
                    Icons.close_rounded,
                    color: AppColors.muted,
                    size: 19,
                  ),
                ),
          border: InputBorder.none,
          enabledBorder: InputBorder.none,
          focusedBorder: InputBorder.none,
        ),
      ),
    ),
  );
}

/// Horizontally scrolling "All / Breathing / Mindfulness…" filter row.
class _CategoryChipRow extends StatelessWidget {
  const _CategoryChipRow({
    required this.categories,
    required this.selected,
    required this.onSelected,
  });

  final List<String> categories;

  /// Lower-cased category, or null for "All".
  final String? selected;
  final ValueChanged<String?> onSelected;

  @override
  Widget build(BuildContext context) => SizedBox(
    height: 38,
    child: ListView.separated(
      scrollDirection: Axis.horizontal,
      itemCount: categories.length + 1,
      separatorBuilder: (_, _) => const SizedBox(width: AppSpacing.sm),
      itemBuilder: (context, index) {
        if (index == 0) {
          return _FilterChip(
            label: 'All',
            isSelected: selected == null,
            onTap: () => onSelected(null),
          );
        }
        final label = categories[index - 1];
        final value = label.toLowerCase();
        return _FilterChip(
          label: label,
          isSelected: selected == value,
          onTap: () => onSelected(selected == value ? null : value),
        );
      },
    ),
  );
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.isSelected,
    required this.onTap,
  });

  final String label;
  final bool isSelected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.pill),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        curve: Curves.easeOut,
        padding: const EdgeInsets.symmetric(horizontal: 16),
        alignment: Alignment.center,
        decoration: BoxDecoration(
          color: isSelected ? AppColors.primary : AppColors.surface,
          borderRadius: BorderRadius.circular(AppRadii.pill),
          border: Border.all(
            color: isSelected ? AppColors.primary : AppColors.outline,
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            color: isSelected ? Colors.white : AppColors.muted,
            fontSize: 13,
            fontWeight: FontWeight.w700,
          ),
        ),
      ),
    ),
  );
}

/// One row in a collection: artwork or a category tile, the title, a short
/// blurb, and the category the student can search by.
class _ActivityTile extends StatelessWidget {
  const _ActivityTile({required this.activity, required this.showThumbnail});

  final WellbeingActivity activity;
  final bool showThumbnail;

  @override
  Widget build(BuildContext context) {
    final tint = WellbeingPalette.tintFor(activity.category);

    return Material(
      color: Colors.transparent,
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadii.card),
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => ActivityDetailScreen(activity: activity),
          ),
        ),
        child: Ink(
          padding: const EdgeInsets.all(AppSpacing.md),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(AppRadii.card),
            border: Border.all(color: AppColors.outline),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: 0.05),
                blurRadius: 12,
                offset: const Offset(0, 5),
              ),
            ],
          ),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              if (showThumbnail && activity.hasVideo)
                _VideoThumbnail(activity: activity, tint: tint)
              else
                _CategoryTileIcon(activity: activity, tint: tint),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      activity.category.toUpperCase(),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: const TextStyle(
                        color: AppColors.primary,
                        fontSize: 10,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1,
                      ),
                    ),
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      activity.title,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w800,
                        color: AppColors.ink,
                        height: 1.25,
                      ),
                    ),
                    if (activity.description.trim().isNotEmpty) ...[
                      const SizedBox(height: AppSpacing.xs),
                      Text(
                        activity.description,
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                        style: const TextStyle(
                          color: AppColors.muted,
                          fontSize: 12.5,
                          height: 1.35,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(width: AppSpacing.sm),
              Container(
                width: 30,
                height: 30,
                decoration: BoxDecoration(color: tint, shape: BoxShape.circle),
                child: const Icon(
                  Icons.arrow_forward_ios_rounded,
                  size: 13,
                  color: AppColors.primary,
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _CategoryTileIcon extends StatelessWidget {
  const _CategoryTileIcon({required this.activity, required this.tint});

  final WellbeingActivity activity;
  final Color tint;

  @override
  Widget build(BuildContext context) => Container(
    width: 58,
    height: 58,
    decoration: BoxDecoration(
      color: tint,
      borderRadius: BorderRadius.circular(18),
    ),
    child: Icon(
      WellbeingPalette.iconFor(activity.category),
      color: AppColors.primary,
      size: 26,
    ),
  );
}

/// YouTube artwork with a play badge. Anything without a resolvable thumbnail
/// (TikTok, an unrecognised host, or no network) keeps the category wash so the
/// row never collapses or shows a broken image.
class _VideoThumbnail extends StatelessWidget {
  const _VideoThumbnail({required this.activity, required this.tint});

  final WellbeingActivity activity;
  final Color tint;

  static const _width = 104.0;
  static const _height = 74.0;

  String? get _thumbnailUrl {
    final id = YoutubePlayerController.convertUrlToId(
      activity.normalisedSourceUrl,
    );
    return id == null ? null : 'https://i.ytimg.com/vi/$id/mqdefault.jpg';
  }

  @override
  Widget build(BuildContext context) => SizedBox(
    width: _width,
    height: _height,
    child: ClipRRect(
      borderRadius: BorderRadius.circular(18),
      child: Stack(
        fit: StackFit.expand,
        children: [
          DecoratedBox(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [tint, Color.lerp(tint, AppColors.primary, 0.16)!],
              ),
            ),
            child: Icon(
              WellbeingPalette.iconFor(activity.category),
              color: AppColors.primary.withValues(alpha: 0.35),
              size: 26,
            ),
          ),
          if (_thumbnailUrl case final url?)
            Image.network(
              url,
              fit: BoxFit.cover,
              gaplessPlayback: true,
              // A failed load leaves the wash below visible.
              errorBuilder: (_, _, _) => const SizedBox.shrink(),
              frameBuilder: (context, child, frame, wasSynchronouslyLoaded) =>
                  wasSynchronouslyLoaded || frame != null
                  ? child
                  : const SizedBox.shrink(),
            ),
          Center(
            child: Container(
              width: 30,
              height: 30,
              decoration: BoxDecoration(
                color: Colors.white.withValues(alpha: 0.9),
                shape: BoxShape.circle,
                boxShadow: [
                  BoxShadow(
                    color: AppColors.ink.withValues(alpha: 0.18),
                    blurRadius: 8,
                  ),
                ],
              ),
              child: const Icon(
                Icons.play_arrow_rounded,
                color: AppColors.primary,
                size: 20,
              ),
            ),
          ),
        ],
      ),
    ),
  );
}
