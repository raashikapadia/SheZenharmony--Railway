import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_theme.dart';
import '../application/auth_provider.dart';
import '../data/auth_challenge.dart';

class OtpVerificationScreen extends StatefulWidget {
  const OtpVerificationScreen({super.key, required this.challenge});

  final AuthChallenge challenge;

  @override
  State<OtpVerificationScreen> createState() => _OtpVerificationScreenState();
}

class _OtpVerificationScreenState extends State<OtpVerificationScreen> {
  final _formKey = GlobalKey<FormState>();
  final _codeController = TextEditingController();
  Timer? _timer;
  late int _expiresIn;
  late int _resendIn;

  @override
  void initState() {
    super.initState();
    _resetTimers(widget.challenge);
    _timer = Timer.periodic(const Duration(seconds: 1), (_) {
      if (!mounted) return;
      setState(() {
        if (_expiresIn > 0) _expiresIn--;
        if (_resendIn > 0) _resendIn--;
      });
    });
  }

  void _resetTimers(AuthChallenge challenge) {
    _expiresIn = challenge.expiresInSeconds;
    _resendIn = challenge.resendAfterSeconds;
  }

  @override
  void dispose() {
    _timer?.cancel();
    _codeController.dispose();
    super.dispose();
  }

  Future<void> _verify() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;

    final verified = await context.read<AuthProvider>().verifyOtp(
      _codeController.text,
    );
    if (!mounted || !verified) return;
    Navigator.of(context).pop(true);
  }

  Future<void> _resend() async {
    final auth = context.read<AuthProvider>();
    final sent = await auth.resendOtp();
    if (!mounted || !sent) return;

    final challenge = auth.pendingMfa;
    if (challenge != null) {
      setState(() {
        _codeController.clear();
        _resetTimers(challenge);
      });
    }
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(content: Text('A new verification code was sent.')),
    );
  }

  String _formatTime(int seconds) {
    final minutes = seconds ~/ 60;
    final remainder = seconds % 60;
    return '$minutes:${remainder.toString().padLeft(2, '0')}';
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final challenge = auth.pendingMfa ?? widget.challenge;
    final codeError = auth.fieldErrors?['code']?.first;

    return Scaffold(
      appBar: AppBar(title: const Text('Email verification')),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(28),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 390),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const CircleAvatar(
                      radius: 30,
                      backgroundColor: AppColors.softTeal,
                      child: Icon(
                        Icons.mark_email_read_outlined,
                        color: AppColors.primary,
                        size: 30,
                      ),
                    ),
                    const SizedBox(height: 24),
                    Text(
                      challenge.isRegistration
                          ? 'Verify your USP email'
                          : 'Confirm it’s you',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      'Enter the 6-digit code sent to ${challenge.maskedEmail}.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: AppColors.muted,
                        height: 1.5,
                      ),
                    ),
                    const SizedBox(height: 28),
                    TextFormField(
                      key: const Key('otp-code-field'),
                      controller: _codeController,
                      autofocus: true,
                      keyboardType: TextInputType.number,
                      textInputAction: TextInputAction.done,
                      textAlign: TextAlign.center,
                      maxLength: 6,
                      inputFormatters: [FilteringTextInputFormatter.digitsOnly],
                      style: const TextStyle(
                        fontSize: 28,
                        fontWeight: FontWeight.w700,
                        letterSpacing: 10,
                      ),
                      decoration: InputDecoration(
                        labelText: 'Verification code',
                        counterText: '',
                        errorText: codeError,
                      ),
                      validator: (value) => value?.length == 6
                          ? null
                          : 'Enter the complete 6-digit code.',
                      onFieldSubmitted: auth.isLoading
                          ? null
                          : (_) => _verify(),
                    ),
                    const SizedBox(height: 12),
                    Text(
                      _expiresIn > 0
                          ? 'Code expires in ${_formatTime(_expiresIn)}'
                          : 'This code may have expired. Request a new one.',
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: AppColors.muted,
                        fontSize: 12,
                      ),
                    ),
                    if (auth.error != null && codeError == null) ...[
                      const SizedBox(height: 12),
                      Text(
                        auth.error!,
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: Colors.red),
                      ),
                    ],
                    const SizedBox(height: 24),
                    FilledButton(
                      key: const Key('verify-otp-button'),
                      onPressed: auth.isLoading ? null : _verify,
                      child: auth.isLoading
                          ? const SizedBox.square(
                              dimension: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.5,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Verify'),
                    ),
                    const SizedBox(height: 10),
                    TextButton(
                      key: const Key('resend-otp-button'),
                      onPressed: auth.isLoading || _resendIn > 0
                          ? null
                          : _resend,
                      child: Text(
                        _resendIn > 0
                            ? 'Resend code in ${_formatTime(_resendIn)}'
                            : 'Resend Code',
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}
