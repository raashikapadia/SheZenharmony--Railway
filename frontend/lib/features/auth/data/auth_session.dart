class AuthSession {
  const AuthSession({
    required this.token,
    required this.userId,
    required this.name,
    required this.email,
  });

  final String token;
  final int userId;
  final String name;
  final String email;

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
    );
  }
}
