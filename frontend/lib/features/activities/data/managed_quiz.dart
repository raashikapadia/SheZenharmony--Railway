class ManagedQuiz {
  const ManagedQuiz({
    required this.id,
    required this.name,
    required this.category,
    required this.description,
    required this.questions,
  });

  final int id;
  final String name;
  final String category;
  final String description;
  final List<ManagedQuizQuestion> questions;

  factory ManagedQuiz.fromJson(Map<String, dynamic> json) {
    return ManagedQuiz(
      id: json['id'] as int,
      name: json['name'] as String? ?? 'Quiz',
      category: json['category'] as String? ?? '',
      description: json['description'] as String? ?? '',
      questions: (json['questions'] as List<dynamic>? ?? const [])
          .whereType<Map<String, dynamic>>()
          .map(ManagedQuizQuestion.fromJson)
          .toList(),
    );
  }
}

class ManagedQuizQuestion {
  const ManagedQuizQuestion({
    required this.id,
    required this.questionText,
    required this.options,
    required this.explanation,
  });

  final int id;
  final String questionText;
  final Map<String, String> options;
  final String explanation;

  factory ManagedQuizQuestion.fromJson(Map<String, dynamic> json) {
    final options = (json['options'] as Map<String, dynamic>? ?? const {}).map(
      (key, value) => MapEntry(key, value.toString()),
    );
    return ManagedQuizQuestion(
      id: json['id'] as int,
      questionText: json['question_text'] as String? ?? '',
      options: options,
      explanation: json['explanation'] as String? ?? '',
    );
  }
}
