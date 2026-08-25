class QuestionOption {
  const QuestionOption({
    this.id,
    required this.label,
    required this.value,
    this.score,
    this.position = 0,
    this.isActive = true,
  });

  final int? id;
  final String label;
  final String value;
  final int? score;
  final int position;
  final bool isActive;

  factory QuestionOption.fromJson(Map<String, dynamic> json) {
    return QuestionOption(
      id: json['id'] as int?,
      label: json['label'] as String? ?? '',
      value: json['value'] as String? ?? '',
      score: json['score'] as int?,
      position: json['position'] as int? ?? 0,
      isActive: json['is_active'] as bool? ?? true,
    );
  }

  Map<String, dynamic> toJson() {
    return {
      if (id != null) 'id': id,
      'label': label,
      'value': value,
      'score': score,
    };
  }

  QuestionOption copyWith({
    int? id,
    String? label,
    String? value,
    int? score,
    bool clearScore = false,
    int? position,
    bool? isActive,
  }) {
    return QuestionOption(
      id: id ?? this.id,
      label: label ?? this.label,
      value: value ?? this.value,
      score: clearScore ? null : (score ?? this.score),
      position: position ?? this.position,
      isActive: isActive ?? this.isActive,
    );
  }
}
