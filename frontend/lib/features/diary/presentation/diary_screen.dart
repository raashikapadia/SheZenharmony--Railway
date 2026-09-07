import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../data/diary.dart';
import 'diary_composer.dart';
import 'diary_page_editor_screen.dart';
import 'diary_ui.dart';

/// One diary and its pages.
///
/// Edits are handed to [onSave], which returns false when the device refused
/// the write. This screen only updates what it shows once that succeeds.
class DiaryScreen extends StatefulWidget {
  const DiaryScreen({
    super.key,
    required this.diary,
    required this.onSave,
    required this.onDelete,
  });

  final Diary diary;
  final Future<bool> Function(Diary diary) onSave;
  final Future<bool> Function(Diary diary) onDelete;

  @override
  State<DiaryScreen> createState() => _DiaryScreenState();
}

class _DiaryScreenState extends State<DiaryScreen> {
  late Diary _diary = widget.diary;

  List<DiaryPage> get _pages =>
      [..._diary.pages]..sort((a, b) => b.updatedAt.compareTo(a.updatedAt));

  Future<bool> _savePage(DiaryPage page) async {
    final updated = _diary.withPage(page);
    final saved = await widget.onSave(updated);
    if (saved && mounted) setState(() => _diary = updated);
    return saved;
  }

  Future<bool> _deletePage(DiaryPage page) async {
    final updated = _diary.withoutPage(page.id);
    final saved = await widget.onSave(updated);
    if (saved && mounted) setState(() => _diary = updated);
    return saved;
  }

  void _openPage(DiaryPage page, {required bool isNew}) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => DiaryPageEditorScreen(
          page: page,
          diaryTitle: _diary.title,
          isNew: isNew,
          onSave: _savePage,
          onDelete: _deletePage,
        ),
      ),
    );
  }

  Future<void> _rename() async {
    final result = await showDiaryComposer(
      context,
      heading: 'Rename diary',
      actionLabel: 'Save changes',
      initialTitle: _diary.title,
      initialCoverIndex: _diary.coverIndex,
    );
    if (result == null || !mounted) return;

    final updated = _diary.copyWith(
      title: result.title,
      coverIndex: result.coverIndex,
      updatedAt: DateTime.now(),
    );
    if (await widget.onSave(updated) && mounted) {
      setState(() => _diary = updated);
    }
  }

  Future<void> _confirmDelete() async {
    final navigator = Navigator.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete this diary?'),
        content: Text(
          _diary.pages.isEmpty
              ? 'This diary will be removed from this phone.'
              : 'Its ${_diary.pages.length} '
                    '${_diary.pages.length == 1 ? 'page' : 'pages'} will be '
                    'permanently erased. This cannot be undone.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Keep it'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
            ),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed != true) return;
    if (await widget.onDelete(_diary)) navigator.pop();
  }

  @override
  Widget build(BuildContext context) {
    final pages = _pages;

    return Scaffold(
      appBar: AppBar(
        title: Text(_diary.title, overflow: TextOverflow.ellipsis),
        actions: [
          PopupMenuButton<String>(
            tooltip: 'Diary options',
            onSelected: (value) =>
                value == 'rename' ? _rename() : _confirmDelete(),
            itemBuilder: (_) => const [
              PopupMenuItem(value: 'rename', child: Text('Rename or recolour')),
              PopupMenuItem(value: 'delete', child: Text('Delete diary')),
            ],
          ),
        ],
      ),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: () => _openPage(DiaryPage.blank(), isNew: true),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        icon: const Icon(Icons.edit_rounded),
        label: const Text('New page'),
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.page,
            AppSpacing.md,
            AppSpacing.page,
            96,
          ),
          children: [
            _DiaryHeader(diary: _diary),
            const SizedBox(height: AppSpacing.xxl),
            if (pages.isEmpty)
              const _EmptyPagePrompt()
            else ...[
              Text(
                pages.length == 1 ? '1 PAGE' : '${pages.length} PAGES',
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: AppColors.primary,
                  fontWeight: FontWeight.w800,
                  letterSpacing: 1.2,
                ),
              ),
              const SizedBox(height: AppSpacing.md),
              for (final page in pages) ...[
                _PageCard(
                  page: page,
                  coverIndex: _diary.coverIndex,
                  onTap: () => _openPage(page, isNew: false),
                ),
                const SizedBox(height: AppSpacing.md),
              ],
            ],
          ],
        ),
      ),
    );
  }
}

