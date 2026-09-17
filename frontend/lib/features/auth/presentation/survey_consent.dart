import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/platform/app_exit.dart';
import '../../../core/theme/app_theme.dart';
import '../application/auth_provider.dart';

/// The survey participation consent every student must agree to before using
/// SheZen Harmony. This is the one consent in the app: registration shows it
/// as its consent step, and [SurveyConsentScreen] shows the same content to
/// an existing account whose recorded consent predates the current wording.
class SurveyConsentContent extends StatelessWidget {
  const SurveyConsentContent({
    super.key,
    required this.onAccept,
    required this.onDecline,
    this.isBusy = false,
    this.errorText,
  });

  final VoidCallback onAccept;
  final VoidCallback onDecline;
  final bool isBusy;
  final String? errorText;

  static const consentQuestion =
      'I have read the information above and agree to participate in this survey.';

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      const _ConsentSection(
        icon: Icons.psychology_outlined,
        title: 'Purpose of the survey',
        body:
            'This survey aims to understand the mental health, wellbeing, academic stress, social support, safety concerns, and help-seeking needs of female university students.',
      ),
      const _ConsentSection(
        icon: Icons.lock_outline_rounded,
        title: 'Confidentiality',
        body:
            'Your responses will be kept confidential and used only for research or student wellbeing improvement purposes. No individual student will be identified in any report.',
      ),
      const _ConsentSection(
        icon: Icons.volunteer_activism_outlined,
        title: 'Participation is voluntary.',
      ),
      const _ConsentSection(
        icon: Icons.info_outline_rounded,
        title: 'Important note',
        body:
            'This survey is not a medical diagnosis. If any question makes you feel uncomfortable or distressed, please contact a university counsellor, health clinic, trusted staff member, or emergency support service.',
        emphasised: true,
      ),
      const SizedBox(height: 6),
      Container(
        padding: const EdgeInsets.fromLTRB(18, 16, 18, 16),
        decoration: BoxDecoration(
          color: AppColors.softGold,
          borderRadius: BorderRadius.circular(24),
        ),
        child: const Text(
          '“$consentQuestion”',
          style: TextStyle(
            color: AppColors.ink,
            fontWeight: FontWeight.w700,
            height: 1.45,
          ),
        ),
      ),
      if (errorText != null) ...[
        const SizedBox(height: 10),
        Text(
          errorText!,
          style: TextStyle(color: Theme.of(context).colorScheme.error),
        ),
      ],
      const SizedBox(height: 16),
      FilledButton(
        key: const Key('consent-yes'),
        style: FilledButton.styleFrom(minimumSize: const Size.fromHeight(52)),
        onPressed: isBusy ? null : onAccept,
        child: isBusy
            ? const SizedBox.square(
                dimension: 22,
                child: CircularProgressIndicator(
                  strokeWidth: 2.5,
                  color: Colors.white,
                ),
              )
            : const Text('Yes'),
      ),
      const SizedBox(height: 10),
      OutlinedButton(
        key: const Key('consent-no'),
        style: OutlinedButton.styleFrom(
          minimumSize: const Size.fromHeight(52),
          foregroundColor: AppColors.ink,
          side: const BorderSide(color: AppColors.outline),
        ),
        onPressed: isBusy ? null : onDecline,
        child: const Text('No'),
      ),
    ],
  );
}

