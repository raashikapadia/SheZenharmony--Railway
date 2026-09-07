/// Titles withheld from the student-facing activity lists.
const hiddenWellbeingActivityTitles = <String>{'box breathing'};

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

  factory WellbeingActivity.fromJson(Map<String, dynamic> json) {
    final sourceUrl = json['video_url'] as String? ?? '';
    final declaredType = (json['video_type'] as String? ?? 'video')
        .trim()
        .toLowerCase();

    return WellbeingActivity(
      title: json['title'] as String? ?? 'Wellbeing activity',
      description: json['description'] as String? ?? '',
      category: json['category'] as String? ?? 'Wellbeing',
      sourceUrl: sourceUrl,
      sourceType: _videoTypeFromUrl(sourceUrl) ?? declaredType,
      instructions: '',
      hasVideo: sourceUrl.trim().isNotEmpty,
    );
  }

  factory WellbeingActivity.fromInterventionJson(Map<String, dynamic> json) {
    final sourceUrl = json['external_url'] as String? ?? '';
    final videoType = _videoTypeFromUrl(sourceUrl);

    return WellbeingActivity(
      title: json['title'] as String? ?? 'Wellbeing activity',
      description: json['description'] as String? ?? '',
      category: _categoryLabel(json['content_type'] as String?),
      sourceUrl: sourceUrl,
      sourceType: videoType ?? 'guided',
      instructions: json['instructions'] as String? ?? '',
      hasVideo: videoType != null,
    );
  }

  /// Admins often paste links without a scheme ("youtu.be/..."), which parse
  /// as relative URIs that neither the id parser nor an external app handles.
  String get normalisedSourceUrl {
    final url = sourceUrl.trim();
    if (url.isEmpty) return url;
    if (url.startsWith(RegExp(r'[a-zA-Z][a-zA-Z0-9+.-]*:'))) return url;
    return 'https://$url';
  }

  static String? _videoTypeFromUrl(String sourceUrl) {
    var url = sourceUrl.trim();
    if (url.isEmpty) return null;
    if (!url.startsWith(RegExp(r'[a-zA-Z][a-zA-Z0-9+.-]*:'))) {
      url = 'https://$url';
    }

    final host = Uri.tryParse(url)?.host.toLowerCase() ?? '';
    if (host == 'youtu.be' ||
        host == 'youtube.com' ||
        host.endsWith('.youtube.com') ||
        host == 'youtube-nocookie.com' ||
        host.endsWith('.youtube-nocookie.com')) {
      return 'youtube';
    }
    if (host == 'tiktok.com' || host.endsWith('.tiktok.com')) {
      return 'tiktok';
    }
    return null;
  }

  static String _categoryLabel(String? type) => switch (type) {
    'journaling' => 'Journaling',
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
