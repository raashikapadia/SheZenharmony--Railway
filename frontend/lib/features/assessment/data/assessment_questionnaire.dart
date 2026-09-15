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

/// How a question expects to be answered. Mirrors the backend's
/// `QuestionType` enum; anything unrecognised falls back to [scale] so a new
/// server-side type still renders as a plain option list.
enum AssessmentQuestionType {
  scale('scale'),
  multipleChoice('multiple_choice'),
  yesNo('yes_no');

  const AssessmentQuestionType(this.wire);

  final String wire;

  static AssessmentQuestionType parse(String? raw) => values.firstWhere(
    (type) => type.wire == raw,
    orElse: () => AssessmentQuestionType.scale,
  );
}

class AssessmentQuestion {
  const AssessmentQuestion({
    required this.id,
    required this.text,
    required this.required,
    required this.position,
    required this.options,
    this.type = AssessmentQuestionType.scale,
    this.sectionId,
  });

  final int id;
  final String text;
  final bool required;
  final int position;
  final List<AssessmentOption> options;
  final AssessmentQuestionType type;

  /// The [AssessmentSection] this question belongs to, if the questionnaire
  /// is organised into sections.
  final int? sectionId;

  factory AssessmentQuestion.fromJson(Map<String, dynamic> json) {
    final rawOptions = json['options'];
    return AssessmentQuestion(
      id: json['id'] as int,
      text: json['text'] as String? ?? '',
      required: json['required'] as bool? ?? true,
      position: json['position'] as int? ?? 0,
      type: AssessmentQuestionType.parse(json['type'] as String?),
      sectionId: json['section_id'] as int?,
      options: rawOptions is List
          ? rawOptions
                .whereType<Map<String, dynamic>>()
                .map(AssessmentOption.fromJson)
                .toList()
          : const [],
    );
  }
}

/// A named group of questions — the admin's logical grouping, which the
/// questionnaire UI uses as the natural boundary when it splits questions
/// into pages.
class AssessmentSection {
  const AssessmentSection({
    required this.id,
    required this.title,
    required this.position,
    this.description,
  });

  final int id;
  final String title;
  final int position;
  final String? description;

  factory AssessmentSection.fromJson(Map<String, dynamic> json) =>
      AssessmentSection(
        id: json['id'] as int,
        title: json['title'] as String? ?? '',
        position: json['position'] as int? ?? 0,
        description: json['description'] as String?,
      );
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
    this.sections = const [],
  });

  final int id;
  final String title;
  final String? description;
  final List<AssessmentQuestion> questions;
  final List<AssessmentSection> sections;

  factory AssessmentQuestionnaire.fromJson(Map<String, dynamic> json) {
    final rawQuestions = json['questions'];
    final questions = rawQuestions is List
        ? rawQuestions
              .whereType<Map<String, dynamic>>()
              .map(AssessmentQuestion.fromJson)
              .toList()
        : <AssessmentQuestion>[];
    questions.sort((a, b) => a.position.compareTo(b.position));

    final rawSections = json['sections'];
    final sections = rawSections is List
        ? rawSections
              .whereType<Map<String, dynamic>>()
              .map(AssessmentSection.fromJson)
              .toList()
        : <AssessmentSection>[];
    sections.sort((a, b) => a.position.compareTo(b.position));

    return AssessmentQuestionnaire(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      questions: questions,
      sections: sections,
    );
  }
}
