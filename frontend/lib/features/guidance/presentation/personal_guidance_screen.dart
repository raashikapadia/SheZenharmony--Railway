import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:share_plus/share_plus.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../../auth/application/auth_provider.dart';
import '../data/personal_guidance.dart';
import 'guidance_detail_sheet.dart';
import 'guidance_heart_button.dart';

/// The student's Personal Guidance space: three clearly separate, fully
/// admin-managed sections — 🌿 Advice & Coping, ✨ Daily Affirmations, and
/// ☀️ Daily Wellbeing Tips. Nothing shown here is hard-coded: each section is
/// simply every published item of its content type, in the admin's own
/// order, optionally narrowed to a category.
class PersonalGuidanceScreen extends StatefulWidget {
  const PersonalGuidanceScreen({super.key, ApiService? apiService})
    : _injectedApiService = apiService;

  final ApiService? _injectedApiService;

  @override
  State<PersonalGuidanceScreen> createState() => _PersonalGuidanceScreenState();
}

class _PersonalGuidanceScreenState extends State<PersonalGuidanceScreen> {
  late final ApiService _api;

  @override
  void initState() {
    super.initState();
    _api = widget._injectedApiService ?? ApiService();
  }

  @override
  void dispose() {
    if (widget._injectedApiService == null) _api.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => DefaultTabController(
    length: 3,
    child: Scaffold(
      // Carries its own bar, and so its own back arrow: this is reached by
      // being pushed from Home, not by a tab that would supply one.
      appBar: AppBar(
        title: const AppScreenHeading(
          'Personal guidance',
          icon: Icons.eco_outlined,
        ),
        bottom: const TabBar(
          indicatorColor: AppColors.primary,
          labelColor: AppColors.primary,
          unselectedLabelColor: AppColors.muted,
          labelStyle: TextStyle(fontWeight: FontWeight.w700, fontSize: 13),
          tabs: [
            Tab(text: '🌿 Coping'),
            Tab(text: '✨ Affirmations'),
            Tab(text: '☀️ Tips'),
          ],
        ),
      ),
      body: SafeArea(
        child: TabBarView(
          children: [
            _CopingTab(api: _api),
            _AffirmationsTab(api: _api),
            _TipsTab(api: _api),
          ],
        ),
      ),
    ),
  );
}

/// 🌿 Advice & Coping — practical support for a hard moment.
class _CopingTab extends StatelessWidget {
  const _CopingTab({required this.api});

  final ApiService api;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      const _SectionBanner(
        icon: Icons.eco_rounded,
        title: 'Advice & coping',
        subtitle: 'Support for when something feels hard right now.',
        colors: [AppColors.softSage, Colors.white],
      ),
      Expanded(
        child: _BrowsableSection(
          api: api,
          types: const [GuidanceType.guidance],
          emptyIcon: Icons.eco_outlined,
          emptyTitle: 'Nothing here yet',
          emptyMessage:
              'Advice and coping strategies published by the SheZen team will appear here.',
          itemBuilder: (context, item, onToggleFavourite) => _CopingCard(
            item: item,
            onOpen: () => showGuidanceDetail(context, item),
            onToggleFavourite: onToggleFavourite,
          ),
        ),
      ),
    ],
  );
}

/// ✨ Daily Affirmations — affirmations and motivational quotes together.
class _AffirmationsTab extends StatelessWidget {
  const _AffirmationsTab({required this.api});

  final ApiService api;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      const _SectionBanner(
        icon: Icons.auto_awesome_rounded,
        title: 'Daily affirmations',
        subtitle: 'Positive reminders and encouraging words to carry with you.',
        colors: [AppColors.softLavender, AppColors.softBlush],
      ),
      Expanded(
        child: _BrowsableSection(
          api: api,
          types: const [GuidanceType.affirmation, GuidanceType.quote],
          emptyIcon: Icons.auto_awesome_outlined,
          emptyTitle: 'Nothing here yet',
          emptyMessage:
              'Affirmations and quotes published by the SheZen team will appear here.',
          itemBuilder: (context, item, onToggleFavourite) => _AffirmationCard(
            item: item,
            onToggleFavourite: onToggleFavourite,
          ),
        ),
      ),
    ],
  );
}

