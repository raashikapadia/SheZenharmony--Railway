class ScoreBand {
  const ScoreBand({
    required this.id,
    required this.code,
    required this.label,
    required this.minScore,
    required this.maxScore,
    required this.position,
    required this.isActive,
  });

  final int id;
  final String code;
  final String label;
  final int minScore;
  final int maxScore;
  final int position;
  final bool isActive;

  factory ScoreBand.fromJson(Map<String, dynamic> json) {
    return ScoreBand(
      id: json['id'] as int,
      code: json['code'] as String? ?? '',
      label: json['label'] as String? ?? '',
      minScore: json['min_score'] as int? ?? 0,
      maxScore: json['max_score'] as int? ?? 0,
      position: json['position'] as int? ?? 0,
      isActive: json['is_active'] as bool? ?? true,
    );
  }
}
