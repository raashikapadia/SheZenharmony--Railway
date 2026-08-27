import 'score_band.dart';
import 'stress_question.dart';

class Questionnaire {
  const Questionnaire({
    required this.id,
    required this.title,
    this.description,
    this.period,
    required this.version,
    required this.status,
    required this.isActive,
    required this.questionCount,
    required this.questions,
    required this.scoreBands,
    this.createdAt,
    this.updatedAt,
    this.publishedAt,
  });

  final int id;
  final String title;
  final String? description;
  final String? period;
  final int version;
  final String status;
  final bool isActive;
  final int questionCount;
  final List<StressQuestion> questions;
  final List<ScoreBand> scoreBands;
  final DateTime? createdAt;
  final DateTime? updatedAt;
  final DateTime? publishedAt;

  factory Questionnaire.fromJson(Map<String, dynamic> json) {
    final rawQuestions = json['questions'];
    final rawBands = json['score_bands'];
    return Questionnaire(
      id: json['id'] as int,
      title: json['title'] as String? ?? '',
      description: json['description'] as String?,
      period: json['period'] as String?,
      version: json['version'] as int? ?? 1,
      status: json['status'] as String? ?? 'draft',
      isActive: json['is_active'] as bool? ?? false,
      questionCount: json['question_count'] as int? ?? 0,
      questions: rawQuestions is List
          ? rawQuestions
                .whereType<Map<String, dynamic>>()
                .map(StressQuestion.fromJson)
                .toList()
          : const [],
      scoreBands: rawBands is List
          ? rawBands
                .whereType<Map<String, dynamic>>()
                .map(ScoreBand.fromJson)
                .toList()
          : const [],
      createdAt: _parseDate(json['created_at']),
      updatedAt: _parseDate(json['updated_at']),
      publishedAt: _parseDate(json['published_at']),
    );
  }

  static DateTime? _parseDate(Object? value) {
    if (value is String && value.isNotEmpty) {
      return DateTime.tryParse(value);
    }
    return null;
  }
}
