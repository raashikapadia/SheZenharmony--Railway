import 'question_option.dart';

/// The set of question types the SheZen Harmony backend supports today.
/// Mirrors `App\Enums\QuestionType` on the Laravel side — keep in sync.
class QuestionType {
  QuestionType._();

  static const String scale = 'scale';

  static const List<String> values = [scale];

  static String label(String value) {
    switch (value) {
      case scale:
        return 'Scale';
      default:
        return value;
    }
  }
}

class StressQuestion {
  const StressQuestion({
    required this.id,
    required this.questionText,
    this.dimension,
    this.helpText,
    required this.questionType,
    required this.isActive,
    required this.isSensitive,
    required this.position,
    required this.isRequired,
    required this.options,
  });

  final int id;
  final String questionText;
  final String? dimension;
  final String? helpText;
  final String questionType;
  final bool isActive;
  final bool isSensitive;
  final int position;
  final bool isRequired;
  final List<QuestionOption> options;

  factory StressQuestion.fromJson(Map<String, dynamic> json) {
    final rawOptions = json['options'];
    return StressQuestion(
      id: json['id'] as int,
      questionText: json['question_text'] as String? ?? '',
      dimension: json['dimension'] as String?,
      helpText: json['help_text'] as String?,
      questionType: json['question_type'] as String? ?? QuestionType.scale,
      isActive: json['is_active'] as bool? ?? true,
      isSensitive: json['is_sensitive'] as bool? ?? false,
      position: json['position'] as int? ?? 0,
      isRequired: json['is_required'] as bool? ?? true,
      options: rawOptions is List
          ? rawOptions
              .whereType<Map<String, dynamic>>()
              .map(QuestionOption.fromJson)
              .toList()
          : const [],
    );
  }
}
