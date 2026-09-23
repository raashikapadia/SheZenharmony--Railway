import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import 'shezen_chat_screen.dart';

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
      color: Colors.transparent,
      elevation: 3,
      shadowColor: const Color(0x3376517B),
      borderRadius: BorderRadius.circular(AppRadii.pill),
      child: InkWell(
        borderRadius: BorderRadius.circular(AppRadii.pill),
        onTap: () => Navigator.of(
          context,
        ).push(MaterialPageRoute(builder: (_) => const ShezenChatScreen())),
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(AppRadii.pill),
            gradient: const LinearGradient(
              begin: Alignment.topLeft,
              end: Alignment.bottomRight,
              colors: [AppColors.softBlush, Color(0xFFFBE3EE)],
            ),
            border: Border.all(color: AppColors.blushPink, width: 1.4),
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(7, 7, 16, 7),
            child: Row(
              mainAxisSize: MainAxisSize.min,
              children: [
                const ShezenAvatar(size: 34),
                const SizedBox(width: AppSpacing.md),
                Column(
                  mainAxisSize: MainAxisSize.min,
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: const [
                    Text(
                      'ChatBuddy',
                      style: TextStyle(
                        color: AppColors.primary,
                        fontWeight: FontWeight.w700,
                        fontSize: 13.5,
                        height: 1.15,
                      ),
                    ),
                    // The one place a companion is allowed to sound like one.
                    AppScriptAccent("I'm here for you  ♡", fontSize: 11.5),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
}
