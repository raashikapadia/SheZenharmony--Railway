import 'package:flutter/material.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/theme/app_theme.dart';
import '../data/support_content.dart';

/// The helpline directory at the top of the Resource tab.
///
/// Every entry is admin-published through the Web Admin's Resource section, so
/// contacts change without an app release. The order is the admin's "Display
/// order"; an emergency contact is tinted and badged rather than forced to the
/// top, so the team can decide what a student should reach for first.
class HelplineSection extends StatelessWidget {
  const HelplineSection({super.key, required this.helplines});

  final List<HelplineResource> helplines;

  @override
  Widget build(BuildContext context) {
    if (helplines.isEmpty) return const SizedBox.shrink();

    final theme = Theme.of(context);

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          toMathBold('Talk to someone 💜'),
          style: theme.textTheme.titleMedium?.copyWith(
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: AppSpacing.xs),
        Text(
          'Real people, trained to listen. Reach out whenever you need to — '
          'you do not have to be in crisis to call.',
          style: theme.textTheme.bodySmall?.copyWith(
            color: AppColors.muted,
            height: 1.4,
          ),
        ),
        const SizedBox(height: AppSpacing.lg),
        for (final helpline in helplines) ...[
          _HelplineCard(helpline: helpline),
          const SizedBox(height: AppSpacing.md),
        ],
      ],
    );
  }
}

class _HelplineCard extends StatelessWidget {
  const _HelplineCard({required this.helpline});

  final HelplineResource helpline;

  bool get _isEmergency => helpline.isEmergency;

  /// Blush marks the urgent contacts; the calmer lavender is for everything
  /// else, matching the soft tints the rest of the app already uses.
  Color get _tint =>
      _isEmergency ? AppColors.softBlush : AppColors.softLavender;

  Color get _edge => _isEmergency
      ? AppColors.secondary.withValues(alpha: 0.35)
      : AppColors.outline;

  /// Opens a dialler, mail app, or browser. Deliberately never completes the
  /// action itself — the student takes the last step.
  Future<void> _launch(
    BuildContext context,
    Uri uri,
    String fallbackMessage,
  ) async {
    final messenger = ScaffoldMessenger.of(context);

    final opened = await launchUrl(uri, mode: LaunchMode.externalApplication);
    if (!opened && context.mounted) {
      messenger.showSnackBar(SnackBar(content: Text(fallbackMessage)));
    }
  }

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [_tint, AppColors.surface],
        ),
        borderRadius: BorderRadius.circular(AppRadii.card),
        border: Border.all(color: _edge),
      ),
      padding: const EdgeInsets.all(AppSpacing.lg),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Container(
                width: 42,
                height: 42,
                decoration: BoxDecoration(
                  color: AppColors.surface.withValues(alpha: 0.75),
                  borderRadius: BorderRadius.circular(13),
                ),
                child: Icon(
                  _isEmergency
                      ? Icons.favorite_rounded
                      : Icons.support_agent_rounded,
                  color: _isEmergency ? AppColors.secondary : AppColors.primary,
                  size: 22,
                ),
              ),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      toMathBold(helpline.name),
                      style: theme.textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                        height: 1.25,
                      ),
                    ),
                    if (helpline.organisation.isNotEmpty) ...[
                      const SizedBox(height: 2),
                      Text(
                        helpline.organisation,
                        style: theme.textTheme.bodySmall?.copyWith(
                          color: AppColors.muted,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              if (_isEmergency) ...[
                const SizedBox(width: AppSpacing.sm),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: AppSpacing.sm,
                    vertical: 4,
                  ),
                  decoration: BoxDecoration(
                    color: AppColors.secondary,
                    borderRadius: BorderRadius.circular(AppRadii.pill),
                  ),
                  child: Text(
                    'Emergency',
                    style: theme.textTheme.labelSmall?.copyWith(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ],
            ],
          ),
          if (helpline.description.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            Text(
              helpline.description,
              style: theme.textTheme.bodyMedium?.copyWith(height: 1.45),
            ),
          ],
          if (helpline.availability.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            _Chip(icon: Icons.schedule_rounded, label: helpline.availability),
          ],
          if (helpline.hasPhone) ...[
            const SizedBox(height: AppSpacing.lg),
            SizedBox(
              width: double.infinity,
              child: FilledButton.icon(
                onPressed: () => _launch(
                  context,
                  Uri(scheme: 'tel', path: helpline.dialableNumber),
                  'Dial ${helpline.phone} to reach this line.',
                ),
                icon: const Icon(Icons.call_rounded, size: 20),
                label: Text('Call ${helpline.phone}'),
              ),
            ),
          ],
          if (helpline.alternatePhone.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.sm),
            _ContactLink(
              icon: Icons.phone_in_talk_rounded,
              label: helpline.alternatePhone,
              caption: 'Alternate number',
              onTap: () => _launch(
                context,
                Uri(scheme: 'tel', path: helpline.dialableAlternateNumber),
                'Dial ${helpline.alternatePhone} to reach this line.',
              ),
            ),
          ],
          if (helpline.email.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.sm),
            _ContactLink(
              icon: Icons.mail_rounded,
              label: helpline.email,
              caption: 'Email them',
              onTap: () => _launch(
                context,
                Uri(scheme: 'mailto', path: helpline.email),
                'Email ${helpline.email} to reach this service.',
              ),
            ),
          ],
          if (helpline.websiteUrl.trim().isNotEmpty) ...[
            const SizedBox(height: AppSpacing.sm),
            _ContactLink(
              icon: Icons.language_rounded,
              label: 'Visit their website',
              caption: null,
              onTap: () {
                final uri = Uri.tryParse(helpline.normalisedWebsiteUrl);
                if (uri == null) return;
                _launch(context, uri, 'Couldn\'t open that link.');
              },
            ),
          ],
        ],
      ),
    );
  }
}

