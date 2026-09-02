import 'assessment_result.dart';

/// One answered question in a completed assessment, from the immutable
/// snapshot the backend stored at submission time.
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
    required this.totalScore,
    required this.scoreOutOf,
    this.bandCode,
    this.bandLabel,
    this.completedAt,
    this.responses = const [],
    this.recommendedInterventions = const [],
  });

  final int id;
  final String? questionnaireTitle;
  final int totalScore;
  final int scoreOutOf;
  final String? bandCode;
  final String? bandLabel;
  final DateTime? completedAt;
  final List<AssessmentResponseLine> responses;
  final List<RecommendedIntervention> recommendedInterventions;

  factory AssessmentDetail.fromJson(Map<String, dynamic> json) {
    final band = json['band'] as Map<String, dynamic>?;
    final completedAtRaw = json['completed_at'];
    final rawResponses = json['responses'];
    final totalScore = json['total_score'] as int? ?? 0;

    return AssessmentDetail(
      id: json['id'] as int? ?? 0,
      questionnaireTitle: json['questionnaire_title'] as String?,
      totalScore: totalScore,
      scoreOutOf: json['score_out_of'] as int? ?? totalScore,
      bandCode: band?['code'] as String?,
      bandLabel: band?['label'] as String?,
      completedAt: completedAtRaw is String
          ? DateTime.tryParse(completedAtRaw)
          : null,
      responses: rawResponses is List
          ? rawResponses
                .whereType<Map<String, dynamic>>()
                .map(AssessmentResponseLine.fromJson)
                .toList()
          : const [],
      recommendedInterventions: RecommendedIntervention.listFrom(
        json['recommended_interventions'],
      ),
    );
  }
}
