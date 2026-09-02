/// A single admin-published support item recommended for the student's
/// computed stress band. Sourced from the API — never hardcoded.
class RecommendedIntervention {
  const RecommendedIntervention({
    required this.title,
    this.description,
    this.contentType,
    this.instructions,
    this.externalUrl,
  });

  final String title;
  final String? description;
  final String? contentType;
  final String? instructions;
  final String? externalUrl;

  factory RecommendedIntervention.fromJson(Map<String, dynamic> json) {
    return RecommendedIntervention(
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      contentType: json['content_type'] as String?,
      instructions: json['instructions'] as String?,
      externalUrl: json['external_url'] as String?,
    );
  }

  static List<RecommendedIntervention> listFrom(dynamic raw) {
    return raw is List
        ? raw
              .whereType<Map<String, dynamic>>()
              .map(RecommendedIntervention.fromJson)
              .toList()
        : const [];
  }
}

class AssessmentResult {
  const AssessmentResult({
    required this.assessmentId,
    required this.totalScore,
    required this.scoreOutOf,
    required this.bandCode,
    required this.bandLabel,
    this.completedAt,
    this.recommendedInterventions = const [],
  });

  final int assessmentId;
  final int totalScore;

  /// Highest configured band maximum for the questionnaire (e.g. 100).
  /// Falls back to [totalScore] when the API omits it.
  final int scoreOutOf;
  final String bandCode;
  final String bandLabel;
  final DateTime? completedAt;
  final List<RecommendedIntervention> recommendedInterventions;

  factory AssessmentResult.fromJson(Map<String, dynamic> json) {
    final assessment = json['assessment'] as Map<String, dynamic>? ?? const {};
    final result = json['result'] as Map<String, dynamic>? ?? const {};
    final band = result['band'] as Map<String, dynamic>? ?? const {};
    final completedAtRaw = assessment['completed_at'];
    final totalScore = result['total_score'] as int? ?? 0;

    return AssessmentResult(
      assessmentId: assessment['id'] as int? ?? 0,
      totalScore: totalScore,
      scoreOutOf: result['score_out_of'] as int? ?? totalScore,
      bandCode: band['code'] as String? ?? '',
      bandLabel: band['label'] as String? ?? '',
      completedAt: completedAtRaw is String
          ? DateTime.tryParse(completedAtRaw)
          : null,
      recommendedInterventions: RecommendedIntervention.listFrom(
        json['recommended_interventions'],
      ),
    );
  }
}

class AssessmentSummary {
  const AssessmentSummary({
    required this.id,
    this.questionnaireTitle,
    required this.totalScore,
    this.bandLabel,
    this.completedAt,
  });

  final int id;
  final String? questionnaireTitle;
  final int totalScore;
  final String? bandLabel;
  final DateTime? completedAt;

  factory AssessmentSummary.fromJson(Map<String, dynamic> json) {
    final band = json['band'] as Map<String, dynamic>?;
    final completedAtRaw = json['completed_at'];

    return AssessmentSummary(
      id: json['id'] as int,
      questionnaireTitle: json['questionnaire_title'] as String?,
      totalScore: json['total_score'] as int? ?? 0,
      bandLabel: band?['label'] as String?,
      completedAt: completedAtRaw is String
          ? DateTime.tryParse(completedAtRaw)
          : null,
    );
  }
}
