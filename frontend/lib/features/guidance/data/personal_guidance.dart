/// A single piece of Home Page encouragement. All content is authored by the
/// SheZen admin and served by the backend — nothing here is bundled in the app.
enum GuidanceType { affirmation, quote, guidance }

/// A wellbeing activity an admin has linked to a piece of guidance, so the
/// student can move straight from advice to something they can try.
class RelatedActivity {
  const RelatedActivity({
    required this.title,
    required this.contentType,
    required this.instructions,
  });

  final String title;
  final String contentType;
  final String instructions;

  factory RelatedActivity.fromJson(Map<String, dynamic> json) =>
      RelatedActivity(
        title: (json['title'] as String? ?? '').trim(),
        contentType: (json['content_type'] as String? ?? '').trim(),
        instructions: (json['instructions'] as String? ?? '').trim(),
      );
}

class PersonalGuidance {
  const PersonalGuidance({
    required this.id,
    required this.type,
    required this.content,
    this.author,
    this.category,
    this.isFavourite = false,
    this.title,
    this.summary,
    this.whenItHelps,
    this.steps = const [],
    this.durationMinutes,
    this.relatedActivity,
  });

  final int id;
  final GuidanceType type;
  final String content;

  /// Attribution to display, already resolved by the backend. Null means show
  /// none (never render "Unknown").
  final String? author;
  final String? category;
  final bool isFavourite;

  // Optional coping-strategy detail. Guidance written before these fields
  // existed simply leaves them null, and renders as it always has.
  final String? title;
  final String? summary;
  final String? whenItHelps;
  final List<String> steps;
  final int? durationMinutes;
  final RelatedActivity? relatedActivity;

  /// True when this item has enough detail to present as a practical strategy
  /// rather than a short tip.
  bool get isStrategy => steps.isNotEmpty;

  /// The line to lead with: the admin's short summary when there is one,
  /// otherwise the full content.
  String get lead =>
      (summary != null && summary!.trim().isNotEmpty) ? summary!.trim() : content;

  factory PersonalGuidance.fromJson(Map<String, dynamic> json) {
    final rawAuthor = (json['author'] as String?)?.trim();
    final rawTitle = (json['title'] as String?)?.trim();
    final rawSummary = (json['summary'] as String?)?.trim();
    final rawWhen = (json['when_it_helps'] as String?)?.trim();
    final related = json['related_activity'];

    return PersonalGuidance(
      id: (json['id'] as num?)?.toInt() ?? 0,
      type: _typeFrom(json['type'] as String?),
      content: (json['content'] as String? ?? '').trim(),
      author: (rawAuthor == null || rawAuthor.isEmpty) ? null : rawAuthor,
      category: json['category'] as String?,
      isFavourite: json['is_favourite'] as bool? ?? false,
      title: (rawTitle == null || rawTitle.isEmpty) ? null : rawTitle,
      summary: (rawSummary == null || rawSummary.isEmpty) ? null : rawSummary,
      whenItHelps: (rawWhen == null || rawWhen.isEmpty) ? null : rawWhen,
      steps: (json['steps'] as List<dynamic>? ?? const [])
          .map((step) => step.toString().trim())
          .where((step) => step.isNotEmpty)
          .toList(),
      durationMinutes: (json['duration_minutes'] as num?)?.toInt(),
      relatedActivity: related is Map<String, dynamic>
          ? RelatedActivity.fromJson(related)
          : null,
    );
  }

  PersonalGuidance copyWith({bool? isFavourite}) => PersonalGuidance(
    id: id,
    type: type,
    content: content,
    author: author,
    category: category,
    isFavourite: isFavourite ?? this.isFavourite,
    title: title,
    summary: summary,
    whenItHelps: whenItHelps,
    steps: steps,
    durationMinutes: durationMinutes,
    relatedActivity: relatedActivity,
  );

  static GuidanceType _typeFrom(String? raw) => switch (raw) {
    'affirmation' => GuidanceType.affirmation,
    'quote' => GuidanceType.quote,
    _ => GuidanceType.guidance,
  };
}

/// The student's personalised toolkit: guidance matched to their latest
/// check-in, with a supportive lead-in written by the admin.
class GuidanceToolkit {
  const GuidanceToolkit({
    required this.hasCheckIn,
    required this.headline,
    required this.subline,
    required this.items,
    this.bandMessage,
  });

  final bool hasCheckIn;
  final String headline;
  final String subline;
  final String? bandMessage;
  final List<PersonalGuidance> items;

  /// Items detailed enough to follow as a strategy.
  List<PersonalGuidance> get strategies =>
      items.where((item) => item.isStrategy).toList();

  /// Shorter items, shown as quick tips. Affirmations are excluded: they get
  /// their own section rather than being mixed into tips and advice.
  List<PersonalGuidance> get quickTips => items
      .where((item) => !item.isStrategy && item.type != GuidanceType.affirmation)
      .toList();

  /// Affirmations to read back, shown as "Daily affirmations".
  List<PersonalGuidance> get affirmations =>
      items.where((item) => item.type == GuidanceType.affirmation).toList();

  factory GuidanceToolkit.fromJson(Map<String, dynamic> json) => GuidanceToolkit(
    hasCheckIn: json['has_check_in'] as bool? ?? false,
    headline: (json['headline'] as String? ?? 'A place to start').trim(),
    subline: (json['subline'] as String? ?? '').trim(),
    bandMessage: (json['band_message'] as String?)?.trim(),
    items: (json['data'] as List<dynamic>? ?? const [])
        .whereType<Map<String, dynamic>>()
        .map(PersonalGuidance.fromJson)
        .toList(),
  );
}
