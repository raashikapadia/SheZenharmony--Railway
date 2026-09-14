import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../data/diary.dart';
import 'diary_ui.dart';

/// Writing surface for a single diary page.
///
/// Saving is explicit. [onSave] returns false when the device refused the
/// write, in which case the editor stays open with the text intact rather than
/// closing and losing it.
class DiaryPageEditorScreen extends StatefulWidget {
  const DiaryPageEditorScreen({
    super.key,
    required this.page,
    required this.diaryTitle,
    required this.isNew,
    required this.onSave,
    required this.onDelete,
  });

  final DiaryPage page;
  final String diaryTitle;
  final bool isNew;
  final Future<bool> Function(DiaryPage page) onSave;
  final Future<bool> Function(DiaryPage page) onDelete;

  @override
  State<DiaryPageEditorScreen> createState() => _DiaryPageEditorScreenState();
}

class _DiaryPageEditorScreenState extends State<DiaryPageEditorScreen> {
  late final _titleController = TextEditingController(text: widget.page.title)
    ..addListener(_onChanged);
  late final _bodyController = TextEditingController(text: widget.page.body)
    ..addListener(_onChanged);

  bool _isSaving = false;

  @override
  void dispose() {
    _titleController.dispose();
    _bodyController.dispose();
    super.dispose();
  }

  void _onChanged() => setState(() {});

  bool get _isEmpty =>
      _titleController.text.trim().isEmpty &&
      _bodyController.text.trim().isEmpty;

  bool get _isDirty =>
      _titleController.text != widget.page.title ||
      _bodyController.text != widget.page.body;

  Future<void> _save() async {
    final page = widget.page.copyWith(
      title: _titleController.text.trim(),
      body: _bodyController.text,
      updatedAt: DateTime.now(),
    );

    setState(() => _isSaving = true);
    final saved = await widget.onSave(page);
    if (!mounted) return;
    setState(() => _isSaving = false);

    // A failed write already showed its own message; staying put keeps the
    // student's words on screen so they can try again.
    if (saved) Navigator.of(context).pop();
  }

  Future<void> _confirmDelete() async {
    final navigator = Navigator.of(context);
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete this page?'),
        content: const Text('This page will be erased. This cannot be undone.'),
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
    if (await widget.onDelete(widget.page)) navigator.pop();
  }

  Future<bool> _confirmDiscard() async {
    final discard = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Leave without saving?'),
        content: const Text('Your changes on this page will be lost.'),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Keep writing'),
          ),
          TextButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Discard'),
          ),
        ],
      ),
    );
    return discard ?? false;
  }

  @override
  Widget build(BuildContext context) => PopScope(
    canPop: !_isDirty,
    onPopInvokedWithResult: (didPop, _) async {
      if (didPop) return;
      final navigator = Navigator.of(context);
      if (await _confirmDiscard()) navigator.pop();
    },
    child: Scaffold(
      appBar: AppBar(
        title: Text(widget.isNew ? 'New page' : 'Page'),
        actions: [
          if (!widget.isNew)
            IconButton(
              onPressed: _isSaving ? null : _confirmDelete,
              tooltip: 'Delete page',
              icon: const Icon(Icons.delete_outline_rounded),
            ),
          Padding(
            padding: const EdgeInsets.only(right: AppSpacing.sm),
            child: TextButton(
              onPressed: _isSaving || _isEmpty || !_isDirty ? null : _save,
              child: _isSaving
                  ? const SizedBox(
                      width: 18,
                      height: 18,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text('Save'),
            ),
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.page,
            AppSpacing.sm,
            AppSpacing.page,
            AppSpacing.xxxl,
          ),
          children: [
            Row(
              children: [
                const Icon(
                  Icons.lock_rounded,
                  size: 13,
                  color: AppColors.primary,
                ),
                const SizedBox(width: 6),
                Expanded(
                  child: Text(
                    '${widget.diaryTitle} · '
                    '${formatDiaryDate(widget.isNew ? DateTime.now() : widget.page.createdAt)}',
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
            const SizedBox(height: AppSpacing.md),
            TextField(
              controller: _titleController,
              autofocus: widget.isNew,
              textCapitalization: TextCapitalization.sentences,
              textInputAction: TextInputAction.next,
              style: Theme.of(context).textTheme.headlineSmall,
              decoration: const InputDecoration(
                filled: false,
                isDense: true,
                contentPadding: EdgeInsets.zero,
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                hintText: 'Title (optional)',
                hintStyle: TextStyle(
                  color: AppColors.outline,
                  fontSize: 20,
                  fontWeight: FontWeight.w800,
                ),
              ),
            ),
            const Divider(height: AppSpacing.xxl),
            TextField(
              controller: _bodyController,
              maxLines: null,
              minLines: 12,
              textCapitalization: TextCapitalization.sentences,
              keyboardType: TextInputType.multiline,
              style: const TextStyle(
                fontSize: 15.5,
                height: 1.6,
                color: AppColors.ink,
              ),
              decoration: const InputDecoration(
                filled: false,
                isDense: true,
                contentPadding: EdgeInsets.zero,
                border: InputBorder.none,
                enabledBorder: InputBorder.none,
                focusedBorder: InputBorder.none,
                hintText: 'Write whatever you want. Nobody else will see it.',
                hintStyle: TextStyle(
                  color: AppColors.muted,
                  fontSize: 15.5,
                  height: 1.6,
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}
