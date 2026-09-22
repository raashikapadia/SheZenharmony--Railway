/// A single admin-published support item recommended for the student's
/// computed result level. Sourced from the API — never hardcoded.
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

/// One section's outcome within a result, exactly as the backend computed
/// and stored it — the app never recalculates a percentage.
class SectionScore {
  const SectionScore({
    required this.title,
    required this.percentage,
    this.rawScore,
    this.maxPossibleScore,
    this.weight,
    this.weightedScore,
  });

  final String title;
  final double percentage;
  final double? rawScore;
  final double? maxPossibleScore;
  final double? weight;
  final double? weightedScore;

  factory SectionScore.fromJson(Map<String, dynamic> json) => SectionScore(
    title: json['title'] as String? ?? '',
    percentage: _toDouble(json['percentage']) ?? 0,
    rawScore: _toDouble(json['raw_score']),
    maxPossibleScore: _toDouble(json['max_possible_score']),
    weight: _toDouble(json['weight'] ?? json['category_weight']),
    weightedScore: _toDouble(json['weighted_score']),
  );

  static List<SectionScore> listFrom(dynamic raw) {
    return raw is List
        ? raw
              .whereType<Map<String, dynamic>>()
              .map(SectionScore.fromJson)
              .toList()
        : const [];
  }
}

double? _toDouble(dynamic value) {
  if (value is num) return value.toDouble();
  if (value is String) return double.tryParse(value);
  return null;
}

class AssessmentResult {
  const AssessmentResult({
    required this.assessmentId,
    required this.totalScore,
    required this.scoreOutOf,
    required this.bandCode,
    required this.bandLabel,
    this.bandDescription,
    this.bandMessage,
    this.percentage,
    this.sections = const [],
    this.completedAt,
    this.recommendedInterventions = const [],
  });

  final int assessmentId;
  final int totalScore;

  /// Highest configured level maximum for the questionnaire (e.g. 100).
  /// Falls back to [totalScore] when the API omits it.
  final int scoreOutOf;
  final String bandCode;
  final String bandLabel;

  /// The admin's description of the matched level and the message written
  /// for it, when they configured either.
  final String? bandDescription;
  final String? bandMessage;

  /// 0–100, when the questionnaire has sections.
  final double? percentage;
  final List<SectionScore> sections;
  final DateTime? completedAt;
  final List<RecommendedIntervention> recommendedInterventions;

  factory AssessmentResult.fromJson(Map<String, dynamic> json) {
    final assessment = json['assessment'] as Map<String, dynamic>? ?? const {};
    final result = json['result'] as Map<String, dynamic>? ?? const {};
    final band = result['band'] as Map<String, dynamic>? ?? const {};
    final breakdown = result['breakdown'] as Map<String, dynamic>?;
    final completedAtRaw = assessment['completed_at'];
    final totalScore = result['total_score'] as int? ?? 0;

    return AssessmentResult(
      assessmentId: assessment['id'] as int? ?? 0,
      totalScore: totalScore,
      scoreOutOf: result['score_out_of'] as int? ?? totalScore,
      bandCode: band['code'] as String? ?? '',
      bandLabel: band['label'] as String? ?? '',
      bandDescription: band['description'] as String?,
      bandMessage: band['message'] as String?,
      percentage: _toDouble(result['percentage']),
      sections: SectionScore.listFrom(breakdown?['categories']),
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
    this.questionnaireId,
    this.questionnaireTitle,
    this.questionnaireVersion,
    this.purpose,
    required this.totalScore,
    this.scoreOutOf,
    this.percentage,
    this.bandLabel,
    this.completedAt,
  });

  final int id;
  final int? questionnaireId;
  final String? questionnaireTitle;
  final int? questionnaireVersion;

  /// `registration` for the onboarding baseline, `library` otherwise — so
  /// history can keep the two apart.
  final String? purpose;
  final int totalScore;
  final int? scoreOutOf;
  final double? percentage;
  final String? bandLabel;
  final DateTime? completedAt;

  bool get isRegistration => purpose == 'registration';

  factory AssessmentSummary.fromJson(Map<String, dynamic> json) {
    final band = json['band'] as Map<String, dynamic>?;
    final completedAtRaw = json['completed_at'];

    return AssessmentSummary(
      id: json['id'] as int,
      questionnaireId: json['questionnaire_id'] as int?,
      questionnaireTitle: json['questionnaire_title'] as String?,
      questionnaireVersion: json['questionnaire_version'] as int?,
      purpose: json['purpose'] as String?,
      totalScore: json['total_score'] as int? ?? 0,
      scoreOutOf: json['score_out_of'] as int?,
      percentage: _toDouble(json['percentage']),
      bandLabel: band?['label'] as String?,
      completedAt: completedAtRaw is String
          ? DateTime.tryParse(completedAtRaw)
          : null,
    );
  }
}
