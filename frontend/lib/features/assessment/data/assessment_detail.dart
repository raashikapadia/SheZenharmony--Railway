import 'assessment_result.dart';

/// One answered question in a completed assessment, from the immutable
/// snapshot the backend stored at submission time. A multi-select answer
/// arrives as its ticked options joined into one string.
class AssessmentResponseLine {
  const AssessmentResponseLine({
    required this.question,
    required this.answer,
    required this.score,
  });

  final String question;
  final String answer;
  final int score;

  factory AssessmentResponseLine.fromJson(Map<String, dynamic> json) {
    return AssessmentResponseLine(
      question: json['question'] as String? ?? '',
      answer: json['answer'] as String? ?? '',
      score: json['score'] as int? ?? 0,
    );
  }
}

class AssessmentDetail {
  const AssessmentDetail({
    required this.id,
    this.questionnaireTitle,
    this.questionnaireVersion,
    this.purpose,
    required this.totalScore,
    required this.scoreOutOf,
    this.percentage,
    this.bandCode,
    this.bandLabel,
    this.bandDescription,
    this.bandMessage,
    this.completedAt,
    this.responses = const [],
    this.sections = const [],
    this.recommendedInterventions = const [],
  });

  final int id;
  final String? questionnaireTitle;

  /// The version this attempt was taken against — the stored result never
  /// moves when a later version changes the configuration.
  final int? questionnaireVersion;
  final String? purpose;
  final int totalScore;
  final int scoreOutOf;
  final double? percentage;
  final String? bandCode;
  final String? bandLabel;
  final String? bandDescription;
  final String? bandMessage;
  final DateTime? completedAt;
  final List<AssessmentResponseLine> responses;

  /// Per-section outcome as stored at submission time.
  final List<SectionScore> sections;
  final List<RecommendedIntervention> recommendedInterventions;

  factory AssessmentDetail.fromJson(Map<String, dynamic> json) {
    final band = json['band'] as Map<String, dynamic>?;
    final completedAtRaw = json['completed_at'];
    final rawResponses = json['responses'];
    final totalScore = json['total_score'] as int? ?? 0;
    final rawPercentage = json['percentage'];

    return AssessmentDetail(
      id: json['id'] as int? ?? 0,
      questionnaireTitle: json['questionnaire_title'] as String?,
      questionnaireVersion: json['questionnaire_version'] as int?,
      purpose: json['purpose'] as String?,
      totalScore: totalScore,
      scoreOutOf: json['score_out_of'] as int? ?? totalScore,
      percentage: rawPercentage is num
          ? rawPercentage.toDouble()
          : rawPercentage is String
          ? double.tryParse(rawPercentage)
          : null,
      bandCode: band?['code'] as String?,
      bandLabel: band?['label'] as String?,
      bandDescription: band?['description'] as String?,
      bandMessage: band?['message'] as String?,
      completedAt: completedAtRaw is String
          ? DateTime.tryParse(completedAtRaw)
          : null,
      responses: rawResponses is List
          ? rawResponses
                .whereType<Map<String, dynamic>>()
                .map(AssessmentResponseLine.fromJson)
                .toList()
          : const [],
      sections: SectionScore.listFrom(json['sections']),
      recommendedInterventions: RecommendedIntervention.listFrom(
        json['recommended_interventions'],
      ),
    );
  }
}
