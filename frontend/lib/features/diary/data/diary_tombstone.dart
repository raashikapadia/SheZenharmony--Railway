/// A record that something was deleted, kept until the server has been told.
///
/// Without these, deleting is invisible to the sync: the device would simply
/// send a diary it no longer has, the server would still hold its copy, and the
/// next pull would hand the deleted writing straight back. A tombstone is how a
/// deletion travels between a student's devices.
class DiaryTombstone {
  const DiaryTombstone({
    required this.diaryId,
    required this.pageId,
    required this.deletedAt,
  });

  /// The diary that was deleted, or that held the deleted page.
  final String diaryId;

  /// Null when the whole diary went, rather than one page inside it.
  final String? pageId;

  final DateTime deletedAt;

  bool get isWholeDiary => pageId == null;

  Map<String, dynamic> toJson() => {
    'diary_id': diaryId,
    'page_id': pageId,
    'deleted_at': deletedAt.toIso8601String(),
  };

  static DiaryTombstone? fromJson(Object? json) {
    if (json is! Map<String, dynamic>) return null;
    final diaryId = json['diary_id'] as String?;
    if (diaryId == null || diaryId.isEmpty) return null;
    final deletedAt = DateTime.tryParse(json['deleted_at'] as String? ?? '');
    if (deletedAt == null) return null;
    return DiaryTombstone(
      diaryId: diaryId,
      pageId: json['page_id'] as String?,
      deletedAt: deletedAt,
    );
  }
}