/// ☀️ Daily Wellbeing Tips — small everyday suggestions.
class _TipsTab extends StatelessWidget {
  const _TipsTab({required this.api});

  final ApiService api;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      const _SectionBanner(
        icon: Icons.wb_sunny_rounded,
        title: 'Daily wellbeing tips',
        subtitle: 'Small things you can do for your everyday wellbeing.',
        colors: [AppColors.softGold, Colors.white],
      ),
      Expanded(
        child: _BrowsableSection(
          api: api,
          types: const [GuidanceType.tip],
          emptyIcon: Icons.wb_sunny_outlined,
          emptyTitle: 'Nothing here yet',
          emptyMessage:
              'Everyday wellbeing tips published by the SheZen team will appear here.',
          itemBuilder: (context, item, onToggleFavourite) => _TipCard(
            item: item,
            onOpen: () => showGuidanceDetail(context, item),
            onToggleFavourite: onToggleFavourite,
          ),
        ),
      ),
    ],
  );
}

/// The soft, colour-coded banner that keeps each section instantly
/// identifiable even after scrolling past the tab bar.
class _SectionBanner extends StatelessWidget {
  const _SectionBanner({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.colors,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final List<Color> colors;

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    margin: const EdgeInsets.fromLTRB(20, 16, 20, 4),
    padding: const EdgeInsets.all(AppSpacing.lg),
    decoration: BoxDecoration(
      gradient: LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: colors,
      ),
      borderRadius: BorderRadius.circular(24),
    ),
    child: Row(
      children: [
        Container(
          width: 44,
          height: 44,
          alignment: Alignment.center,
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: 0.6),
            shape: BoxShape.circle,
          ),
          child: Icon(icon, color: AppColors.primary),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 2),
              Text(
                subtitle,
                style: const TextStyle(
                  color: AppColors.muted,
                  fontSize: 12.5,
                  height: 1.3,
                ),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

/// Loads every published item of [types] (merged when more than one, e.g.
/// affirmations and quotes together), with a category filter and an
/// optimistic-update favourite toggle. Shared by all three tabs so each one
/// only has to describe its own card and empty state.
class _BrowsableSection extends StatefulWidget {
  const _BrowsableSection({
    required this.api,
    required this.types,
    required this.emptyIcon,
    required this.emptyTitle,
    required this.emptyMessage,
    required this.itemBuilder,
  });

  final ApiService api;
  final List<GuidanceType> types;
  final IconData emptyIcon;
  final String emptyTitle;
  final String emptyMessage;
  final Widget Function(
    BuildContext context,
    PersonalGuidance item,
    VoidCallback onToggleFavourite,
  )
  itemBuilder;

  @override
  State<_BrowsableSection> createState() => _BrowsableSectionState();
}

class _BrowsableSectionState extends State<_BrowsableSection> {
  bool _loading = true;
  String? _error;
  List<PersonalGuidance> _items = const [];
  List<GuidanceCategory> _categories = const [];
  int? _categoryId;

  @override
  void initState() {
    super.initState();
    _load();
  }

  String get _token => context.read<AuthProvider>().session?.token ?? '';

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final token = _token;
      final itemLists = await Future.wait(
        widget.types.map(
          (type) => widget.api.guidanceList(
            token,
            type: type,
            categoryId: _categoryId,
          ),
        ),
      );
      final categoryLists = await Future.wait(
        widget.types.map(
          (type) => widget.api.guidanceCategories(token, type: type),
        ),
      );
      if (!mounted) return;

      final byId = <int, GuidanceCategory>{};
      for (final list in categoryLists) {
        for (final category in list) {
          byId[category.id] = category;
        }
      }
      final categories = byId.values.toList()
        ..sort((a, b) => a.name.compareTo(b.name));

      setState(() {
        _items = itemLists.expand((list) => list).toList();
        _categories = categories;
        _loading = false;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'Please check your connection and try again.';
      });
    }
  }

  void _selectCategory(int? id) {
    if (id == _categoryId) return;
    setState(() => _categoryId = id);
    _load();
  }

  Future<void> _toggleFavourite(PersonalGuidance item) async {
    final next = !item.isFavourite;
    setState(() => _items = _replace(item, next));
    try {
      await widget.api.setGuidanceFavourite(_token, item.id, favourite: next);
    } catch (_) {
      if (!mounted) return;
      setState(() => _items = _replace(item, !next)); // roll back
    }
  }

  List<PersonalGuidance> _replace(PersonalGuidance item, bool isFavourite) =>
      _items
          .map(
            (i) => i.id == item.id ? i.copyWith(isFavourite: isFavourite) : i,
          )
          .toList();

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const AppLoadingView(message: 'Just a moment…');
    }

