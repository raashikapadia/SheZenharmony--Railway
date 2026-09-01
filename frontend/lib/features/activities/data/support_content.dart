class WellbeingActivity {
  const WellbeingActivity({
    required this.title,
    required this.description,
    required this.category,
    required this.sourceUrl,
    required this.sourceType,
    required this.instructions,
    required this.hasVideo,
  });

  final String title;
  final String description;
  final String category;
  final String sourceUrl;
  final String sourceType;
  final String instructions;
  final bool hasVideo;

  factory WellbeingActivity.fromJson(Map<String, dynamic> json) =>
      WellbeingActivity(
        title: json['title'] as String? ?? 'Wellbeing activity',
        description: json['description'] as String? ?? '',
        category: json['category'] as String? ?? 'Wellbeing',
        sourceUrl: json['video_url'] as String? ?? '',
        sourceType: json['video_type'] as String? ?? 'video',
        instructions: '',
        hasVideo: true,
      );

  factory WellbeingActivity.fromInterventionJson(Map<String, dynamic> json) =>
      WellbeingActivity(
        title: json['title'] as String? ?? 'Wellbeing activity',
        description: json['description'] as String? ?? '',
        category: _categoryLabel(json['content_type'] as String?),
        sourceUrl: json['external_url'] as String? ?? '',
        sourceType: 'guided',
        instructions: json['instructions'] as String? ?? '',
        hasVideo: false,
      );

  static String _categoryLabel(String? type) => switch (type) {
    'breathing' => 'Breathing',
    'grounding' => 'Grounding',
    'mindfulness' => 'Mindfulness',
    'relaxation' => 'Relaxation',
    'resource' => 'Resource',
    _ => 'Wellbeing',
  };
}

class PositiveContent {
  const PositiveContent({
    required this.title,
    required this.description,
    required this.contentType,
    required this.instructions,
    required this.externalUrl,
  });

  final String title;
  final String description;
  final String contentType;
  final String instructions;
  final String externalUrl;

  factory PositiveContent.fromJson(Map<String, dynamic> json) =>
      PositiveContent(
        title: json['title'] as String? ?? 'Positive activity',
        description: json['description'] as String? ?? '',
        contentType: json['content_type'] as String? ?? 'activity',
        instructions: json['instructions'] as String? ?? '',
        externalUrl: json['external_url'] as String? ?? '',
      );
}
