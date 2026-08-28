import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/theme/app_theme.dart';
import '../application/auth_provider.dart';

const _registrationTeal = AppColors.primary;
const _registrationInk = AppColors.ink;
const _registrationMuted = AppColors.muted;

final _primaryButtonStyle = FilledButton.styleFrom(
  minimumSize: const Size.fromHeight(52),
);

const _privacySummary = [
  'SheZen Harmony is a wellbeing support tool.',
  'It is not a replacement for professional medical care.',
  'Your student login information is used for authentication only.',
  'SheZen uses a persistent pseudonymous system ID internally to represent you.',
  'Assessment data is treated as sensitive information.',
  'Only authorised administrators may access approved system information.',
  'Usage analytics never contain sensitive wellbeing information.',
];

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmController = TextEditingController();
  final _genderController = TextEditingController();
  final _countryController = TextEditingController();
  final _employmentController = TextEditingController();
  final _relationshipController = TextEditingController();
  final _livingSituationController = TextEditingController();
  bool? _hasChildren;
  bool _privacyConsent = false;
  bool _obscurePassword = true;
  int _step = 0;
  bool _registrationComplete = false;

  @override
  void dispose() {
    _emailController.dispose();
    _passwordController.dispose();
    _confirmController.dispose();
    _genderController.dispose();
    _countryController.dispose();
    _employmentController.dispose();
    _relationshipController.dispose();
    _livingSituationController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    FocusScope.of(context).unfocus();
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final auth = context.read<AuthProvider>();
    final success = await auth.register(
      email: _emailController.text.trim(),
      password: _passwordController.text,
      passwordConfirmation: _confirmController.text,
      demographics: {
        'gender': _genderController.text,
        'country': _countryController.text,
        'employment_status': _employmentController.text,
        'relationship_status': _relationshipController.text,
        'has_children': _hasChildren,
        'living_situation': _livingSituationController.text,
      },
      privacyConsent: _privacyConsent,
    );
    if (!mounted) return;
    if (success) {
      setState(() => _registrationComplete = true);
    } else if (auth.fieldErrors != null) {
      final keys = auth.fieldErrors!.keys;
      setState(() {
        if (keys.any((key) => key == 'email' || key == 'password')) {
          _step = 0;
        } else if (keys.contains('privacy_consent')) {
          _step = 2;
        } else if (keys.any((key) => key.startsWith('demographics.'))) {
          _step = 3;
        }
      });
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('We couldn\'t create your account. Please try again.'),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final fieldErrors = auth.fieldErrors;
    if (_registrationComplete) {
      return _RegistrationCompleteScreen(
        shezenId: auth.session!.shezenId,
        onContinue: () => Navigator.of(context).pop(),
      );
    }

    return Scaffold(
      appBar: AppBar(
        automaticallyImplyLeading: false,
        leading: IconButton(
          onPressed: auth.isLoading ? null : _goBack,
          icon: const Icon(Icons.arrow_back_rounded, size: 20),
        ),
        titleSpacing: 0,
        title: Text(
          [
            'Create Account',
            'Student account details',
            'Privacy & Consent',
            'Your demographic profile',
          ][_step],
          style: const TextStyle(
            color: _registrationInk,
            fontWeight: FontWeight.w700,
            fontSize: 19,
          ),
        ),
      ),
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.fromLTRB(28, 6, 28, 30),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 390),
              child: Form(
                key: _formKey,
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    if (_step == 0) ...[
                      const Text(
                        'Registration starts with your USP student account. We check the email format, then create your pseudonymous SheZen profile securely.',
                        style: TextStyle(
                          color: _registrationMuted,
                          height: 1.45,
                        ),
                      ),
                      const SizedBox(height: 26),
                      _RegistrationField(
                        controller: _emailController,
                        label: 'USP Student Email',
                        errorText: fieldErrors?['email']?.first,
                        keyboardType: TextInputType.emailAddress,
                        textInputAction: TextInputAction.next,
                        validator: (value) {
                          final email = value?.trim() ?? '';
                          if (email.isEmpty) return 'Enter your email address.';
                          final studentEmail = RegExp(
                            r'^[A-Za-z0-9][A-Za-z0-9._%+-]*@student\.usp\.ac\.fj$',
                            caseSensitive: false,
                          );
                          if (!studentEmail.hasMatch(email)) {
                            return 'Use your @student.usp.ac.fj email.';
                          }
                          return null;
                        },
                      ),
                      const SizedBox(height: 18),
                      _RegistrationField(
                        controller: _passwordController,
                        label: 'Create Password',
                        errorText: fieldErrors?['password']?.first,
                        obscureText: _obscurePassword,
                        textInputAction: TextInputAction.next,
                        suffixIcon: IconButton(
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
                      _RegistrationField(
                        controller: _confirmController,
                        label: 'Confirm Password',
                        obscureText: _obscurePassword,
                        onFieldSubmitted: auth.isLoading
                            ? null
                            : (_) => _nextStep(),
                        validator: (value) => value != _passwordController.text
                            ? 'Passwords do not match.'
                            : null,
                      ),
                      const SizedBox(height: 20),
                      FilledButton(
                        style: _primaryButtonStyle,
                        onPressed: auth.isLoading ? null : _nextStep,
                        child: const Text('Verify Account'),
                      ),
                      const SizedBox(height: 26),
                      const _StepProgress(currentStep: 0),
                    ],
                    if (_step == 1) ...[
                      const SizedBox(height: 22),
                      Container(
                        padding: const EdgeInsets.fromLTRB(24, 28, 24, 22),
                        decoration: BoxDecoration(
                          color: Colors.white,
                          border: Border.all(color: AppColors.outline),
                          borderRadius: BorderRadius.circular(24),
                          boxShadow: const [
                            BoxShadow(
                              color: Color(0x167042A3),
                              blurRadius: 22,
                              offset: Offset(0, 10),
                            ),
                          ],
                        ),
                        child: Column(
                          children: [
                            const CircleAvatar(
                              radius: 28,
                              backgroundColor: AppColors.softTeal,
                              child: Icon(
                                Icons.shield_outlined,
                                color: _registrationTeal,
                              ),
                            ),
                            const SizedBox(height: 16),
                            const Text(
                              'Student login ready',
                              style: TextStyle(
                                color: _registrationInk,
                                fontWeight: FontWeight.w700,
                                fontSize: 17,
                              ),
                            ),
                            const SizedBox(height: 6),
                            Text(
                              _emailController.text.trim(),
                              textAlign: TextAlign.center,
                              style: const TextStyle(color: _registrationMuted),
                            ),
                            const SizedBox(height: 18),
                            Container(
                              padding: const EdgeInsets.all(16),
                              decoration: BoxDecoration(
                                color: AppColors.softGold,
                                borderRadius: BorderRadius.circular(20),
                              ),
                              child: const Text(
                                'Your student login format has been checked. Final verification happens securely when your profile is created, then your login stays inside the authentication layer.',
                                style: TextStyle(
                                  color: _registrationMuted,
                                  height: 1.5,
                                  fontSize: 12,
                                ),
                              ),
                            ),
                            const SizedBox(height: 20),
                            SizedBox(
                              width: double.infinity,
                              child: FilledButton(
                                style: _primaryButtonStyle,
                                onPressed: _nextStep,
                                child: const Text('Continue'),
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 26),
                      const _StepProgress(currentStep: 0),
                    ],
                    if (_step == 2) ...[
                      const SizedBox(height: 4),
                      for (final item in _privacySummary)
                        _PrivacyPoint(text: item),
                      Align(
                        alignment: Alignment.centerLeft,
                        child: TextButton(
                          onPressed: _showPrivacyNotice,
                          child: const Text('Read full Privacy & Data Use'),
                        ),
                      ),
                      Material(
                        color: AppColors.softGold,
                        borderRadius: BorderRadius.circular(24),
                        child: CheckboxListTile(
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(24),
                          ),
                          activeColor: _registrationTeal,
                          controlAffinity: ListTileControlAffinity.leading,
                          value: _privacyConsent,
                          onChanged: auth.isLoading
                              ? null
                              : (value) => setState(
                                  () => _privacyConsent = value ?? false,
                                ),
                          title: const Text('I understand and agree'),
                          subtitle:
                              fieldErrors?['privacy_consent']?.first == null
                              ? null
                              : Text(
                                  fieldErrors!['privacy_consent']!.first,
                                  style: const TextStyle(color: Colors.red),
                                ),
                        ),
                      ),
                      const SizedBox(height: 16),
                      FilledButton(
                        style: _primaryButtonStyle,
                        onPressed: auth.isLoading || !_privacyConsent
                            ? null
                            : _nextStep,
                        child: const Text('Continue'),
                      ),
                      const SizedBox(height: 24),
                      const _StepProgress(currentStep: 1),
                    ],
                    if (_step == 3) ...[
                      const Text(
                        'This information is linked to your SheZen ID and used for aggregate reporting only.',
                        style: TextStyle(
                          color: _registrationMuted,
                          height: 1.45,
                        ),
                      ),
                      const SizedBox(height: 20),
                      _ChoiceField(
                        controller: _genderController,
                        label: 'Gender',
                        options: const [
                          'Female',
                          'Male',
                          'Non-binary',
                          'Prefer not to say',
                        ],
                        errorText: fieldErrors?['demographics.gender']?.first,
                      ),
                      _ChoiceField(
                        controller: _countryController,
                        label: 'Country',
                        options: const [
                          'Fiji',
                          'Samoa',
                          'Tonga',
                          'Vanuatu',
                          'Solomon Islands',
                          'Other Pacific',
                        ],
                        errorText: fieldErrors?['demographics.country']?.first,
                      ),
                      _ChoiceField(
                        controller: _employmentController,
                        label: 'Employment status',
                        options: const [
                          'Not employed',
                          'Part-time',
                          'Full-time',
                          'Prefer not to say',
                        ],
                        errorText:
                            fieldErrors?['demographics.employment_status']
                                ?.first,
                      ),
                      _ChoiceField(
                        controller: _relationshipController,
                        label: 'Relationship status',
                        options: const [
                          'Single',
                          'Partnered',
                          'Married',
                          'Prefer not to say',
                        ],
                        errorText:
                            fieldErrors?['demographics.relationship_status']
                                ?.first,
                      ),
                      _BooleanChoiceField(
                        label: 'Do you have children?',
                        value: _hasChildren,
                        errorText:
                            fieldErrors?['demographics.has_children']?.first,
                        onChanged: (value) =>
                            setState(() => _hasChildren = value),
                      ),
                      _ChoiceField(
                        controller: _livingSituationController,
                        label: 'Living situation',
                        options: const [
                          'With family',
                          'Campus housing',
                          'Shared housing',
                          'Living alone',
                          'Other',
                        ],
                        errorText: fieldErrors?['demographics.living_situation']
                            ?.first,
                      ),
                      const SizedBox(height: 8),
                      FilledButton(
                        style: _primaryButtonStyle,
                        onPressed: auth.isLoading ? null : _submit,
                        child: auth.isLoading
                            ? const SizedBox.square(
                                dimension: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.5,
                                  color: Colors.white,
                                ),
                              )
                            : const Text('Generate my SheZen ID'),
                      ),
                      const SizedBox(height: 24),
                      const _StepProgress(currentStep: 2),
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

  void _nextStep() {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() => _step++);
  }

  void _goBack() {
    if (_step == 0) {
      Navigator.of(context).pop();
      return;
    }
    setState(() => _step--);
  }

  Future<void> _showPrivacyNotice() => showModalBottomSheet<void>(
    context: context,
    isScrollControlled: true,
    showDragHandle: true,
    builder: (context) => const _PrivacyNotice(),
  );
}

class _StepProgress extends StatelessWidget {
  const _StepProgress({required this.currentStep});

  final int currentStep;

  @override
  Widget build(BuildContext context) {
    const labels = ['Verify', 'Consent', 'Demographics', 'SheZen ID'];
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        for (var index = 0; index < labels.length; index++)
          Expanded(
            child: Padding(
              padding: EdgeInsets.only(
                right: index == labels.length - 1 ? 0 : 4,
              ),
              child: Column(
                children: [
                  AnimatedContainer(
                    duration: const Duration(milliseconds: 220),
                    height: 4,
                    decoration: BoxDecoration(
                      color: index <= currentStep
                          ? _registrationTeal
                          : const Color(0xFFD7E0DD),
                      borderRadius: BorderRadius.circular(20),
                    ),
                  ),
                  const SizedBox(height: 6),
                  Text(
                    labels[index],
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: _registrationMuted,
                      fontSize: 9,
                    ),
                  ),
                ],
              ),
            ),
          ),
      ],
    );
  }
}

class _PrivacyPoint extends StatelessWidget {
  const _PrivacyPoint({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 10),
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
    decoration: BoxDecoration(
      color: Colors.white,
      border: Border.all(color: const Color(0xFFD7E0DD)),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(Icons.check_rounded, color: _registrationTeal, size: 18),
        const SizedBox(width: 10),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(
              color: _registrationMuted,
              height: 1.4,
              fontSize: 13,
            ),
          ),
        ),
      ],
    ),
  );
}

