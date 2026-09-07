import 'dart:math';

/// Identifier for a locally created diary or page.
///
/// The diary never leaves the device, so ids only need to be unique within one
/// installation — a timestamp plus a random suffix is enough, and avoids
/// pulling in a uuid dependency for it.
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

/// A private notebook of [DiaryPage]s, stored only on this device.
class Diary {
  const Diary({
    required this.id,
    required this.title,
    required this.coverIndex,
    required this.createdAt,
    required this.updatedAt,
    required this.pages,
  });

  Diary.create({required this.title, this.coverIndex = 0})
    : id = newLocalId(),
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
    );
  }
}
