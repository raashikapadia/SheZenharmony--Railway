class AuthSession {
  const AuthSession({
    required this.token,
    required this.role,
    required this.shezenId,
    required this.hasCompletedRequiredAssessment,
  });

  final String token;
  final String role;
  final String shezenId;

  /// Whether the user has completed at least one stress assessment — the
  /// backend is the source of truth for this (derived from real
  /// `stress_assessments` rows), never a locally-stored flag.
  final bool hasCompletedRequiredAssessment;

  bool get isAdmin => role == 'admin';

  factory AuthSession.fromJson(Map<String, dynamic> json) {
    final user = json['user'];
    if (json['token'] is! String ||
        user is! Map<String, dynamic> ||
        user['shezen_id'] is! String) {
      throw const FormatException('Invalid authentication response.');
    }

    return AuthSession(
      token: json['token'] as String,
      role: user['role'] as String? ?? 'student',
      shezenId: user['shezen_id'] as String,
      hasCompletedRequiredAssessment:
          user['has_completed_required_assessment'] as bool? ?? false,
    );
  }

  AuthSession copyWith({bool? hasCompletedRequiredAssessment}) {
    return AuthSession(
      token: token,
      role: role,
      shezenId: shezenId,
      hasCompletedRequiredAssessment:
          hasCompletedRequiredAssessment ?? this.hasCompletedRequiredAssessment,
    );
  }
}