class _DiaryHeader extends StatelessWidget {
  const _DiaryHeader({required this.diary});

  final Diary diary;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(AppSpacing.xl),
    decoration: BoxDecoration(
      color: diaryCoverColor(diary.coverIndex),
      borderRadius: BorderRadius.circular(AppRadii.card),
      border: Border.all(color: AppColors.primary.withValues(alpha: 0.08)),
    ),
    child: Row(
      children: [
        const _HeaderGlyph(),
        const SizedBox(width: AppSpacing.lg),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                diary.title,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(
                  context,
                ).textTheme.titleMedium?.copyWith(color: AppColors.primary),
              ),
              const SizedBox(height: 3),
              Text(
                'Started ${formatDiaryDate(diary.createdAt)}',
                style: const TextStyle(color: AppColors.muted, fontSize: 12.5),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

/// The header sits on the diary's own cover wash, so its glyph goes on a white
/// tile rather than reusing [DiaryCover] and disappearing into the background.
class _HeaderGlyph extends StatelessWidget {
  const _HeaderGlyph();

  @override
  Widget build(BuildContext context) => Container(
    width: 54,
    height: 54,
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(18),
    ),
    child: const Icon(
      Icons.menu_book_rounded,
      color: AppColors.primary,
      size: 25,
    ),
  );
}

class _EmptyPagePrompt extends StatelessWidget {
  const _EmptyPagePrompt();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(AppSpacing.xxl),
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadii.card),
      border: Border.all(color: AppColors.outline),
    ),
    child: Column(
      children: [
        Container(
          width: 58,
          height: 58,
          decoration: const BoxDecoration(
            color: AppColors.softLavender,
            shape: BoxShape.circle,
          ),
          child: const Icon(
            Icons.edit_note_rounded,
            size: 28,
            color: AppColors.primary,
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        Text(
          'This diary is empty',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: AppSpacing.xs),
        const Text(
          'Add a page whenever you want to get something down. '
          'A sentence counts.',
          textAlign: TextAlign.center,
          style: TextStyle(color: AppColors.muted, height: 1.45),
        ),
      ],
    ),
  );
}

class _PageCard extends StatelessWidget {
  const _PageCard({
    required this.page,
    required this.coverIndex,
    required this.onTap,
  });

  final DiaryPage page;
  final int coverIndex;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final preview = page.body.trim().replaceAll(RegExp(r'\s+'), ' ');

    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.card),
        child: Ink(
          padding: const EdgeInsets.all(AppSpacing.lg),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(AppRadii.card),
            border: Border.all(color: AppColors.outline),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Container(
                    width: 8,
                    height: 8,
                    decoration: BoxDecoration(
                      color: diaryCoverColor(coverIndex),
                      shape: BoxShape.circle,
                      border: Border.all(
                        color: AppColors.primary.withValues(alpha: 0.25),
                      ),
                    ),
                  ),
                  const SizedBox(width: AppSpacing.sm),
                  Text(
                    formatDiaryDate(page.updatedAt),
                    style: const TextStyle(
                      color: AppColors.primary,
                      fontSize: 11,
                      fontWeight: FontWeight.w800,
                      letterSpacing: 0.6,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                page.title.trim().isEmpty ? 'Untitled page' : page.title,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
              ),
              if (preview.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.xs),
                Text(
                  preview,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppColors.muted,
                    fontSize: 12.5,
                    height: 1.4,
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
