import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_theme.dart';
import '../application/auth_provider.dart';
import 'register_screen.dart';
import 'otp_verification_screen.dart';

const _teal = AppColors.primary;
const _ink = AppColors.ink;
const _muted = AppColors.muted;

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final success = await context.read<AuthProvider>().login(
      email: _emailController.text.trim(),
      password: _passwordController.text,
    );
    if (!mounted) return;
    if (success) {
      final challenge = context.read<AuthProvider>().pendingMfa;
      if (challenge != null) {
        await Navigator.of(context).push<bool>(
          MaterialPageRoute(
            builder: (_) => OtpVerificationScreen(challenge: challenge),
          ),
        );
      }
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          context.read<AuthProvider>().error ??
              'We couldn\'t sign you in. Check your details and try again.',
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    return Scaffold(
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(24, 36, 24, 28),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 390),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Align(
                      child: Container(
                        width: 58,
                        height: 58,
                        decoration: const BoxDecoration(
                          color: _teal,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.self_improvement_rounded,
                          size: 31,
                          color: Colors.white,
                        ),
                      ),
                    ),
                    const SizedBox(height: 24),
                    Text(
                      'Welcome to SheZen\nHarmony',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.headlineSmall
                          ?.copyWith(
                            height: 1.18,
                            fontWeight: FontWeight.w700,
                            color: _ink,
                          ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Sign in with your USP student account',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: _muted),
                    ),
                    const SizedBox(height: 34),
                    _LoginField(
                      controller: _emailController,
                      label: 'USP student email',
                      keyboardType: TextInputType.emailAddress,
                      autofillHints: const [AutofillHints.email],
                      textInputAction: TextInputAction.next,
                      validator: (value) {
                        final email = value?.trim() ?? '';
                        if (email.isEmpty) return 'Enter your email address.';
                        if (!RegExp(
                          r'^[^@\s]+@[^@\s]+\.[^@\s]+$',
                        ).hasMatch(email)) {
                          return 'Enter a valid email address.';
                        }
                        if (!RegExp(
                          r'^[^@\s]+@student\.usp\.ac\.fj$',
                          caseSensitive: false,
                        ).hasMatch(email)) {
                          return 'Use your @student.usp.ac.fj email.';
                        }
                        return null;
                      },
                    ),
                    const SizedBox(height: 18),
                    _LoginField(
                      controller: _passwordController,
                      label: 'Password',
                      obscureText: _obscurePassword,
                      autofillHints: const [AutofillHints.password],
                      onFieldSubmitted: auth.isLoading
                          ? null
                          : (_) => _submit(),
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
                          size: 19,
                        ),
                      ),
                      validator: (value) => value == null || value.isEmpty
                          ? 'Enter your password.'
                          : null,
                    ),
                    const SizedBox(height: 18),
                    FilledButton(
                      style: FilledButton.styleFrom(
                        backgroundColor: _teal,
                        minimumSize: const Size.fromHeight(48),
                      ),
                      onPressed: auth.isLoading ? null : _submit,
                      child: auth.isLoading
                          ? const SizedBox.square(
                              dimension: 22,
                              child: CircularProgressIndicator(
                                strokeWidth: 2.5,
                                color: Colors.white,
                              ),
                            )
                          : const Text('Sign in'),
                    ),
                    const SizedBox(height: 8),
                    const Row(
                      children: [
                        Expanded(child: Divider()),
                        Padding(
                          padding: EdgeInsets.symmetric(horizontal: 14),
                          child: Text('or', style: TextStyle(color: _muted)),
                        ),
                        Expanded(child: Divider()),
                      ],
                    ),
                    const SizedBox(height: 18),
                    OutlinedButton(
                      style: OutlinedButton.styleFrom(
                        foregroundColor: _ink,
                        minimumSize: const Size.fromHeight(48),
                        side: const BorderSide(color: Color(0xFFD4DDDA)),
                      ),
                      onPressed: auth.isLoading
                          ? null
                          : () => Navigator.of(context).push(
                              MaterialPageRoute(
                                builder: (_) => const RegisterScreen(),
                              ),
                            ),
                      child: const Text('Create Account'),
                    ),
                    const SizedBox(height: 26),
                    const Text(
                      'Your university login is used only to verify that you are an enrolled student. Inside SheZen you are represented by a pseudonymous SheZen ID.',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: _muted,
                        height: 1.55,
                        fontSize: 12,
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

class _LoginField extends StatelessWidget {
  const _LoginField({
    required this.controller,
    required this.label,
    required this.validator,
    this.keyboardType,
    this.autofillHints,
    this.textInputAction,
    this.obscureText = false,
    this.onFieldSubmitted,
    this.suffixIcon,
  });

  final TextEditingController controller;
  final String label;
  final FormFieldValidator<String> validator;
  final TextInputType? keyboardType;
  final Iterable<String>? autofillHints;
  final TextInputAction? textInputAction;
  final bool obscureText;
  final ValueChanged<String>? onFieldSubmitted;
  final Widget? suffixIcon;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Padding(
        padding: const EdgeInsets.only(left: 2, bottom: 7),
        child: Text(
          label,
          style: const TextStyle(
            color: _ink,
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      TextFormField(
        controller: controller,
        keyboardType: keyboardType,
        autofillHints: autofillHints,
        textInputAction: textInputAction,
        obscureText: obscureText,
        onFieldSubmitted: onFieldSubmitted,
        validator: validator,
        decoration: InputDecoration(
          hintText: label,
          suffixIcon: suffixIcon,
          isDense: true,
          filled: true,
          fillColor: Colors.white,
          contentPadding: const EdgeInsets.symmetric(
            horizontal: 14,
            vertical: 13,
          ),
        ),
      ),
    ],
  );
}
