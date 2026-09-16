import 'dart:async';

import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../../auth/application/auth_provider.dart';
import '../data/diary.dart';
import '../data/diary_lock.dart';
import '../data/diary_storage.dart';
import '../data/diary_sync_service.dart';
import '../data/diary_tombstone.dart';
import 'diary_composer.dart';
import 'diary_pin.dart';
import 'diary_screen.dart';
import 'diary_ui.dart';

/// The student's private diaries.
///
/// This screen owns the whole collection and is the only thing that writes to
/// storage; the diary and page screens hand their edits back through
/// callbacks, so a failed write can never leave the UI claiming something was
/// saved when it was not.
///
/// The device copy stays authoritative for what is on screen, so writing works
/// with no signal. Syncing runs alongside it and never blocks the student: a
/// failed sync leaves the local diary exactly as it was, to be offered again
/// next time.
class DiaryLibraryScreen extends StatefulWidget {
  const DiaryLibraryScreen({
    super.key,
    DiaryStorage? storage,
    DiarySyncService? syncService,
  }) : _injectedStorage = storage,
       _injectedSync = syncService;

  final DiaryStorage? _injectedStorage;
  final DiarySyncService? _injectedSync;

  @override
  State<DiaryLibraryScreen> createState() => _DiaryLibraryScreenState();
}

class _DiaryLibraryScreenState extends State<DiaryLibraryScreen> {
  late final DiaryStorage _storage = widget._injectedStorage ?? DiaryStorage();
  late final DiarySyncService _sync =
      widget._injectedSync ?? DiarySyncService(storage: _storage);

  /// Null while loading.
  List<Diary>? _diaries;

  /// The student's one PIN, or null if they have never set one. Every diary
  /// they lock opens with this.
  DiaryLock? _lock;

  String? _error;
  bool _isSyncing = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  /// Shows the device copy first, then reconciles.
  ///
  /// Reading locally before syncing is what keeps the diary instant and usable
  /// offline; on a fresh install the local read simply comes back empty and the
  /// sync is what fills the screen.
  Future<void> _load() async {
    setState(() {
      _error = null;
      _diaries = null;
    });
    try {
      final diaries = await _storage.readAll();
      final lock = await _storage.readLock();
      if (!mounted) return;
      setState(() {
        _diaries = diaries;
        _lock = lock?.lock;
      });
    } on DiaryStorageException catch (error) {
      if (!mounted) return;
      setState(() => _error = error.message);
      return;
    }
    await _syncNow();
  }

