import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/theme/app_theme.dart';

/// The artwork every screen sits on.
///
/// Three layers, bottom to top: the plum wash that shows through wherever the
/// artwork cannot reach, the artwork itself, and a veil.
///
/// The veil is the important part. The artwork is a photograph — bright
/// sparkles, dark ripples, high local contrast — and body text laid straight
/// over it is unreadable in patches, which is the opposite of what the rest of
/// the app is tuned for. Softening it to a wash keeps the mood while letting
/// the type stay legible, and it is stronger towards the bottom where the
/// scrolling content sits and lighter at the top where the artwork reads as
/// atmosphere behind the heading.
class AppBackground extends StatelessWidget {
  const AppBackground({super.key, required this.child});

  /// Replace this file to change the artwork; nothing else needs to move.
  static const artwork = 'assets/images/shezen_background.png';

  /// The plum wash, and what the veil tints the artwork towards.
  static const _wash = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [Color(0xFFFFFBFF), Color(0xFFF8F1F9), Color(0xFFF5F8F5)],
  );

  final Widget child;

  @override
  Widget build(BuildContext context) => Stack(
    fit: StackFit.expand,
    children: [
      const DecoratedBox(decoration: BoxDecoration(gradient: _wash)),

      // Cover rather than fill: the artwork is portrait, and letting it crop
      // keeps the ripples circular instead of stretching them into ovals on a
      // wide window.
      Image.asset(
        artwork,
        fit: BoxFit.cover,
        // A background is decoration. If the file is missing or unreadable the
        // app must still open, on the wash alone.
        errorBuilder: (context, error, stackTrace) => const SizedBox.shrink(),
      ),

      const DecoratedBox(
        decoration: BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topCenter,
            end: Alignment.bottomCenter,
            // Light enough that the water and blossoms still read. The
            // artwork is uniformly pale, so ink-on-artwork keeps its contrast;
            // what the veil is really for is knocking back the bright
            // sparkles, which are the only places type would get lost.
            colors: [Color(0x40FFFBFF), Color(0x70F8F1F9), Color(0x99FBF8FC)],
            stops: [0, 0.45, 1],
          ),
        ),
      ),

      child,
    ],
  );
}

class AppSectionHeader extends StatelessWidget {
  const AppSectionHeader({super.key, required this.title, this.subtitle});

  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(toMathBold(title), style: Theme.of(context).textTheme.titleLarge),
      if (subtitle != null) ...[
        const SizedBox(height: AppSpacing.xs),
        Text(
          subtitle!,
          style: Theme.of(
            context,
          ).textTheme.bodyMedium?.copyWith(color: AppColors.muted),
        ),
      ],
    ],
  );
}

class AppFeatureCard extends StatelessWidget {
  const AppFeatureCard({
    super.key,
    required this.icon,
    required this.title,
    required this.description,
    required this.onTap,
    this.tint = AppColors.softTeal,
    this.badge,
  });

  final IconData icon;
  final String title;
  final String description;
  final VoidCallback onTap;
  final Color tint;
  final String? badge;

  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      borderRadius: BorderRadius.circular(AppRadii.card),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Container(
                  width: 42,
                  height: 42,
                  decoration: BoxDecoration(
                    color: tint,
                    borderRadius: BorderRadius.circular(13),
                  ),
                  child: Icon(icon, color: AppColors.primary, size: 23),
                ),
                const Spacer(),
                if (badge != null)
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 4,
                    ),
                    decoration: BoxDecoration(
                      color: tint,
                      borderRadius: BorderRadius.circular(AppRadii.pill),
                    ),
                    child: Text(
                      badge!,
                      style: const TextStyle(
                        fontSize: 11,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: AppSpacing.md),
            Text(
              toMathBold(title),
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: AppSpacing.xs),
            Text(
              description,
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.muted,
                height: 1.35,
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

/// A primary way into one of the three Home content areas: tinted icon tile,
/// then the name over a short description, then a chevron.
///
/// Laid out along the row rather than down the card so the three read as one
/// set of equal choices, and so the icon does not push the words down the way
/// the stacked [AppFeatureCard] does.
class AppPathwayCard extends StatelessWidget {
  const AppPathwayCard({
    super.key,
    required this.icon,
    required this.title,
    required this.description,
    required this.onTap,
    this.tint = AppColors.softTeal,
  });

  final IconData icon;
  final String title;
  final String description;
  final VoidCallback onTap;
  final Color tint;

  @override
  Widget build(BuildContext context) => Material(
    color: AppColors.surface,
    clipBehavior: Clip.antiAlias,
    shape: RoundedRectangleBorder(
      side: const BorderSide(color: AppColors.outline),
      borderRadius: BorderRadius.circular(AppRadii.card),
    ),
    child: InkWell(
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        // Top-aligned so the three titles share a line when the cards sit side
        // by side and one description wraps further than the others.
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                color: tint,
                borderRadius: BorderRadius.circular(16),
              ),
              child: Icon(icon, color: AppColors.primary, size: 26),
            ),

            const SizedBox(width: AppSpacing.lg),

            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(title, style: Theme.of(context).textTheme.titleMedium),

                  const SizedBox(height: AppSpacing.xs),

                  // Wraps rather than truncating: these descriptions are the
                  // only place the sub-sections are named.
                  Text(
                    description,
                    style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                      color: AppColors.muted,
                      height: 1.35,
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(width: AppSpacing.sm),

            // Centred against the icon tile rather than the whole card, so it
            // stays level with the title however far the description wraps.
            const SizedBox(
              height: 52,
              child: Center(
                child: Icon(
                  Icons.chevron_right_rounded,
                  color: AppColors.muted,
                  size: 26,
                ),
              ),
            ),
          ],
        ),
      ),
    ),
  );
}

