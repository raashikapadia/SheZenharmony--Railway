import 'dart:math';

/// Identifier for a locally created diary or page.
///
/// Minted on the device and kept for the life of the record: the server stores
/// it as `client_id` and matches on it, so a reinstalled app re-attaches to the
/// student's existing rows rather than duplicating them. A timestamp plus a
/// random suffix is unique enough for one person's diaries across their own
/// devices, and avoids pulling in a uuid dependency for it.
String newLocalId() {
  final random = Random();
  final suffix = random.nextInt(1 << 32).toRadixString(36);
  return '${DateTime.now().microsecondsSinceEpoch.toRadixString(36)}-$suffix';
}

/// One written entry inside a [Diary].
class DiaryPage {
  const DiaryPage({
    required this.id,
    required this.title,
    required this.body,
    required this.createdAt,
    required this.updatedAt,
  });

  DiaryPage.blank()
    : id = newLocalId(),
      title = '',
      body = '',
      createdAt = DateTime.now(),
      updatedAt = DateTime.now();

  final String id;

  /// Optional — an untitled page falls back to its date in the list.
  final String title;
  final String body;
  final DateTime createdAt;
  final DateTime updatedAt;

  bool get isEmpty => title.trim().isEmpty && body.trim().isEmpty;

  DiaryPage copyWith({String? title, String? body, DateTime? updatedAt}) =>
      DiaryPage(
        id: id,
        title: title ?? this.title,
        body: body ?? this.body,
        createdAt: createdAt,
        updatedAt: updatedAt ?? this.updatedAt,
      );

  Map<String, dynamic> toJson() => {
    'id': id,
    'title': title,
    'body': body,
    'created_at': createdAt.toIso8601String(),
    'updated_at': updatedAt.toIso8601String(),
  };

  /// Tolerant of a partly written record: a diary is irreplaceable, so a
  /// missing or malformed field loses that field rather than the whole page.
  factory DiaryPage.fromJson(Map<String, dynamic> json) {
    final created = DateTime.tryParse(json['created_at'] as String? ?? '');
    return DiaryPage(
      id: json['id'] as String? ?? newLocalId(),
      title: json['title'] as String? ?? '',
      body: json['body'] as String? ?? '',
      createdAt: created ?? DateTime.now(),
      updatedAt:
          DateTime.tryParse(json['updated_at'] as String? ?? '') ??
          created ??
          DateTime.now(),
    );
  }
}

/// A private notebook of [DiaryPage]s.
class Diary {
  const Diary({
    required this.id,
    required this.title,
    required this.coverIndex,
    required this.createdAt,
    required this.updatedAt,
    required this.pages,
    this.isLocked = false,
  });

  Diary.create({
    required this.title,
    this.coverIndex = 0,
    this.isLocked = false,
  }) : id = newLocalId(),
       createdAt = DateTime.now(),
       updatedAt = DateTime.now(),
       pages = const [];

  final String id;
  final String title;

  /// Index into the cover palette on the diary screens; stored rather than
  /// derived so a student's chosen cover survives a palette change.
  final int coverIndex;
  final DateTime createdAt;
  final DateTime updatedAt;
  final List<DiaryPage> pages;

  /// Whether this diary asks for the student's PIN before it opens.
  ///
  /// Which PIN is not recorded here. A student chooses one PIN, kept in
  /// [DiaryLockStore], and every diary they lock opens with that same one.
  final bool isLocked;

  /// Leaves [isLocked] alone. Use [locked] and [unlocked] to change it, so
  /// "nothing said about the lock" can never be mistaken for "unlock it".
  Diary copyWith({
    String? title,
    int? coverIndex,
    List<DiaryPage>? pages,
    DateTime? updatedAt,
  }) => Diary(
    id: id,
    title: title ?? this.title,
    coverIndex: coverIndex ?? this.coverIndex,
    createdAt: createdAt,
    updatedAt: updatedAt ?? this.updatedAt,
    pages: pages ?? this.pages,
    isLocked: isLocked,
  );

  /// Makes this diary one of the ones that asks for the student's PIN.
  Diary locked() => _withLockFlag(true);

  /// Opens straight from the library again. The student's PIN is untouched —
  /// their other locked diaries still use it.
  Diary unlocked() => _withLockFlag(false);

  Diary _withLockFlag(bool value) => Diary(
    id: id,
    title: title,
    coverIndex: coverIndex,
    createdAt: createdAt,
    updatedAt: DateTime.now(),
    pages: pages,
    isLocked: value,
  );

  /// Replaces [page] if the diary already holds its id, otherwise adds it.
  Diary withPage(DiaryPage page) {
    final next = [...pages];
    final index = next.indexWhere((existing) => existing.id == page.id);
    if (index == -1) {
      next.insert(0, page);
    } else {
      next[index] = page;
    }
    return copyWith(pages: next, updatedAt: DateTime.now());
  }

  Diary withoutPage(String pageId) => copyWith(
    pages: pages.where((page) => page.id != pageId).toList(),
    updatedAt: DateTime.now(),
  );

  Map<String, dynamic> toJson() => {
    'id': id,
    'title': title,
    'cover_index': coverIndex,
    'created_at': createdAt.toIso8601String(),
    'updated_at': updatedAt.toIso8601String(),
    'pages': pages.map((page) => page.toJson()).toList(),
    'is_locked': isLocked,
  };

  factory Diary.fromJson(Map<String, dynamic> json) {
    final created = DateTime.tryParse(json['created_at'] as String? ?? '');
    return Diary(
      id: json['id'] as String? ?? newLocalId(),
      title: json['title'] as String? ?? 'My diary',
      coverIndex: (json['cover_index'] as num?)?.toInt() ?? 0,
      createdAt: created ?? DateTime.now(),
      updatedAt:
          DateTime.tryParse(json['updated_at'] as String? ?? '') ??
          created ??
          DateTime.now(),
      pages: (json['pages'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(DiaryPage.fromJson)
          .toList(),
      isLocked: json['is_locked'] as bool? ?? false,
    );
  }
}
