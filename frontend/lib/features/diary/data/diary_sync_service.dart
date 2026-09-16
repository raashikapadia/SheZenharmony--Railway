import '../../../core/network/api_service.dart';
import 'diary.dart';
import 'diary_lock.dart';
import 'diary_storage.dart';
import 'diary_tombstone.dart';

/// Keeps this device's diary and the stored one in step.
///
/// Every sync sends the device's **whole** diary rather than a queue of pending
/// changes. That is what makes the feature survive a connection that drops
/// mid-write, a force-quit, or a week offline: there is no queue to lose, and
/// whatever is on the device is simply offered again next time.
///
/// The server merges newest-wins per record on the client's own timestamps and
/// hands back the merged result, which then replaces what is on the device.
class DiarySyncService {
  DiarySyncService({required this.storage, ApiService? apiService})
    : _api = apiService ?? ApiService();

  /// The device copy this service reconciles. Public because the screen that
  /// owns both hands the same instance to each.
  final DiaryStorage storage;
  final ApiService _api;

  /// Pushes local work, applies what comes back, and returns the merged diary.
  ///
  /// Throws [ApiException] when the device cannot reach the server; callers are
  /// expected to carry on with the local copy rather than treat that as fatal.
  Future<DiarySyncResult> sync(String token) async {
    final local = await storage.readAll();
    final tombstones = await storage.readTombstones();
    final localLock = await storage.readLock();

    final response = await _api.syncDiaries(
      token,
      _payload(local, tombstones),
      // Absent when this device has never seen a PIN. That is not the same as
      // saying there is no PIN, and the server treats it accordingly.
      lock: localLock?.toJson(),
    );

    final diaries =
        response.diaries.map(_diaryFromServer).whereType<Diary>().toList()
          ..sort((a, b) => b.updatedAt.compareTo(a.updatedAt));

    final lock = DiaryLockState.fromJson(response.lock);

    await storage.writeAll(diaries);
    await storage.writeLock(
      lock ??
          // The server holds no PIN. Recorded as an explicit clearing rather
          // than left blank, so this device stops offering one it has caught up
          // past.
          DiaryLockState(lock: null, updatedAt: DateTime.now()),
    );
    // Only now: the deletions have been recorded server-side, so replaying them
    // on the next sync would achieve nothing. Clearing earlier would risk
    // losing a deletion that never actually landed.
    await storage.writeTombstones(const []);

    return DiarySyncResult(diaries: diaries, lock: lock?.lock);
  }

  /// The wire shape: every live diary, plus one entry per deletion still owed
  /// to the server.
  List<Map<String, dynamic>> _payload(
    List<Diary> diaries,
    List<DiaryTombstone> tombstones,
  ) {
    final deletedDiaryIds = tombstones
        .where((entry) => entry.isWholeDiary)
        .map((entry) => entry.diaryId)
        .toSet();

    final pageTombstonesByDiary = <String, List<DiaryTombstone>>{};
    for (final entry in tombstones.where((entry) => !entry.isWholeDiary)) {
      pageTombstonesByDiary.putIfAbsent(entry.diaryId, () => []).add(entry);
    }

    final payload = <Map<String, dynamic>>[];

    for (final diary in diaries) {
      if (deletedDiaryIds.contains(diary.id)) continue;
      payload.add(
        _diaryToServer(diary, pageTombstonesByDiary[diary.id] ?? const []),
      );
    }

    // A deleted diary is still sent, marked as gone, so the server can record
    // the deletion instead of handing the diary back on the next pull.
    for (final entry in tombstones.where((entry) => entry.isWholeDiary)) {
      payload.add({
        'client_id': entry.diaryId,
        'title': '',
        'cover_index': 0,
        'deleted': true,
        'created_at': entry.deletedAt.toUtc().toIso8601String(),
        'updated_at': entry.deletedAt.toUtc().toIso8601String(),
        'pages': const <Map<String, dynamic>>[],
      });
    }

    return payload;
  }

  Map<String, dynamic> _diaryToServer(
    Diary diary,
    List<DiaryTombstone> deletedPages,
  ) => {
    'client_id': diary.id,
    'title': diary.title,
    'cover_index': diary.coverIndex,
    'is_locked': diary.isLocked,
    'created_at': diary.createdAt.toUtc().toIso8601String(),
    'updated_at': diary.updatedAt.toUtc().toIso8601String(),
    'pages': [
      for (final page in diary.pages)
        {
          'client_id': page.id,
          'title': page.title,
          'body': page.body,
          'created_at': page.createdAt.toUtc().toIso8601String(),
          'updated_at': page.updatedAt.toUtc().toIso8601String(),
        },
      for (final entry in deletedPages)
        {
          'client_id': entry.pageId,
          'title': '',
          'body': '',
          'deleted': true,
          'created_at': entry.deletedAt.toUtc().toIso8601String(),
          'updated_at': entry.deletedAt.toUtc().toIso8601String(),
        },
    ],
  };

  /// Rebuilds a [Diary] from the server's shape.
  ///
  /// As tolerant as [Diary.fromJson] is of a damaged local record, and for the
  /// same reason: one unreadable diary in the response must not cost the
  /// student the rest of them.
  Diary? _diaryFromServer(Map<String, dynamic> json) {
    final id = json['client_id'] as String?;
    if (id == null || id.isEmpty) return null;

    final created = DateTime.tryParse(json['created_at'] as String? ?? '');
    final updated = DateTime.tryParse(json['updated_at'] as String? ?? '');

    return Diary(
      id: id,
      title: json['title'] as String? ?? 'My diary',
      coverIndex: (json['cover_index'] as num?)?.toInt() ?? 0,
      createdAt: created ?? DateTime.now(),
      updatedAt: updated ?? created ?? DateTime.now(),
      isLocked: json['is_locked'] as bool? ?? false,
      pages: (json['pages'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(_pageFromServer)
          .whereType<DiaryPage>()
          .toList(),
    );
  }

  DiaryPage? _pageFromServer(Map<String, dynamic> json) {
    final id = json['client_id'] as String?;
    if (id == null || id.isEmpty) return null;

    final created = DateTime.tryParse(json['created_at'] as String? ?? '');
    final updated = DateTime.tryParse(json['updated_at'] as String? ?? '');

    return DiaryPage(
      id: id,
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      createdAt: created ?? DateTime.now(),
      updatedAt: updated ?? created ?? DateTime.now(),
    );
  }
}

/// What one sync produced: the merged diaries, and the student's PIN as the
/// server now holds it.
class DiarySyncResult {
  const DiarySyncResult({required this.diaries, required this.lock});

  final List<Diary> diaries;

  /// Null when the student has no PIN — either never set, or removed.
  final DiaryLock? lock;
}