class AppStateView extends StatelessWidget {
  const AppStateView({
    super.key,
    required this.icon,
    required this.title,
    required this.message,
    this.actionLabel,
    this.onAction,
  });

  final IconData icon;
  final String title;
  final String message;
  final String? actionLabel;
  final VoidCallback? onAction;

  @override
  Widget build(BuildContext context) => Center(
    child: SingleChildScrollView(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          Container(
            width: 64,
            height: 64,
            decoration: const BoxDecoration(
              color: AppColors.softTeal,
              shape: BoxShape.circle,
            ),
            child: Icon(icon, size: 30, color: AppColors.primary),
          ),
          const SizedBox(height: AppSpacing.lg),
          Text(
            toMathBold(title),
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: AppSpacing.sm),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(
              context,
            ).textTheme.bodyMedium?.copyWith(color: AppColors.muted),
          ),
          if (onAction != null) ...[
            const SizedBox(height: AppSpacing.xl),
            FilledButton(onPressed: onAction, child: Text(actionLabel!)),
          ],
        ],
      ),
    ),
  );
}

class AppLoadingView extends StatelessWidget {
  const AppLoadingView({super.key, required this.message});

  final String message;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const CircularProgressIndicator(),
          const SizedBox(height: AppSpacing.lg),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: AppColors.muted),
          ),
        ],
      ),
    ),
  );
}

/// The SheZen ID as it is drawn on screen.
///
/// The identifier is `SZ-` followed by 32 hex characters — 35 monospace
/// characters, wider than a phone can show at a readable size. The ends are
/// the part a student recognises, so the middle is elided.
///
/// Presentation only: the stored and transmitted identifier is unchanged, and
/// the copy action on [AppIdentityCard] still yields the whole thing.
String shortShezenId(String id) {
  const prefix = 'SZ-';
  final trimmed = id.trim();
  final body = trimmed.startsWith(prefix)
      ? trimmed.substring(prefix.length)
      : trimmed;

  // Short enough to read whole, so eliding would only lose information.
  if (body.length <= 12) return trimmed;

  return '$prefix${body.substring(0, 4)}…${body.substring(body.length - 4)}';
}

class AppIdentityCard extends StatelessWidget {
  const AppIdentityCard({super.key, required this.shezenId});

  final String shezenId;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(AppSpacing.xl),
    decoration: BoxDecoration(
      color: AppColors.softLavender,
      borderRadius: BorderRadius.circular(AppRadii.card),
      border: Border.all(color: AppColors.outline),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const CircleAvatar(
          backgroundColor: AppColors.surface,
          child: Icon(Icons.shield_outlined, color: AppColors.primary),
        ),
        const SizedBox(width: AppSpacing.md),
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Your SheZen ID',
                style: Theme.of(context).textTheme.titleMedium,
              ),
              const SizedBox(height: AppSpacing.xs),
              Row(
                children: [
                  Flexible(
                    child: Text(
                      shortShezenId(shezenId),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        color: AppColors.primary,
                        fontFamily: 'monospace',
                        letterSpacing: 0.4,
                      ),
                    ),
                  ),
                  // The shortened form is for reading; this is how the whole
                  // identifier still leaves the screen.
                  _CopyShezenIdButton(shezenId: shezenId),
                ],
              ),
              const SizedBox(height: AppSpacing.sm),
              const Text(
                'SheZen uses this ID for your wellbeing journey instead of showing your university identity.',
                style: TextStyle(color: AppColors.muted, height: 1.4),
              ),
            ],
          ),
        ),
      ],
    ),
  );
}

/// Puts the whole SheZen ID on the clipboard, since the card only draws a
/// shortened form of it.
class _CopyShezenIdButton extends StatelessWidget {
  const _CopyShezenIdButton({required this.shezenId});

  final String shezenId;

  @override
  Widget build(BuildContext context) => IconButton(
    icon: const Icon(Icons.copy_rounded, size: 18),
    color: AppColors.primary,
    visualDensity: VisualDensity.compact,
    padding: EdgeInsets.zero,
    constraints: const BoxConstraints(minWidth: 32, minHeight: 32),
    tooltip: 'Copy your full SheZen ID',
    onPressed: shezenId.trim().isEmpty
        ? null
        : () async {
            final messenger = ScaffoldMessenger.of(context);
            await Clipboard.setData(ClipboardData(text: shezenId.trim()));
            messenger.showSnackBar(
              const SnackBar(content: Text('SheZen ID copied')),
            );
          },
  );
}
