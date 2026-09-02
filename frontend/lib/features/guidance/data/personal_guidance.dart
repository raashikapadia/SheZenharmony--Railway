/// A single piece of Home Page encouragement. All content is authored by the
/// SheZen admin and served by the backend — nothing here is bundled in the app.
enum GuidanceType { affirmation, quote, guidance }

class PersonalGuidance {
  const PersonalGuidance({
    required this.id,
    required this.type,
    required this.content,
    this.author,
    this.category,
    this.isFavourite = false,
  });

  final int id;
  final GuidanceType type;
  final String content;

  /// Attribution to display, already resolved by the backend. Null means show
  /// none (never render "Unknown").
  final String? author;
  final String? category;
  final bool isFavourite;

  factory PersonalGuidance.fromJson(Map<String, dynamic> json) {
    final rawAuthor = (json['author'] as String?)?.trim();
    return PersonalGuidance(
      id: (json['id'] as num?)?.toInt() ?? 0,
      type: _typeFrom(json['type'] as String?),
      content: (json['content'] as String? ?? '').trim(),
      author: (rawAuthor == null || rawAuthor.isEmpty) ? null : rawAuthor,
      category: json['category'] as String?,
      isFavourite: json['is_favourite'] as bool? ?? false,
    );
  }

  PersonalGuidance copyWith({bool? isFavourite}) => PersonalGuidance(
    id: id,
    type: type,
    content: content,
    author: author,
    category: category,
    isFavourite: isFavourite ?? this.isFavourite,
  );

  static GuidanceType _typeFrom(String? raw) => switch (raw) {
    'affirmation' => GuidanceType.affirmation,
    'quote' => GuidanceType.quote,
    _ => GuidanceType.guidance,
  };
}
