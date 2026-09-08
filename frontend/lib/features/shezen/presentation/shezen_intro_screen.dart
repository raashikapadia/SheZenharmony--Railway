import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

/// The Shezen preview.
///
/// Shezen is a rule-based chat buddy: every reply is written by the SheZen team
/// in the Web Admin and matched by keyword. Nothing here talks to a model or a
/// language API, and it never will — the preview below is a static illustration
/// of the conversation shape, not a live chat.
class ShezenIntroScreen extends StatefulWidget {
  const ShezenIntroScreen({super.key});

  @override
  State<ShezenIntroScreen> createState() => _ShezenIntroScreenState();
}

class _ShezenIntroScreenState extends State<ShezenIntroScreen>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 700),
  )..forward();

  late final Animation<double> _fade = CurvedAnimation(
    parent: _controller,
    curve: Curves.easeOut,
  );

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Shezen')),
    body: SafeArea(
      child: FadeTransition(
        opacity: _fade,
        child: ListView(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
          children: [
            const Center(child: ShezenAvatar(size: 96)),

            const SizedBox(height: AppSpacing.lg),

            Text(
              'Meet Shezen',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.headlineMedium,
            ),

            const SizedBox(height: AppSpacing.sm),

            const Text(
              'Shezen is here to give you a little encouragement, help you '
              'reflect, and point you toward things that might help.',
              textAlign: TextAlign.center,
              style: TextStyle(color: AppColors.muted, height: 1.5),
            ),

            const SizedBox(height: AppSpacing.lg),

            const Center(child: _ComingSoonBadge()),

            const SizedBox(height: AppSpacing.xxxl),

            Text(
              'Here is what it will look like',
              style: Theme.of(context).textTheme.titleSmall,
            ),

            const SizedBox(height: AppSpacing.md),

            // A still illustration of the conversation, not a working chat.
            const _ChatPreview(),

            const SizedBox(height: AppSpacing.xxl),

            Container(
              padding: const EdgeInsets.all(AppSpacing.lg),
              decoration: BoxDecoration(
                color: AppColors.softSage,
                borderRadius: BorderRadius.circular(AppRadii.card),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(Icons.info_outline_rounded, color: AppColors.primary),
                  SizedBox(width: AppSpacing.md),
                  Expanded(
                    child: Text(
                      'Shezen follows a set of replies written by the SheZen '
                      'wellbeing team. It is not a counsellor and cannot give '
                      'medical advice — for that, please talk to a person you '
                      'trust or a support service.',
                      style: TextStyle(color: AppColors.muted, height: 1.5),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

/// Shezen's face. A simple, friendly mark rather than an illustration asset, so
/// it stays crisp at any size and needs no bundled image.
class ShezenAvatar extends StatelessWidget {
  const ShezenAvatar({super.key, this.size = 48});

  final double size;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: const BoxDecoration(
      shape: BoxShape.circle,
      gradient: LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [AppColors.softLavender, AppColors.softBlush],
      ),
    ),
    alignment: Alignment.center,
    child: Icon(
      Icons.chat_bubble_rounded,
      size: size * 0.44,
      color: AppColors.primary,
    ),
  );
}

class _ComingSoonBadge extends StatelessWidget {
  const _ComingSoonBadge();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 7),
    decoration: BoxDecoration(
      color: AppColors.softGold,
      borderRadius: BorderRadius.circular(AppRadii.pill),
    ),
    child: const Text(
      'COMING SOON',
      style: TextStyle(
        fontSize: 12,
        fontWeight: FontWeight.w800,
        letterSpacing: 1.1,
        color: AppColors.ink,
      ),
    ),
  );
}

/// A static picture of a Shezen conversation. Nothing here is interactive.
class _ChatPreview extends StatelessWidget {
  const _ChatPreview();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(AppSpacing.lg),
    decoration: BoxDecoration(
      color: AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadii.card),
      border: Border.all(color: AppColors.outline),
    ),
    child: const Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        _Bubble(
          text: 'Hi, I\'m Shezen. How are you feeling today?',
          fromShezen: true,
        ),
        SizedBox(height: AppSpacing.sm),
        _Bubble(text: 'A bit stressed, honestly.', fromShezen: false),
        SizedBox(height: AppSpacing.sm),
        _Bubble(
          text:
              'Sounds like things are feeling a bit heavy right now. '
              'Let\'s keep it simple — what sounds best?',
          fromShezen: true,
        ),
        SizedBox(height: AppSpacing.md),
        Wrap(
          spacing: AppSpacing.sm,
          runSpacing: AppSpacing.sm,
          children: [
            _QuickReplyChip(label: 'Take a breathing break'),
            _QuickReplyChip(label: 'Give me a quick tip'),
            _QuickReplyChip(label: 'Something distracting'),
          ],
        ),
      ],
    ),
  );
}

class _Bubble extends StatelessWidget {
  const _Bubble({required this.text, required this.fromShezen});

  final String text;
  final bool fromShezen;

  @override
  Widget build(BuildContext context) => Align(
    alignment: fromShezen ? Alignment.centerLeft : Alignment.centerRight,
    child: Container(
      constraints: const BoxConstraints(maxWidth: 260),
      padding: const EdgeInsets.symmetric(
        horizontal: AppSpacing.lg,
        vertical: AppSpacing.md,
      ),
      decoration: BoxDecoration(
        color: fromShezen ? AppColors.softLavender : AppColors.softSage,
        borderRadius: BorderRadius.only(
          topLeft: const Radius.circular(18),
          topRight: const Radius.circular(18),
          bottomLeft: Radius.circular(fromShezen ? 4 : 18),
          bottomRight: Radius.circular(fromShezen ? 18 : 4),
        ),
      ),
      child: Text(text, style: const TextStyle(height: 1.4)),
    ),
  );
}

class _QuickReplyChip extends StatelessWidget {
  const _QuickReplyChip({required this.label});

  final String label;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 8),
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(AppRadii.pill),
      border: Border.all(color: AppColors.primary.withValues(alpha: .35)),
    ),
    child: Text(
      label,
      style: const TextStyle(fontSize: 13, color: AppColors.primary),
    ),
  );
}
