import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_theme.dart';
import '../application/auth_provider.dart';

class PasswordResetScreen extends StatefulWidget {
  const PasswordResetScreen({super.key});

  @override
  State<PasswordResetScreen> createState() => _PasswordResetScreenState();
}

class _PasswordResetScreenState extends State<PasswordResetScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _codeController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  int _step = 0;
  bool _obscurePassword = true;
  bool _hasSubmitted = false;

  @override
  void dispose() {
    _emailController.dispose();
    _codeController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _hasSubmitted = true);
    final auth = context.read<AuthProvider>();
    final email = _emailController.text.trim();
    if (_step == 0) {
      if (await auth.requestPasswordReset(email) && mounted) {
        setState(() => _step = 1);
      }
    } else if (_step == 1) {
      if (await auth.verifyPasswordResetCode(email, _codeController.text) &&
          mounted) {
        setState(() => _step = 2);
      }
    } else {
      final reset = await auth.resetPassword(
        email: email,
        code: _codeController.text,
        password: _passwordController.text,
        passwordConfirmation: _confirmController.text,
      );
      if (reset && mounted) Navigator.of(context).pop(true);
    }
  }

  Future<void> _resend() async {
    setState(() => _hasSubmitted = true);
    final sent = await context.read<AuthProvider>().requestPasswordReset(
      _emailController.text.trim(),
    );
    if (!mounted || !sent) return;
    setState(() {
      _codeController.clear();
      _step = 1;
    });
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('If this email is registered, a new code has been sent.'),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final fieldErrors = _hasSubmitted ? auth.fieldErrors : null;
    final error = _hasSubmitted ? auth.error : null;
    final title = switch (_step) {
      0 => 'Forgot your password?',
      1 => 'Check your email',
      _ => 'Create a new password',
    };
    final description = switch (_step) {
      0 => 'Enter your USP student email to request a reset code.',
      1 => 'If this email is registered, enter the 6-digit code sent to it.',
      _ => 'Use at least 8 characters for your new password.',
    };

    return Scaffold(
      appBar: AppBar(title: const Text('Reset password')),
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
                        Icons.lock_reset_outlined,
                        color: AppColors.primary,
                        size: 30,
                      ),
                    ),
                    const SizedBox(height: 24),
                    Text(
                      title,
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(
                            fontWeight: FontWeight.w700,
                            color: AppColors.ink,
                          ),
                    ),
                    const SizedBox(height: 10),
                    Text(
                      description,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: AppColors.muted,
                        height: 1.5,
                      ),
                    ),
                    const SizedBox(height: 28),
                    if (_step == 0)
                      TextFormField(
                        key: const Key('reset-email-field'),
                        controller: _emailController,
                        keyboardType: TextInputType.emailAddress,
                        autofillHints: const [AutofillHints.email],
                        decoration: InputDecoration(
                          labelText: 'USP student email',
                          errorText: fieldErrors?['email']?.first,
                        ),
                        validator: (value) {
                          final email = value?.trim() ?? '';
                          if (email.isEmpty) return 'Enter your email address.';
                          if (!RegExp(
                            r'^[^@\s]+@student\.usp\.ac\.fj$',
                            caseSensitive: false,
                          ).hasMatch(email)) {
                            return 'Use your @student.usp.ac.fj email.';
                          }
                          return null;
                        },
                      ),
                    if (_step == 1)
                      TextFormField(
                        key: const Key('reset-code-field'),
                        controller: _codeController,
                        autofocus: true,
                        keyboardType: TextInputType.number,
                        textAlign: TextAlign.center,
                        maxLength: 6,
                        inputFormatters: [
                          FilteringTextInputFormatter.digitsOnly,
                        ],
                        decoration: InputDecoration(
                          labelText: 'Verification code',
                          counterText: '',
                          errorText: fieldErrors?['code']?.first,
                        ),
                        validator: (value) => value?.length == 6
                            ? null
                            : 'Enter the complete 6-digit code.',
                      ),
                    if (_step == 2) ...[
                      TextFormField(
                        key: const Key('reset-password-field'),
                        controller: _passwordController,
                        obscureText: _obscurePassword,
                        autofillHints: const [AutofillHints.newPassword],
                        decoration: InputDecoration(
                          labelText: 'New password',
                          errorText: fieldErrors?['password']?.first,
                          suffixIcon: IconButton(
                            tooltip: _obscurePassword
                                ? 'Show password'
                                : 'Hide password',
                            onPressed: () => setState(
                              () => _obscurePassword = !_obscurePassword,
                            ),
                            icon: Icon(
                              _obscurePassword
                                  ? Icons.visibility_off_outlined
                                  : Icons.visibility_outlined,
                            ),
                          ),
                        ),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Enter a password.';
                          }
                          if (value.length < 8) {
                            return 'Use at least 8 characters.';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 18),
                      TextFormField(
                        key: const Key('reset-confirm-field'),
                        controller: _confirmController,
                        obscureText: _obscurePassword,
                        autofillHints: const [AutofillHints.newPassword],
                        decoration: const InputDecoration(
                          labelText: 'Confirm new password',
                        ),
                        validator: (value) => value != _passwordController.text
                            ? 'Passwords do not match.'
                            : null,
                      ),
                    ],
                    if (error != null &&
                        (fieldErrors == null ||
                            (_step == 2 && fieldErrors['code'] != null))) ...[
                      const SizedBox(height: 12),
                      Text(
                        _step == 2 && fieldErrors?['code'] != null
                            ? fieldErrors!['code']!.first
                            : error,
                        textAlign: TextAlign.center,
                        style: const TextStyle(color: Colors.red),
                      ),
                    ],
                    const SizedBox(height: 24),
                    FilledButton(
                      onPressed: auth.isLoading ? null : _submit,
                      child: auth.isLoading
                          ? const SizedBox.square(
                              dimension: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.5,
                                color: Colors.white,
                              ),
                            )
                          : Text(switch (_step) {
                              0 => 'Send verification code',
                              1 => 'Verify code',
                              _ => 'Reset password',
                            }),
                    ),
                    if (_step == 1) ...[
                      const SizedBox(height: 10),
                      TextButton(
                        onPressed: auth.isLoading ? null : _resend,
                        child: const Text('Send another code'),
                      ),
                    ],
                    if (_step == 2 && fieldErrors?['code'] != null) ...[
                      const SizedBox(height: 10),
                      TextButton(
                        onPressed: auth.isLoading ? null : _resend,
                        child: const Text('Request a new code'),
                      ),
                    ],
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