class _RegistrationCompleteScreen extends StatelessWidget {
  const _RegistrationCompleteScreen({
    required this.shezenId,
    required this.onContinue,
  });

  final String shezenId;
  final VoidCallback onContinue;

  @override
  Widget build(BuildContext context) => Scaffold(
    body: DecoratedBox(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [Color(0xFFD8F4ED), Color(0xFFF2E9FF), Color(0xFFFFF4E5)],
        ),
      ),
      child: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 28, vertical: 40),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 390),
              child: Column(
                children: [
                  Container(
                    width: 60,
                    height: 60,
                    decoration: const BoxDecoration(
                      color: Colors.white,
                      shape: BoxShape.circle,
                      boxShadow: [
                        BoxShadow(
                          color: Color(0x140C3E3C),
                          blurRadius: 20,
                          offset: Offset(0, 8),
                        ),
                      ],
                    ),
                    child: const Icon(
                      Icons.check_rounded,
                      color: _registrationTeal,
                      size: 30,
                    ),
                  ),
                  const SizedBox(height: 28),
                  const Text(
                    'Your SheZen profile is ready',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: _registrationInk,
                      fontFamily: 'serif',
                      fontWeight: FontWeight.w700,
                      fontSize: 23,
                    ),
                  ),
                  const SizedBox(height: 14),
                  const Text(
                    'Your SheZen ID:',
                    style: TextStyle(color: _registrationMuted),
                  ),
                  const SizedBox(height: 8),
                  SelectableText(
                    shezenId,
                    textAlign: TextAlign.center,
                    style: const TextStyle(
                      color: _registrationTeal,
                      fontFamily: 'serif',
                      fontWeight: FontWeight.w700,
                      fontSize: 22,
                    ),
                  ),
                  const SizedBox(height: 18),
                  const Text(
                    'This ID is used within SheZen Harmony to help protect your identity. It stays the same every time you log in with your university account.',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: _registrationMuted,
                      height: 1.5,
                      fontSize: 12,
                    ),
                  ),
                  const SizedBox(height: 36),
                  SizedBox(
                    width: double.infinity,
                    child: FilledButton(
                      style: _primaryButtonStyle,
                      onPressed: onContinue,
                      child: const Text('Continue'),
                    ),
                  ),
                  const SizedBox(height: 26),
                  const _StepProgress(currentStep: 3),
                ],
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

