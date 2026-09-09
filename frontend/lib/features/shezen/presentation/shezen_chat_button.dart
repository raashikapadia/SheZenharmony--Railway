import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import 'shezen_intro_screen.dart';

/// Shezen as a floating companion above the bottom bar, next to Profile.
///
/// Deliberately not a navigation destination: it is a helper the student can
/// reach from anywhere in the shell, not a section of the app. The label keeps
/// it recognisable for anyone who has not met the avatar yet, and the solid
/// surface behind it keeps the text readable over whatever is scrolling past.
class ShezenChatButton extends StatelessWidget {
  const ShezenChatButton({super.key});

  @override
  Widget build(BuildContext context) => Semantics(
    button: true,
    label: 'Open ChatBuddy, your Shezen chat companion',
    child: Material(
      color: AppColors.surface,
      elevation: 3,
      shadowColor: const Color(0x3376517B),
      borderRadius: BorderRadius.circular(AppRadii.pill),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadii.pill),
        onTap: () => Navigator.of(
          context,
        ).push(MaterialPageRoute(builder: (_) => const ShezenIntroScreen())),
        child: Padding(
          padding: const EdgeInsets.fromLTRB(6, 6, 14, 6),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: const [
              ShezenAvatar(size: 36),
              SizedBox(width: AppSpacing.sm),
              Text(
                'ChatBuddy',
                style: TextStyle(
                  color: AppColors.primary,
                  fontWeight: FontWeight.w700,
                  fontSize: 13,
                ),
              ),
            ],
          ),
        ),
      ),
    ),
  );
}