  /// Reconciles with the server, if there is a signed-in session to do it with.
  ///
  /// Signed out, this is a no-op and the diary simply stays on the device.
  Future<void> _syncNow({bool reportFailure = false}) async {
    final token = context.read<AuthProvider>().session?.token;
    if (token == null || _isSyncing) return;

    final messenger = ScaffoldMessenger.of(context);
    setState(() => _isSyncing = true);
    try {
      final merged = await _sync.sync(token);
      if (!mounted) return;
      setState(() {
        _diaries = merged.diaries;
        _lock = merged.lock;
      });
    } on ApiException catch (error) {
      // Offline or the server is unreachable. The local copy is untouched and
      // will be offered again, so this is only worth saying out loud when the
      // student asked for the sync.
      if (mounted && reportFailure) {
        messenger.showSnackBar(SnackBar(content: Text(error.message)));
      }
    } on DiaryStorageException catch (error) {
      if (mounted && reportFailure) {
        messenger.showSnackBar(SnackBar(content: Text(error.message)));
      }
    } finally {
      if (mounted) setState(() => _isSyncing = false);
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

  Future<bool> _saveDiary(Diary updated) async {
    final next = [...?_diaries];
    final index = next.indexWhere((diary) => diary.id == updated.id);
    if (index == -1) {
      next.insert(0, updated);
    } else {
      await _recordDeletedPages(before: next[index], after: updated);
      next[index] = updated;
    }

    if (!await _persist(next)) return false;
    unawaited(_syncNow());
    return true;
  }

  /// Notices pages the diary screen dropped and marks them as deleted.
  ///
  /// The page screens hand back a whole diary rather than telling us what
  /// changed, so a removed page is only visible as a gap between the old copy
  /// and the new one. Without this, deleting a page would be undone by the very
  /// next sync, which still holds the server's copy of it.
  Future<void> _recordDeletedPages({
    required Diary before,
    required Diary after,
  }) async {
    final remaining = after.pages.map((page) => page.id).toSet();
    final removed = before.pages.where((page) => !remaining.contains(page.id));
    final now = DateTime.now();

    for (final page in removed) {
      await _storage.addTombstone(
        DiaryTombstone(diaryId: before.id, pageId: page.id, deletedAt: now),
      );
    }
  }

  Future<bool> _deleteDiary(Diary diary) async {
    // Recorded before the write, so a delete that survives on screen is one the
    // server will hear about even if the app is closed straight afterwards.
    await _storage.addTombstone(
      DiaryTombstone(
        diaryId: diary.id,
        pageId: null,
        deletedAt: DateTime.now(),
      ),
    );

    final kept = [...?_diaries?.where((entry) => entry.id != diary.id)];
    if (!await _persist(kept)) return false;
    unawaited(_syncNow());
    return true;
  }

  /// Asks for the student's PIN the first time they lock anything.
  ///
  /// Returns false if they backed out, which the caller treats as abandoning
  /// whatever it was about to lock — quietly leaving it unlocked would give
  /// them the opposite of what they asked for.
  Future<bool> _chooseFirstPin(String diaryTitle) async {
    final pin = await showDiaryPinSetup(
      context,
      heading: 'Choose your PIN',
      subheading:
          'One PIN for your diary. You will need it to open "$diaryTitle" and '
          'anything else you lock.',
    );
    if (pin == null || !mounted) return false;

    return _saveLock(DiaryLock.fromPin(pin));
  }

  /// Stores the student's PIN, or clears it when [lock] is null.
  Future<bool> _saveLock(DiaryLock? lock) async {
    final messenger = ScaffoldMessenger.of(context);
    try {
      await _storage.writeLock(
        DiaryLockState(lock: lock, updatedAt: DateTime.now()),
      );
    } on DiaryStorageException catch (error) {
      messenger.showSnackBar(SnackBar(content: Text(error.message)));
      return false;
    }
    if (mounted) setState(() => _lock = lock);
    return true;
  }

  Future<void> _createDiary() async {
    final result = await showDiaryComposer(
      context,
      heading: 'New diary',
      actionLabel: 'Create diary',
      offerPin: true,
    );
    if (result == null || !mounted) return;

    if (result.lockWithPin && _lock == null) {
      // Only the first lock asks for a PIN. Every diary locked after this one
      // reuses it, so the student is never asked to invent or remember another.
      if (!await _chooseFirstPin(result.title) || !mounted) return;
    }

    final diary = Diary.create(
      title: result.title,
      coverIndex: result.coverIndex,
      isLocked: result.lockWithPin,
    );
    if (!await _saveDiary(diary) || !mounted) return;

    // Straight in — they have just proved the PIN by typing it twice.
    _pushDiary(diary);
  }

  Future<void> _openDiary(Diary diary) async {
    // A diary marked locked with no PIN on record opens rather than sealing the
    // student out of writing nothing can recover — the same trade-off the lock
    // storage makes when its record is damaged.
    final lock = _lock;
    if (diary.isLocked && lock != null) {
      final outcome = await showDiaryUnlock(context, diary: diary, lock: lock);
      if (!mounted) return;
      switch (outcome) {
        case DiaryUnlockOutcome.cancelled:
          return;
        case DiaryUnlockOutcome.deleteRequested:
          await _deleteDiary(diary);
          return;
        case DiaryUnlockOutcome.unlocked:
          break;
      }
    }
    _pushDiary(diary);
  }

  void _pushDiary(Diary diary) {
    Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => DiaryScreen(
          diary: diary,
          hasPin: _lock != null,
          onSave: _saveDiary,
          onDelete: _deleteDiary,
          onSetPin: _saveLock,
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

    return RefreshIndicator(
      // The only manual way to force a sync, and the one place a failure is
      // worth a message: here the student actually asked for it.
      onRefresh: () => _syncNow(reportFailure: true),
      child: ListView(
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
      ),
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
                  Row(
                    children: [
                      Flexible(
                        child: Text(
                          diary.title,
                          maxLines: 1,
                          overflow: TextOverflow.ellipsis,
                          style: Theme.of(context).textTheme.titleSmall
                              ?.copyWith(fontWeight: FontWeight.w800),
                        ),
                      ),
                      if (diary.isLocked) ...[
                        const SizedBox(width: AppSpacing.xs),
                        const Icon(
                          Icons.lock_rounded,
                          size: 14,
                          color: AppColors.primary,
                          semanticLabel: 'Locked with a PIN',
                        ),
                      ],
                    ],
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