class _RegistrationField extends StatelessWidget {
  const _RegistrationField({
    required this.controller,
    required this.label,
    required this.validator,
    this.errorText,
    this.keyboardType,
    this.textInputAction,
    this.obscureText = false,
    this.onFieldSubmitted,
    this.suffixIcon,
  });

  final TextEditingController controller;
  final String label;
  final FormFieldValidator<String> validator;
  final String? errorText;
  final TextInputType? keyboardType;
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
            color: _registrationInk,
            fontSize: 12,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      TextFormField(
        controller: controller,
        keyboardType: keyboardType,
        textInputAction: textInputAction,
        obscureText: obscureText,
        onFieldSubmitted: onFieldSubmitted,
        validator: validator,
        decoration: InputDecoration(
          hintText: label,
          errorText: errorText,
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

class _ChoiceField extends StatelessWidget {
  const _ChoiceField({
    required this.controller,
    required this.label,
    required this.options,
    this.errorText,
  });

  final TextEditingController controller;
  final String label;
  final List<String> options;
  final String? errorText;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 20),
    child: FormField<String>(
      initialValue: controller.text.isEmpty ? null : controller.text,
      validator: (value) => value == null || value.isEmpty
          ? 'Select your ${label.toLowerCase()}.'
          : null,
      builder: (state) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: _registrationInk,
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final option in options)
                ChoiceChip(
                  label: Text(option),
                  selected: controller.text == option,
                  selectedColor: _registrationTeal,
                  backgroundColor: Colors.white,
                  side: const BorderSide(color: Color(0xFFD2DDDA)),
                  labelStyle: TextStyle(
                    color: controller.text == option
                        ? Colors.white
                        : _registrationInk,
                    fontSize: 12,
                  ),
                  onSelected: (_) {
                    controller.text = option;
                    state.didChange(option);
                  },
                ),
            ],
          ),
          if (errorText ?? state.errorText case final String message) ...[
            const SizedBox(height: 6),
            Text(
              message,
              style: TextStyle(
                color: Theme.of(context).colorScheme.error,
                fontSize: 12,
              ),
            ),
          ],
        ],
      ),
    ),
  );
}

