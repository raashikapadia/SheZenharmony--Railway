import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import 'shezen_intro_screen.dart';

/// The Shezen entry point on the Home dashboard.
///
/// Tappable rather than disabled: it opens a preview so people can see what is
/// coming, instead of hitting a dead control.
class ShezenFeatureCard extends StatelessWidget {
  const ShezenFeatureCard({super.key});

  @override
  Widget build(BuildContext context) => Material(
    color: Colors.transparent,
    child: InkWell(
      borderRadius: BorderRadius.circular(28),
      onTap: () => Navigator.of(
        context,
      ).push(MaterialPageRoute(builder: (_) => const ShezenIntroScreen())),
      child: Ink(
        decoration: BoxDecoration(
          gradient: const LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [AppColors.softLavender, AppColors.softGold],
          ),
          borderRadius: BorderRadius.circular(28),
        ),
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Row(
          children: [
            const ShezenAvatar(size: 56),
            const SizedBox(width: AppSpacing.lg),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Text(
                        'Meet Shezen',
                        style: Theme.of(context).textTheme.titleMedium,
                      ),
                      const SizedBox(width: AppSpacing.sm),
                      Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 8,
                          vertical: 3,
                        ),
                        decoration: BoxDecoration(
                          color: AppColors.surface,
                          borderRadius: BorderRadius.circular(AppRadii.pill),
                        ),
                        child: const Text(
                          'COMING SOON',
                          style: TextStyle(
                            fontSize: 9,
                            fontWeight: FontWeight.w800,
                            letterSpacing: 0.9,
                            color: AppColors.primary,
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: AppSpacing.xs),
                  const Text(
                    'Your little chat buddy for the days you need someone to '
                    'talk to.',
                    style: TextStyle(color: AppColors.muted, height: 1.4),
                  ),
                ],
              ),
            ),
            const Icon(Icons.chevron_right_rounded),
          ],
        ),
      ),
    ),
  );
}