/// Asks the student to confirm "No" before the consequence is carried out.
/// Returns true when they confirm. [deletesAccount] is true for an existing
/// account, whose data is removed; during registration nothing exists yet.
Future<bool> confirmConsentDecline(
  BuildContext context, {
  required bool deletesAccount,
}) async {
  final confirmed = await showDialog<bool>(
    context: context,
    builder: (dialogContext) => AlertDialog(
      icon: Icon(
        Icons.logout_rounded,
        color: Theme.of(dialogContext).colorScheme.error,
      ),
      title: Text(
        deletesAccount ? 'Withdraw consent and leave?' : 'Leave SheZen Harmony?',
      ),
      content: Text(
        deletesAccount
            ? 'Without your consent SheZen Harmony cannot keep your account. Your account and all of its data — profile, stress checks, diary and progress — will be permanently deleted, and the app will close. You are welcome to register again if you change your mind.'
            : 'Without your consent SheZen Harmony cannot create your account or run the survey. The app will close. You are welcome to come back and register whenever you like.',
      ),
      actions: [
        TextButton(
          onPressed: () => Navigator.of(dialogContext).pop(false),
          child: const Text('Go back'),
        ),
        FilledButton(
          key: const Key('consent-decline-confirm'),
          style: FilledButton.styleFrom(
            backgroundColor: Theme.of(dialogContext).colorScheme.error,
          ),
          onPressed: () => Navigator.of(dialogContext).pop(true),
          child: Text(deletesAccount ? 'Delete and close' : 'Close app'),
        ),
      ],
    ),
  );
  return confirmed == true;
}

/// Gate for a signed-in account without consent to the current wording.
/// The root screen shows this ahead of everything else, so there is nothing
/// behind it to go back to: the system back gesture is swallowed, and the
/// only exits are "Yes" (records consent, root moves on) and "No" (account
/// deleted, app closed).
class SurveyConsentScreen extends StatefulWidget {
  const SurveyConsentScreen({super.key, this.closeApp = closeApplication});

  /// Injected so tests can observe the exit without ending the test runner.
  final Future<void> Function() closeApp;

  @override
  State<SurveyConsentScreen> createState() => _SurveyConsentScreenState();
}

class _SurveyConsentScreenState extends State<SurveyConsentScreen> {
  bool _leaving = false;

  Future<void> _accept() async {
    // On success the root screen replaces this one; on failure the provider's
    // error is rendered under the consent question.
    await context.read<AuthProvider>().acceptConsent();
  }

  Future<void> _decline() async {
    final confirmed = await confirmConsentDecline(
      context,
      deletesAccount: true,
    );
    if (!confirmed || !mounted) return;

    setState(() => _leaving = true);
    await context.read<AuthProvider>().declineConsent();
    await widget.closeApp();
  }

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();
    final busy = _leaving || auth.isLoading || auth.isDeletingAccount;
    return PopScope(
      canPop: false,
      child: Scaffold(
        appBar: AppBar(
          automaticallyImplyLeading: false,
          titleSpacing: 24,
          title: const Text(
            'Privacy & Consent',
            style: TextStyle(
              color: AppColors.ink,
              fontWeight: FontWeight.w700,
              fontSize: 18,
            ),
          ),
        ),
        body: SafeArea(
          child: Center(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(28, 6, 28, 30),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 390),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    const Text(
                      'Before you continue, please read the information below and let us know whether you agree to take part.',
                      style: TextStyle(color: AppColors.muted, height: 1.45),
                    ),
                    const SizedBox(height: 18),
                    SurveyConsentContent(
                      onAccept: _accept,
                      onDecline: _decline,
                      isBusy: busy,
                      errorText: _leaving ? null : auth.error,
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

class _ConsentSection extends StatelessWidget {
  const _ConsentSection({
    required this.icon,
    required this.title,
    this.body,
    this.emphasised = false,
  });

  final IconData icon;
  final String title;
  final String? body;
  final bool emphasised;

  @override
  Widget build(BuildContext context) => Container(
    margin: const EdgeInsets.only(bottom: 10),
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 14),
    decoration: BoxDecoration(
      color: emphasised ? AppColors.softTeal : Colors.white,
      border: Border.all(color: const Color(0xFFD7E0DD)),
      borderRadius: BorderRadius.circular(20),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(icon, color: AppColors.primary, size: 20),
        const SizedBox(width: 10),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                title,
                style: const TextStyle(
                  color: AppColors.ink,
                  fontWeight: FontWeight.w700,
                  fontSize: 14,
                ),
              ),
              if (body != null) ...[
                const SizedBox(height: 4),
                Text(
                  body!,
                  style: const TextStyle(
                    color: AppColors.muted,
                    height: 1.4,
                    fontSize: 13,
                  ),
                ),
              ],
            ],
          ),
        ),
      ],
    ),
  );
}