class _BooleanChoiceField extends StatelessWidget {
  const _BooleanChoiceField({
    required this.label,
    required this.value,
    required this.onChanged,
    this.errorText,
  });

  final String label;
  final bool? value;
  final ValueChanged<bool> onChanged;
  final String? errorText;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 20),
    child: FormField<bool>(
      initialValue: value,
      validator: (value) => value == null ? 'Select an answer.' : null,
      builder: (state) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              color: _registrationInk,
              fontSize: 14,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              for (final option in const [(false, 'No'), (true, 'Yes')])
                ChoiceChip(
                  label: Text(option.$2),
                  selected: value == option.$1,
                  selectedColor: _registrationTeal,
                  backgroundColor: Colors.white,
                  side: const BorderSide(color: Color(0xFFD2DDDA)),
                  labelStyle: TextStyle(
                    color: value == option.$1 ? Colors.white : _registrationInk,
                    fontSize: 12,
                  ),
                  onSelected: (_) {
                    onChanged(option.$1);
                    state.didChange(option.$1);
                  },
                ),
            ],
          ),
          if (errorText ?? state.errorText case final String message) ...[
            const SizedBox(height: 6),
            Text(
              message,
              style: TextStyle(
                color: Theme.of(context).colorScheme.error,
                fontSize: 12,
              ),
            ),
          ],
        ],
      ),
    ),
  );
}

