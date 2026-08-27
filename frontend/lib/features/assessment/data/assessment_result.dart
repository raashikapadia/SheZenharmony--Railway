class AssessmentResult {
  const AssessmentResult({
    required this.assessmentId,
    required this.totalScore,
    required this.bandCode,
    required this.bandLabel,
    this.completedAt,
  });

  final int assessmentId;
  final int totalScore;
  final String bandCode;
  final String bandLabel;
  final DateTime? completedAt;

  factory AssessmentResult.fromJson(Map<String, dynamic> json) {
    final assessment = json['assessment'] as Map<String, dynamic>? ?? const {};
    final result = json['result'] as Map<String, dynamic>? ?? const {};
    final band = result['band'] as Map<String, dynamic>? ?? const {};
    final completedAtRaw = assessment['completed_at'];

    return AssessmentResult(
      assessmentId: assessment['id'] as int? ?? 0,
      totalScore: result['total_score'] as int? ?? 0,
      bandCode: band['code'] as String? ?? '',
      bandLabel: band['label'] as String? ?? '',
      completedAt: completedAtRaw is String
          ? DateTime.tryParse(completedAtRaw)
          : null,
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