    if (_error != null) {
      return AppStateView(
        icon: Icons.cloud_off_outlined,
        title: 'Couldn\'t load this',
        message: _error!,
        actionLabel: 'Try again',
        onAction: _load,
      );
    }

    return RefreshIndicator(
      onRefresh: _load,
      child: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        children: [
          if (_categories.isNotEmpty) ...[
            _CategoryChipRow(
              categories: _categories,
              selectedId: _categoryId,
              onSelect: _selectCategory,
            ),
            const SizedBox(height: AppSpacing.md),
          ],
          if (_items.isEmpty)
            AppStateView(
              icon: widget.emptyIcon,
              title: widget.emptyTitle,
              message: widget.emptyMessage,
            )
          else
            for (final item in _items) ...[
              widget.itemBuilder(context, item, () => _toggleFavourite(item)),
              const SizedBox(height: AppSpacing.md),
            ],
        ],
      ),
    );
  }
}

/// "All" plus one chip per category actually in use — never a dead end.
class _CategoryChipRow extends StatelessWidget {
  const _CategoryChipRow({
    required this.categories,
    required this.selectedId,
    required this.onSelect,
  });

  final List<GuidanceCategory> categories;
  final int? selectedId;
  final ValueChanged<int?> onSelect;

  @override
  Widget build(BuildContext context) => SingleChildScrollView(
    scrollDirection: Axis.horizontal,
    child: Row(
      children: [
        _FilterChip(
          label: 'All',
          selected: selectedId == null,
          onTap: () => onSelect(null),
        ),
        for (final category in categories) ...[
          const SizedBox(width: AppSpacing.sm),
          _FilterChip(
            label: category.name,
            selected: selectedId == category.id,
            onTap: () => onSelect(category.id),
          ),
        ],
      ],
    ),
  );
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => ChoiceChip(
    label: Text(label),
    selected: selected,
    onSelected: (_) => onTap(),
    showCheckmark: false,
    selectedColor: AppColors.primary,
    backgroundColor: AppColors.softLavender,
    side: BorderSide.none,
    labelStyle: TextStyle(
      color: selected ? Colors.white : AppColors.ink,
      fontWeight: FontWeight.w600,
      fontSize: 13,
    ),
  );
}

/// A tag naming an item's category, in the same soft pill used elsewhere.
class _CategoryTag extends StatelessWidget {
  const _CategoryTag(this.label);

  final String label;

  @override
  Widget build(BuildContext context) =>
      _MetaChip(icon: Icons.sell_outlined, label: label);
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

/// One piece of advice in 🌿 Advice & Coping: a title, the advice itself
/// (truncated with "Read more" when there's more to see), an optional "try
/// this" action, and an optional external resource link.
class _CopingCard extends StatelessWidget {
  const _CopingCard({
    required this.item,
    required this.onOpen,
    required this.onToggleFavourite,
  });

  final PersonalGuidance item;
  final VoidCallback onOpen;
  final VoidCallback onToggleFavourite;

