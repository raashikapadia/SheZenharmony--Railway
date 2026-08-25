class AuthSession {
  const AuthSession({
    required this.token,
    required this.userId,
    required this.name,
    required this.email,
    required this.role,
    required this.hasCompletedRequiredAssessment,
  });

  final String token;
  final int userId;
  final String name;
  final String email;
  final String role;

  /// Whether the user has completed at least one stress assessment — the
  /// backend is the source of truth for this (derived from real
  /// `stress_assessments` rows), never a locally-stored flag.
  final bool hasCompletedRequiredAssessment;

  bool get isAdmin => role == 'admin';

  factory AuthSession.fromJson(Map<String, dynamic> json) {
    final user = json['user'];
    if (json['token'] is! String || user is! Map<String, dynamic>) {
      throw const FormatException('Invalid authentication response.');
    }

    return AuthSession(
      token: json['token'] as String,
      userId: user['id'] as int,
      name: user['name'] as String,
      email: user['email'] as String,
      role: user['role'] as String? ?? 'student',
      hasCompletedRequiredAssessment: user['has_completed_required_assessment'] as bool? ?? false,
    );
  }

  AuthSession copyWith({bool? hasCompletedRequiredAssessment}) {
    return AuthSession(
      token: token,
      userId: userId,
      name: name,
      email: email,
      role: role,
      hasCompletedRequiredAssessment: hasCompletedRequiredAssessment ?? this.hasCompletedRequiredAssessment,
    );
  }
}
