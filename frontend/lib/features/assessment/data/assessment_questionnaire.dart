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

/// How a question is *presented*. Mirrors the backend's `QuestionType`
/// enum; anything unrecognised falls back to [scale] so a new server-side
/// type still renders as a plain option list.
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

/// How a question is *answered* — one option, or several up to a limit.
/// Separate from the type on purpose: the admin configures the two
/// independently, and the backend scores from this, never from the type.
enum AssessmentAnswerMode {
  single('single'),
  multiple('multiple');

  const AssessmentAnswerMode(this.wire);

  final String wire;

  static AssessmentAnswerMode parse(String? raw) => values.firstWhere(
    (mode) => mode.wire == raw,
    orElse: () => AssessmentAnswerMode.single,
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
    this.answerMode = AssessmentAnswerMode.single,
    this.maxSelections = 1,
    this.helpText,
    this.sectionId,
  });

  final int id;
  final String text;
  final bool required;
  final int position;
  final List<AssessmentOption> options;
  final AssessmentQuestionType type;
  final AssessmentAnswerMode answerMode;

  /// How many options may be ticked when [answerMode] is
  /// [AssessmentAnswerMode.multiple]; always 1 for a single answer.
  final int maxSelections;
  final String? helpText;

  /// The [AssessmentSection] this question belongs to, if the questionnaire
  /// is organised into sections.
  final int? sectionId;

  bool get allowsMultiple => answerMode == AssessmentAnswerMode.multiple;

  factory AssessmentQuestion.fromJson(Map<String, dynamic> json) {
    final rawOptions = json['options'];
    final mode = AssessmentAnswerMode.parse(json['answer_mode'] as String?);
    return AssessmentQuestion(
      id: json['id'] as int,
      text: json['text'] as String? ?? '',
      required: json['required'] as bool? ?? true,
      position: json['position'] as int? ?? 0,
      type: AssessmentQuestionType.parse(json['type'] as String?),
      answerMode: mode,
      maxSelections: mode == AssessmentAnswerMode.multiple
          ? (json['max_selections'] as int? ?? 0).clamp(1, 1 << 30)
          : 1,
      helpText: json['help_text'] as String?,
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

/// One questionnaire, ready to answer, exactly as the admin configured it
/// (`GET /v1/questionnaires/active`). Title, sections, questions, answer
/// modes and options all come from the response — nothing is assumed here.
class AssessmentQuestionnaire {
  const AssessmentQuestionnaire({
    required this.id,
    required this.title,
    this.description,
    required this.questions,
    this.sections = const [],
    this.version,
    this.estimatedMinutes,
  });

  final int id;
  final String title;
  final String? description;
  final List<AssessmentQuestion> questions;
  final List<AssessmentSection> sections;
  final int? version;
  final int? estimatedMinutes;

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
      version: json['version'] as int?,
      estimatedMinutes: json['estimated_minutes'] as int?,
    );
  }
}