  @override
  Widget build(BuildContext context) {
    final hasMore =
        item.content.length > 140 ||
        item.tryThis != null ||
        item.resourceUrl != null;

    return Card(
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadii.card),
        onTap: onOpen,
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (item.category != null) ...[
                          _CategoryTag(item.category!),
                          const SizedBox(height: AppSpacing.xs),
                        ],
                        Text(
                          item.title ?? 'Advice',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                      ],
                    ),
                  ),
                  GuidanceHeartButton(
                    isFavourite: item.isFavourite,
                    onTap: onToggleFavourite,
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.xs),
              Text(
                item.content,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: AppColors.ink, height: 1.4),
              ),
              if (item.tryThis != null || item.resourceUrl != null) ...[
                const SizedBox(height: AppSpacing.md),
                Wrap(
                  spacing: AppSpacing.sm,
                  runSpacing: AppSpacing.xs,
                  children: [
                    if (item.tryThis != null)
                      const _MetaChip(
                        icon: Icons.bolt_rounded,
                        label: 'Try this',
                      ),
                    if (item.resourceUrl != null)
                      const _MetaChip(
                        icon: Icons.open_in_new_rounded,
                        label: 'Resource',
                      ),
                  ],
                ),
              ],
              if (hasMore) ...[
                const SizedBox(height: AppSpacing.sm),
                const Align(
                  alignment: Alignment.centerRight,
                  child: Text(
                    'Read more →',
                    style: TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w700,
                      fontSize: 12.5,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// One piece of ✨ Daily Affirmations content — an affirmation or a
/// motivational quote, styled identically, with a share button.
class _AffirmationCard extends StatelessWidget {
  const _AffirmationCard({required this.item, required this.onToggleFavourite});

  final PersonalGuidance item;
  final VoidCallback onToggleFavourite;

  bool get _isQuote => item.type == GuidanceType.quote;

  void _share() {
    final author = item.author;
    final text = author == null || author.isEmpty
        ? '"${item.content}"'
        : '"${item.content}" — $author';
    SharePlus.instance.share(ShareParams(text: text));
  }

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    padding: const EdgeInsets.all(AppSpacing.xl),
    decoration: BoxDecoration(
      color: AppColors.softLavender,
      borderRadius: BorderRadius.circular(AppRadii.card),
      border: Border.all(color: AppColors.outline),
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            Icon(
              _isQuote
                  ? Icons.format_quote_rounded
                  : Icons.auto_awesome_rounded,
              color: AppColors.primary,
              size: 24,
            ),
            const Spacer(),
            if (item.category != null) _CategoryTag(item.category!),
          ],
        ),
        const SizedBox(height: AppSpacing.sm),
        Text(
          '“${item.content}”',
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
            fontStyle: FontStyle.italic,
            height: 1.4,
          ),
        ),
        if (item.author != null && item.author!.isNotEmpty) ...[
          const SizedBox(height: AppSpacing.sm),
          Text(
            '— ${item.author}',
            style: const TextStyle(
              color: AppColors.primary,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
        const SizedBox(height: AppSpacing.md),
        Row(
          children: [
            GuidanceHeartButton(
              isFavourite: item.isFavourite,
              onTap: onToggleFavourite,
            ),
            const Spacer(),
            IconButton(
              tooltip: 'Share this',
              icon: const Icon(Icons.share_outlined, color: AppColors.primary),
              onPressed: _share,
            ),
          ],
        ),
      ],
    ),
  );
}

/// One suggestion in ☀️ Daily Wellbeing Tips: a title, a short description,
/// and an optional suggested action.
class _TipCard extends StatelessWidget {
  const _TipCard({
    required this.item,
    required this.onOpen,
    required this.onToggleFavourite,
  });

  final PersonalGuidance item;
  final VoidCallback onOpen;
  final VoidCallback onToggleFavourite;

  @override
  Widget build(BuildContext context) {
    final hasMore = item.content.length > 140;

    return Material(
      color: AppColors.softGold,
      borderRadius: BorderRadius.circular(AppRadii.card),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadii.card),
        onTap: onOpen,
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        if (item.category != null) ...[
                          _CategoryTag(item.category!),
                          const SizedBox(height: AppSpacing.xs),
                        ],
                        Text(
                          item.title ?? 'Wellbeing tip',
                          style: Theme.of(context).textTheme.titleMedium,
                        ),
                      ],
                    ),
                  ),
                  GuidanceHeartButton(
                    isFavourite: item.isFavourite,
                    onTap: onToggleFavourite,
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.xs),
              Text(
                item.content,
                maxLines: 3,
                overflow: TextOverflow.ellipsis,
                style: const TextStyle(color: AppColors.ink, height: 1.4),
              ),
              if (item.tryThis != null) ...[
                const SizedBox(height: AppSpacing.md),
                _MetaChip(
                  icon: Icons.check_circle_outline_rounded,
                  label: item.tryThis!,
                ),
              ],
              if (hasMore) ...[
                const SizedBox(height: AppSpacing.sm),
                const Align(
                  alignment: Alignment.centerRight,
                  child: Text(
                    'Read more →',
                    style: TextStyle(
                      color: AppColors.primary,
                      fontWeight: FontWeight.w700,
                      fontSize: 12.5,
                    ),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}