/// A soft pill for a short fact, such as opening hours.
class _Chip extends StatelessWidget {
  const _Chip({required this.icon, required this.label});

  final IconData icon;
  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(
      horizontal: AppSpacing.md,
      vertical: AppSpacing.sm,
    ),
    decoration: BoxDecoration(
      color: AppColors.surface.withValues(alpha: 0.8),
      borderRadius: BorderRadius.circular(AppRadii.pill),
      border: Border.all(color: AppColors.outline),
    ),
    child: Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, size: 15, color: AppColors.muted),
        const SizedBox(width: AppSpacing.xs),
        Flexible(
          child: Text(
            label,
            style: Theme.of(
              context,
            ).textTheme.bodySmall?.copyWith(color: AppColors.muted),
          ),
        ),
      ],
    ),
  );
}

/// A full-width tappable contact row — used for the email address, alternate
/// number, and website so each one reads as something you can act on rather
/// than text to copy out by hand.
class _ContactLink extends StatelessWidget {
  const _ContactLink({
    required this.icon,
    required this.label,
    required this.caption,
    required this.onTap,
  });

  final IconData icon;
  final String label;
  final String? caption;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final theme = Theme.of(context);

    return Material(
      color: AppColors.surface.withValues(alpha: 0.8),
      borderRadius: BorderRadius.circular(AppRadii.input),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(AppRadii.input),
        child: Container(
          constraints: const BoxConstraints(minHeight: 52),
          padding: const EdgeInsets.symmetric(
            horizontal: AppSpacing.md,
            vertical: AppSpacing.sm,
          ),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadii.input),
            border: Border.all(color: AppColors.outline),
          ),
          child: Row(
            children: [
              Icon(icon, size: 20, color: AppColors.primary),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    if (caption case final caption?)
                      Text(
                        caption,
                        style: theme.textTheme.labelSmall?.copyWith(
                          color: AppColors.muted,
                        ),
                      ),
                    Text(
                      label,
                      style: theme.textTheme.titleSmall?.copyWith(
                        color: AppColors.primary,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
              ),
              Icon(
                Icons.arrow_outward_rounded,
                size: 16,
                color: AppColors.muted,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