class _PrivacyNotice extends StatelessWidget {
  const _PrivacyNotice();

  @override
  Widget build(BuildContext context) => SafeArea(
    child: DraggableScrollableSheet(
      expand: false,
      initialChildSize: 0.82,
      maxChildSize: 0.95,
      builder: (context, controller) => ListView(
        controller: controller,
        padding: const EdgeInsets.fromLTRB(24, 4, 24, 32),
        children: [
          Text(
            'Privacy & Data Use',
            style: Theme.of(
              context,
            ).textTheme.headlineSmall?.copyWith(fontWeight: FontWeight.w800),
          ),
          const SizedBox(height: 8),
          Text(
            'Policy version: shezen-privacy-notice-v1-draft',
            style: Theme.of(context).textTheme.labelMedium,
          ),
          const SizedBox(height: 16),
          const _NoticeSection(
            title: 'Information SheZen collects',
            body:
                'SheZen uses your USP student email for registration, login, and account security. It also collects the demographic details entered during registration, stress questionnaire responses and results, assessment history, progress information, and interactions with wellbeing resources and supported features.',
          ),
          const _NoticeSection(
            title: 'How your identity is protected',
            body:
                'Your real name is not required. After authentication, wellbeing records use a persistent pseudonymous student identity. Your email is not intended to appear alongside assessment, progress, demographic, or wellbeing records in normal application or administration use. SheZen is pseudonymised, not completely anonymous.',
          ),
          const _NoticeSection(
            title: 'Why information is used',
            body:
                'Information supports your stress assessment and history, relevant wellbeing features and resources, operation and improvement of SheZen, understanding feature use, and approved aggregate demographic and wellbeing analytics.',
          ),
          const _NoticeSection(
            title: 'Administrator access',
            body:
                'Authorised administrators may access approved aggregate or pseudonymous information needed to operate and evaluate SheZen. Normal wellbeing analytics should not show a student name, USP email, or student ID alongside individual wellbeing information.',
          ),
          const _NoticeSection(
            title: 'Sensitive wellbeing information',
            body:
                'Stress questionnaire responses and wellbeing information are sensitive data. Questionnaire answers, scores, stress tiers, names, student IDs, and email addresses must not be placed in application analytics or ordinary debug logs.',
          ),
          const _NoticeSection(
            title: 'Account deletion',
            body:
                'Under the current MVP behavior, explicitly deleting your account removes your individual wellbeing records, demographic profile, and identity mapping. Only genuinely aggregated, non-identifying analytics may remain.',
          ),
          const Text(
            'Important: this draft notice and its account-deletion wording require final client/university review and approval.',
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: 20),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('Close'),
          ),
        ],
      ),
    ),
  );
}

class _NoticeSection extends StatelessWidget {
  const _NoticeSection({required this.title, required this.body});

  final String title;
  final String body;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 18),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
        const SizedBox(height: 4),
        Text(body),
      ],
    ),
  );
}
