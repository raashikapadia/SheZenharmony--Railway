class AssessmentOption {
  const AssessmentOption({
    required this.id,
    required this.label,
    required this.value,
  });

  final int id;
  final String label;
  final String value;

  factory AssessmentOption.fromJson(Map<String, dynamic> json) {
    return AssessmentOption(
      id: json['id'] as int,
      label: json['label'] as String? ?? '',
      value: json['value'] as String? ?? '',
    );
  }
}

class AssessmentQuestion {
  const AssessmentQuestion({
    required this.id,
    required this.text,
    required this.required,
    required this.position,
    required this.options,
  });

  final int id;
  final String text;
  final bool required;
  final int position;
  final List<AssessmentOption> options;

  factory AssessmentQuestion.fromJson(Map<String, dynamic> json) {
    final rawOptions = json['options'];
    return AssessmentQuestion(
      id: json['id'] as int,
      text: json['text'] as String? ?? '',
      required: json['required'] as bool? ?? true,
      position: json['position'] as int? ?? 0,
      options: rawOptions is List
          ? rawOptions
                .whereType<Map<String, dynamic>>()
                .map(AssessmentOption.fromJson)
                .toList()
          : const [],
    );
  }
}

/// The same questionnaire configuration is used for both the mandatory
/// post-registration assessment and the optional in-app check-in — this
/// model is the single shape both flows render, sourced from a single API
/// endpoint (`GET /v1/questionnaires/active`).
class AssessmentQuestionnaire {
  const AssessmentQuestionnaire({
    required this.id,
    required this.title,
    this.description,
    required this.questions,
  });

  final int id;
  final String title;
  final String? description;
  final List<AssessmentQuestion> questions;

  factory AssessmentQuestionnaire.fromJson(Map<String, dynamic> json) {
    final rawQuestions = json['questions'];
    final questions = rawQuestions is List
        ? rawQuestions
              .whereType<Map<String, dynamic>>()
              .map(AssessmentQuestion.fromJson)
              .toList()
        : <AssessmentQuestion>[];
    questions.sort((a, b) => a.position.compareTo(b.position));

    return AssessmentQuestionnaire(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      questions: questions,
    );
  }
}
