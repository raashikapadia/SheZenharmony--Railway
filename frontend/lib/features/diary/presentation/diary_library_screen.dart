import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../data/diary.dart';
import '../data/diary_storage.dart';
import 'diary_composer.dart';
import 'diary_screen.dart';
import 'diary_ui.dart';

/// The student's private diaries.
///
/// This screen owns the whole collection and is the only thing that writes to
/// storage; the diary and page screens hand their edits back through
/// callbacks, so a failed write can never leave the UI claiming something was
/// saved when it was not.
class DiaryLibraryScreen extends StatefulWidget {
  const DiaryLibraryScreen({super.key, DiaryStorage? storage})
    : _injectedStorage = storage;

  final DiaryStorage? _injectedStorage;

  @override
  State<DiaryLibraryScreen> createState() => _DiaryLibraryScreenState();
}

class _DiaryLibraryScreenState extends State<DiaryLibraryScreen> {
  late final DiaryStorage _storage = widget._injectedStorage ?? DiaryStorage();

  /// Null while loading.
  List<Diary>? _diaries;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _error = null;
      _diaries = null;
    });
    try {
      final diaries = await _storage.readAll();
      if (!mounted) return;
      setState(() => _diaries = diaries);
    } on DiaryStorageException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.message);
    }
  }

  /// Writes first and only then updates the UI, so the list on screen always
  /// reflects what is actually on the device.
  Future<bool> _persist(List<Diary> next) async {
    final messenger = ScaffoldMessenger.of(context);
    final sorted = [...next]
      ..sort((a, b) => b.updatedAt.compareTo(a.updatedAt));
    try {
      await _storage.writeAll(sorted);
    } on DiaryStorageException catch (error) {
      messenger.showSnackBar(SnackBar(content: Text(error.message)));
      return false;
    }
    if (!mounted) return true;
    setState(() => _diaries = sorted);
    return true;
  }

  Future<bool> _saveDiary(Diary updated) {
    final next = [...?_diaries];
    final index = next.indexWhere((diary) => diary.id == updated.id);
    if (index == -1) {
      next.insert(0, updated);
    } else {
      next[index] = updated;
    }
    return _persist(next);
  }

  Future<bool> _deleteDiary(Diary diary) =>
      _persist([...?_diaries?.where((entry) => entry.id != diary.id)]);

  Future<void> _createDiary() async {
    final result = await showDiaryComposer(
      context,
      heading: 'New diary',
      actionLabel: 'Create diary',
    );
    if (result == null || !mounted) return;

    final diary = Diary.create(
      title: result.title,
      coverIndex: result.coverIndex,
    );
    if (!await _saveDiary(diary) || !mounted) return;

    _openDiary(diary);
  }

  void _openDiary(Diary diary) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => DiaryScreen(
          diary: diary,
          onSave: _saveDiary,
          onDelete: _deleteDiary,
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final diaries = _diaries;

    return Scaffold(
      appBar: AppBar(title: const Text('My diary')),
      floatingActionButton: diaries == null
          ? null
          : FloatingActionButton.extended(
              onPressed: _createDiary,
              backgroundColor: AppColors.primary,
              foregroundColor: Colors.white,
              icon: const Icon(Icons.add_rounded),
              label: const Text('New diary'),
            ),
      body: SafeArea(child: _buildBody(diaries)),
    );
  }

  Widget _buildBody(List<Diary>? diaries) {
    if (_error case final error?) {
      return AppStateView(
        icon: Icons.lock_outline_rounded,
        title: 'Couldn\'t open your diary',
        message: error,
        actionLabel: 'Try again',
        onAction: _load,
      );
    }
    if (diaries == null) {
      return const AppLoadingView(message: 'Unlocking your diary…');
    }

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.page,
        AppSpacing.md,
        AppSpacing.page,
        // Clears the extended FAB.
        96,
      ),
      children: [
        const DiaryPrivacyNote(),
        const SizedBox(height: AppSpacing.xxl),
        if (diaries.isEmpty)
          const _EmptyDiaryPrompt()
        else ...[
          Text(
            diaries.length == 1 ? '1 DIARY' : '${diaries.length} DIARIES',
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
              color: AppColors.primary,
              fontWeight: FontWeight.w800,
              letterSpacing: 1.2,
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          for (final diary in diaries) ...[
            _DiaryCard(diary: diary, onTap: () => _openDiary(diary)),
            const SizedBox(height: AppSpacing.md),
          ],
        ],
      ],
    );
  }
}

class _EmptyDiaryPrompt extends StatelessWidget {
  const _EmptyDiaryPrompt();

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
        const DiaryCover(coverIndex: 0, size: 68),
        const SizedBox(height: AppSpacing.lg),
        Text(
          'Nothing written yet',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        const SizedBox(height: AppSpacing.xs),
        const Text(
          'Make a diary and start a first page whenever you feel like it. '
          'There is no right way to fill it.',
          textAlign: TextAlign.center,
          style: TextStyle(color: AppColors.muted, height: 1.45),
        ),
      ],
    ),
  );
}

class _DiaryCard extends StatelessWidget {
  const _DiaryCard({required this.diary, required this.onTap});

  final Diary diary;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.card),
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
          children: [
            DiaryCover(coverIndex: diary.coverIndex),
            const SizedBox(width: AppSpacing.md),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    diary.title,
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                    style: Theme.of(context).textTheme.titleSmall?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: 3),
                  Text(
                    '${diary.pages.length == 1 ? '1 page' : '${diary.pages.length} pages'}'
                    ' · ${formatDiaryDate(diary.updatedAt)}',
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 12.5,
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(width: AppSpacing.sm),
            Container(
              width: 30,
              height: 30,
              decoration: BoxDecoration(
                color: diaryCoverColor(diary.coverIndex),
                shape: BoxShape.circle,
              ),
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
