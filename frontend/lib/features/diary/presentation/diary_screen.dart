import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../data/diary.dart';
import '../data/diary_lock.dart';
import 'diary_composer.dart';
import 'diary_page_editor_screen.dart';
import 'diary_pin.dart';
import 'diary_ui.dart';

/// Items in the diary's overflow menu. Which of them are offered depends on
/// whether the diary currently has a PIN.
enum _DiaryAction { rename, lock, unlock, changePin, delete }

/// One diary and its pages.
///
/// Edits are handed to [onSave], which returns false when the device refused
/// the write. This screen only updates what it shows once that succeeds.
class DiaryScreen extends StatefulWidget {
  const DiaryScreen({
    super.key,
    required this.diary,
    required this.hasPin,
    required this.onSave,
    required this.onDelete,
    required this.onSetPin,
  });

  final Diary diary;

  /// Whether the student has already chosen their PIN. If they have, locking
  /// this diary reuses it rather than asking for another.
  final bool hasPin;

  final Future<bool> Function(Diary diary) onSave;
  final Future<bool> Function(Diary diary) onDelete;

  /// Sets the student's one PIN, or clears it when given null.
  final Future<bool> Function(DiaryLock? lock) onSetPin;

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

  /// Puts this diary behind the student's PIN.
  ///
  /// Only asks for a PIN if they have not chosen one yet. Once they have, every
  /// diary they lock uses that same PIN — they are never asked to invent or
  /// remember a second one.
  Future<void> _lockDiary() async {
    final messenger = ScaffoldMessenger.of(context);

    if (!widget.hasPin) {
      final pin = await showDiaryPinSetup(
        context,
        heading: 'Choose your PIN',
        subheading:
            'One PIN for your diary. You will need it to open '
            '"${_diary.title}" and anything else you lock.',
      );
      if (pin == null || !mounted) return;
      if (!await widget.onSetPin(DiaryLock.fromPin(pin)) || !mounted) return;
    }

    final updated = _diary.locked();
    if (await widget.onSave(updated) && mounted) {
      setState(() => _diary = updated);
      messenger.showSnackBar(
        const SnackBar(content: Text('This diary is locked.')),
      );
    }
  }

  /// Changes the PIN for every diary the student has locked, not just this one.
  ///
  /// The current PIN is not asked for again: reaching this menu meant unlocking
  /// a diary with it a moment ago.
  Future<void> _changePin() async {
    final messenger = ScaffoldMessenger.of(context);
    final pin = await showDiaryPinSetup(
      context,
      heading: 'Choose a new PIN',
      subheading: 'This replaces the PIN for every diary you have locked.',
    );
    if (pin == null || !mounted) return;

    if (await widget.onSetPin(DiaryLock.fromPin(pin)) && mounted) {
      messenger.showSnackBar(const SnackBar(content: Text('PIN changed.')));
    }
  }

  /// Takes this diary out from behind the PIN, leaving the PIN itself alone.
  Future<void> _unlockDiary() async {
    final messenger = ScaffoldMessenger.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Stop locking this diary?'),
        content: const Text(
          'It will open straight from the list, without asking for anything. '
          'Your PIN stays as it is, and your other locked diaries still use '
          'it.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Keep it locked'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Stop locking'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final updated = _diary.unlocked();
    if (await widget.onSave(updated) && mounted) {
      setState(() => _diary = updated);
      messenger.showSnackBar(
        const SnackBar(content: Text('This diary is no longer locked.')),
      );
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
          PopupMenuButton<_DiaryAction>(
            tooltip: 'Diary options',
            onSelected: (action) => switch (action) {
              _DiaryAction.rename => _rename(),
              _DiaryAction.lock => _lockDiary(),
              _DiaryAction.unlock => _unlockDiary(),
              _DiaryAction.changePin => _changePin(),
              _DiaryAction.delete => _confirmDelete(),
            },
            itemBuilder: (_) => [
              const PopupMenuItem(
                value: _DiaryAction.rename,
                child: Text('Rename or recolour'),
              ),
              if (_diary.isLocked) ...[
                const PopupMenuItem(
                  value: _DiaryAction.unlock,
                  child: Text('Stop locking this diary'),
                ),
                // Worded so it is clear this is not a per-diary setting.
                const PopupMenuItem(
                  value: _DiaryAction.changePin,
                  child: Text('Change your PIN'),
                ),
              ] else
                PopupMenuItem(
                  value: _DiaryAction.lock,
                  child: Text(
                    widget.hasPin ? 'Lock with your PIN' : 'Lock with a PIN',
                  ),
                ),
              const PopupMenuItem(
                value: _DiaryAction.delete,
                child: Text('Delete diary'),
              ),
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
