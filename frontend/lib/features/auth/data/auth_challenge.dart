class AuthChallenge {
  const AuthChallenge({
    required this.id,
    required this.purpose,
    required this.maskedEmail,
    required this.expiresInSeconds,
    required this.resendAfterSeconds,
  });

  final String id;
  final String purpose;
  final String maskedEmail;
  final int expiresInSeconds;
  final int resendAfterSeconds;

  bool get isRegistration => purpose == 'registration';

  factory AuthChallenge.fromResponse(Map<String, dynamic> json) {
    final mfa = json['mfa'];
    if (mfa is! Map<String, dynamic> ||
        mfa['challenge_id'] is! String ||
        mfa['purpose'] is! String ||
        mfa['masked_email'] is! String) {
      throw const FormatException('Invalid MFA challenge response.');
    }

    return AuthChallenge(
      id: mfa['challenge_id'] as String,
      purpose: mfa['purpose'] as String,
      maskedEmail: mfa['masked_email'] as String,
      expiresInSeconds: mfa['expires_in_seconds'] as int? ?? 0,
      resendAfterSeconds: mfa['resend_after_seconds'] as int? ?? 0,
    );
  }
}
