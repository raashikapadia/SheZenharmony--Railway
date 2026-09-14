import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/theme/app_theme.dart';

/// The ground every screen sits on: warm cream, four blooms of colour in the
/// corners, and the photograph left as faint texture.
///
/// The ground is the calm and the content is the colour. Keeping the wash
/// cream rather than lilac is what stops the app reading as one long pastel
/// blur — it gives the blush, coral, lavender and sage cards something neutral
/// to be bright against. The blooms are what keep cream from being bland:
/// peach, orchid, coral and sky, each low enough that body text is never
/// reading against a tint.
///
/// The photograph is a whisper on top rather than the design itself, because a
/// wash of flat tints is something type can always be read against.
class AppBackground extends StatelessWidget {
  const AppBackground({super.key, required this.child});

  /// Replace this file to change the texture; nothing else needs to move.
  static const artwork = 'assets/images/shezen_background.png';

  static const _wash = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [
      Color(0xFFFFFCFA),
      AppColors.background,
      Color(0xFFFFF6F2),
      Color(0xFFFDF3F7),
    ],
    stops: [0, 0.34, 0.72, 1],
  );

  final Widget child;

  @override
  Widget build(BuildContext context) => Stack(
    fit: StackFit.expand,
    children: [
      const DecoratedBox(decoration: BoxDecoration(gradient: _wash)),

      // Four blooms in four different hues rather than two in one. The ground
      // stays cream and calm — none of them is above 22% — but the corners
      // carry warmth, so the app feels sunlit instead of merely pale.
      const Positioned(
        top: -150,
        right: -120,
        child: _Bloom(size: 400, color: Color(0x30F6C58B)),
      ),
      const Positioned(
        top: 90,
        left: -150,
        child: _Bloom(size: 320, color: Color(0x2CB86AC9)),
      ),
      const Positioned(
        bottom: -180,
        left: -130,
        child: _Bloom(size: 430, color: Color(0x30F2A38F)),
      ),
      const Positioned(
        bottom: 40,
        right: -160,
        child: _Bloom(size: 340, color: Color(0x24A8CDE0)),
      ),

      // Texture only. A background is decoration: if the file is missing or
      // unreadable the app must still open, on the wash alone.
      Opacity(
        opacity: 0.07,
        child: Image.asset(
          artwork,
          fit: BoxFit.cover,
          errorBuilder: (context, error, stackTrace) => const SizedBox.shrink(),
        ),
      ),

      child,
    ],
  );
}

/// A soft radial glow. Its own widget so the blur stays cheap and const.
class _Bloom extends StatelessWidget {
  const _Bloom({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) => IgnorePointer(
    child: Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: RadialGradient(colors: [color, color.withAlpha(0)]),
      ),
    ),
  );
}

/// The official SheZen logo.
///
/// Both assets are cut from the original artwork in `branding/` by
/// `branding/make_logo_assets.ps1`, with the cream ground keyed out so the
/// mark sits directly on the app's background. Rerun that script after
/// replacing the original; the launcher icons and splash marks come from the
/// same place (see the `flutter_launcher_icons` section of pubspec.yaml).
///
/// Should an asset ever be missing this renders [_LogoFallback] — a plain typographic
/// lockup, deliberately *not* an imitation of the real mark. Nothing here
/// redraws, recolours or substitutes the logo; the fallback exists only so the
/// app is never branded with a broken image box, and the real artwork
/// supersedes it the moment it lands.
enum SheZenLogoVariant {
  /// Roundel and name side by side. For headers and any other short row,
  /// where the full lockup's tagline would be too small to read.
  horizontal('assets/images/shezen_logo_wordmark.png'),

  /// The complete square lockup — mark over name over tagline. For the places
  /// with room to give it: sign-in, splash, about.
  full('assets/images/shezen_logo.png');

  const SheZenLogoVariant(this.asset);

  /// PNG in both cases: the mark has soft edges and an outer glow, which JPEG
  /// blocking would show against the cream ground.
  final String asset;
}

class SheZenLogo extends StatelessWidget {
  const SheZenLogo({
    super.key,
    this.height = 40,
    this.showWordmark = true,
    this.variant = SheZenLogoVariant.horizontal,
  });

  /// Each variant resolves to exactly one path. A list of candidate extensions
  /// costs a frame per miss before the fallback can appear, which flickers on
  /// launch and hides the lockup from a single-pump widget test — not worth
  /// saving a rename.
  final SheZenLogoVariant variant;

  final double height;

  /// False for tight spots — a splash mark or an app bar — where the drawn
  /// name would repeat a heading that is already on screen. Applies to the
  /// fallback only; supplied artwork is drawn as authored.
  final bool showWordmark;

  @override
  Widget build(BuildContext context) => Image.asset(
    variant.asset,
    height: height,
    fit: BoxFit.contain,
    errorBuilder: (context, error, stackTrace) => _LogoFallback(
      height: height,
      // The full variant stacks, matching the square artwork it stands in for.
      stacked: variant == SheZenLogoVariant.full,
      showWordmark: showWordmark,
    ),
  );
}

/// Stands in until the official artwork is added: the botanical roundel from
/// the logo, drawn as line art, beside the name.
class _LogoFallback extends StatelessWidget {
  const _LogoFallback({
    required this.height,
    required this.showWordmark,
    this.stacked = false,
  });

  final double height;
  final bool showWordmark;

  /// Mark above the name rather than beside it, for the square placements.
  final bool stacked;

  @override
  Widget build(BuildContext context) {
    // Stacked has the full height to share between mark and name; the row
    // gives the mark the full height and sets the name against it.
    final markSize = stacked ? height * 0.52 : height;

    final mark = Container(
      width: markSize,
      height: markSize,
      decoration: const BoxDecoration(
        shape: BoxShape.circle,
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [AppColors.softLavender, AppColors.softBlush],
        ),
      ),
      child: CustomPaint(
        painter: BotanicalSprigPainter(color: AppColors.brand),
      ),
    );

    if (!showWordmark) return mark;

    // Scaled off the mark rather than the box, so the name keeps its
    // proportion to the roundel in both arrangements.
    final nameSize = stacked ? height * 0.3 : height * 0.52;

    final name = Column(
      mainAxisSize: MainAxisSize.min,
      crossAxisAlignment: stacked
          ? CrossAxisAlignment.center
          : CrossAxisAlignment.start,
      children: [
        Text(
          'SheZen',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            fontFamily: AppFonts.serif,
            fontSize: nameSize,
            height: 1.05,
            fontWeight: FontWeight.w600,
            color: AppColors.primary,
            letterSpacing: -0.3,
          ),
        ),
        Text(
          'HARMONY',
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: TextStyle(
            fontSize: nameSize * 0.38,
            height: 1.3,
            fontWeight: FontWeight.w600,
            color: AppColors.brand,
            letterSpacing: nameSize * 0.17,
          ),
        ),
      ],
    );

    if (stacked) {
      return Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          mark,
          SizedBox(height: height * 0.1),
          name,
        ],
      );
    }

    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        mark,
        SizedBox(width: height * 0.24),
        // Flexible with single-line children: in a cramped header the name
        // ellipsises rather than overflowing the row it sits in.
        Flexible(child: name),
      ],
    );
  }
}

/// A leaf sprig in single-weight line, used as the decorative motif throughout.
///
/// Drawn rather than shipped as an image so it inherits any colour and scales
/// to any box without a second asset.
class BotanicalSprigPainter extends CustomPainter {
  const BotanicalSprigPainter({required this.color, this.opacity = 1});

  final Color color;
  final double opacity;

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;
    final stroke = Paint()
      ..style = PaintingStyle.stroke
      ..strokeCap = StrokeCap.round
      ..strokeWidth = (w * 0.022).clamp(1.0, 2.4)
      ..color = color.withValues(alpha: opacity);

    // The stem, a shallow S from bottom-left to top-right.
    final stem = Path()
      ..moveTo(w * 0.24, h * 0.82)
      ..cubicTo(w * 0.42, h * 0.68, w * 0.5, h * 0.46, w * 0.72, h * 0.2);
    canvas.drawPath(stem, stroke);

    // Leaves alternate down the stem, each a pair of mirrored arcs.
    const positions = [0.3, 0.48, 0.66];
    for (var i = 0; i < positions.length; i++) {
      final t = positions[i];
      final ox = w * (0.28 + t * 0.42);
      final oy = h * (0.76 - t * 0.62);
      final len = w * (0.2 - i * 0.03);
      final side = i.isEven ? 1.0 : -1.0;

      final leaf = Path()
        ..moveTo(ox, oy)
        ..quadraticBezierTo(
          ox + len * 0.5 * side,
          oy - len * 0.62,
          ox + len * side,
          oy - len * 0.16,
        )
        ..quadraticBezierTo(ox + len * 0.52 * side, oy + len * 0.16, ox, oy);
      canvas.drawPath(leaf, stroke);
    }
  }

  @override
  bool shouldRepaint(BotanicalSprigPainter old) =>
      old.color != color || old.opacity != opacity;
}

/// A five-petal blossom, filled rather than outlined so it carries colour.
///
/// The counterweight to the line-art sprig: where that one is quiet and
/// structural, this is the bit of joy. Petals are drawn from the centre so any
/// size works, and a lighter heart keeps it from reading as a flat sticker.
class BlossomPainter extends CustomPainter {
  const BlossomPainter({
    required this.color,
    this.opacity = 1,
    this.petals = 5,
    this.rotation = 0,
  });

  final Color color;
  final double opacity;
  final int petals;
  final double rotation;

  @override
  void paint(Canvas canvas, Size size) {
    final c = Offset(size.width / 2, size.height / 2);
    final r = size.shortestSide / 2;
    final petal = Paint()..color = color.withValues(alpha: opacity);

    canvas.save();
    canvas.translate(c.dx, c.dy);
    canvas.rotate(rotation);

    for (var i = 0; i < petals; i++) {
      canvas.save();
      canvas.rotate(i * 2 * 3.1415926535 / petals);
      // Each petal is an ellipse pushed out from the centre, which reads as a
      // soft rounded blossom rather than a star.
      canvas.drawOval(
        Rect.fromCenter(
          center: Offset(0, -r * 0.52),
          width: r * 0.72,
          height: r * 0.96,
        ),
        petal,
      );
      canvas.restore();
    }

    canvas.drawCircle(
      Offset.zero,
      r * 0.3,
      Paint()
        ..color = Color.lerp(
          color,
          const Color(0xFFFFFFFF),
          0.55,
        )!.withValues(alpha: opacity),
    );
    canvas.restore();
  }

  @override
  bool shouldRepaint(BlossomPainter old) =>
      old.color != color ||
      old.opacity != opacity ||
      old.petals != petals ||
      old.rotation != rotation;
}

/// A four-point sparkle. Small, and used sparingly — a couple per screen.
class SparklePainter extends CustomPainter {
  const SparklePainter({required this.color, this.opacity = 1});

  final Color color;
  final double opacity;

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;
    final paint = Paint()..color = color.withValues(alpha: opacity);

    // Concave sides, so it reads as a glint rather than a diamond.
    final path = Path()
      ..moveTo(w / 2, 0)
      ..quadraticBezierTo(w * 0.56, h * 0.44, w, h / 2)
      ..quadraticBezierTo(w * 0.56, h * 0.56, w / 2, h)
      ..quadraticBezierTo(w * 0.44, h * 0.56, 0, h / 2)
      ..quadraticBezierTo(w * 0.44, h * 0.44, w / 2, 0)
      ..close();
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(SparklePainter old) =>
      old.color != color || old.opacity != opacity;
}

/// A scatter of sparkles that breathe, for a moment worth celebrating.
///
/// The animation is a slow opacity and scale drift, nothing that moves across
/// the screen — the brief asks for delight that stays calm, and anything that
/// travels would pull attention off the words it is decorating.
class AppSparkleBurst extends StatefulWidget {
  const AppSparkleBurst({
    super.key,
    this.color = AppColors.peach,
    this.size = 74,
  });

  final Color color;
  final double size;

  @override
  State<AppSparkleBurst> createState() => _AppSparkleBurstState();
}

class _AppSparkleBurstState extends State<AppSparkleBurst>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(seconds: 3),
  )..repeat(reverse: true);

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  // Offsets are fractions of the box, with each sparkle on its own phase so
  // they shimmer out of step.
  static const _spots = [
    (Offset(0.10, 0.24), 13.0, 0.0),
    (Offset(0.78, 0.12), 9.0, 0.35),
    (Offset(0.56, 0.74), 11.0, 0.7),
  ];

  @override
  Widget build(BuildContext context) => IgnorePointer(
    child: SizedBox(
      width: widget.size,
      height: widget.size,
      child: AnimatedBuilder(
        animation: _controller,
        builder: (context, _) => Stack(
          children: [
            for (final (spot, dimension, phase) in _spots)
              Positioned(
                left: spot.dx * widget.size,
                top: spot.dy * widget.size,
                child: Opacity(
                  opacity: 0.35 + 0.55 * _wave(phase),
                  child: SizedBox(
                    width: dimension,
                    height: dimension,
                    child: CustomPaint(
                      painter: SparklePainter(color: widget.color),
                    ),
                  ),
                ),
              ),
          ],
        ),
      ),
    ),
  );

  /// A 0..1 triangle wave offset by [phase], so each sparkle peaks alone.
  double _wave(double phase) {
    final t = (_controller.value + phase) % 1.0;
    return t < 0.5 ? t * 2 : (1 - t) * 2;
  }
}

/// A small solid heart, for the quiet affectionate beats.
class HeartPainter extends CustomPainter {
  const HeartPainter({required this.color, this.opacity = 1});

  final Color color;
  final double opacity;

  @override
  void paint(Canvas canvas, Size size) {
    final w = size.width;
    final h = size.height;
    final paint = Paint()..color = color.withValues(alpha: opacity);

    final path = Path()
      ..moveTo(w / 2, h * 0.92)
      ..cubicTo(-w * 0.18, h * 0.52, w * 0.16, -h * 0.08, w / 2, h * 0.3)
      ..cubicTo(w * 0.84, -h * 0.08, w * 1.18, h * 0.52, w / 2, h * 0.92)
      ..close();
    canvas.drawPath(path, paint);
  }

  @override
  bool shouldRepaint(HeartPainter old) =>
      old.color != color || old.opacity != opacity;
}

/// A short emotional aside in the script face — "You are enough".
///
/// Deliberately narrow in purpose: the type system reserves script for feeling,
/// never for a label, button or instruction, so this widget carries no size or
/// weight knobs that would tempt it into that job.
class AppScriptAccent extends StatelessWidget {
  const AppScriptAccent(
    this.text, {
    super.key,
    this.color = AppColors.brand,
    this.fontSize = 15,
    this.textAlign,
  });

  final String text;
  final Color color;
  final double fontSize;
  final TextAlign? textAlign;

  @override
  Widget build(BuildContext context) => Text(
    text,
    textAlign: textAlign,
    style: TextStyle(
      fontFamily: AppFonts.script,
      fontStyle: FontStyle.italic,
      fontSize: fontSize,
      height: 1.35,
      color: color,
      letterSpacing: 0.2,
    ),
  );
}

/// A small capitalised label above a section — "TODAY'S INSPIRATION".
class AppEyebrow extends StatelessWidget {
  const AppEyebrow(this.text, {super.key, this.icon, this.color});

  final String text;
  final IconData? icon;
  final Color? color;

  @override
  Widget build(BuildContext context) {
    final tone = color ?? AppColors.primary;
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        if (icon != null) ...[
          Icon(icon, size: 15, color: tone),
          const SizedBox(width: AppSpacing.sm),
        ],
        Flexible(
          child: Text(
            text,
            style: TextStyle(
              color: tone,
              fontSize: 11.5,
              fontWeight: FontWeight.w800,
              letterSpacing: 1.3,
            ),
          ),
        ),
      ],
    );
  }
}

/// The standard panel: ivory surface, hairline, wide soft shadow, generous
/// radius. Everything that is not a tinted feature card is one of these.
class AppSoftCard extends StatelessWidget {
  const AppSoftCard({
    super.key,
    required this.child,
    this.padding = const EdgeInsets.all(AppSpacing.xl),
    this.color = AppColors.surface,
    this.gradient,
    this.onTap,
    this.radius = AppRadii.card,
    this.border = true,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final Color color;
  final Gradient? gradient;
  final VoidCallback? onTap;
  final double radius;
  final bool border;

  @override
  Widget build(BuildContext context) {
    final shape = BorderRadius.circular(radius);
    return DecoratedBox(
      decoration: BoxDecoration(
        borderRadius: shape,
        boxShadow: AppShadows.soft,
      ),
      child: Material(
        color: gradient == null ? color : Colors.transparent,
        borderRadius: shape,
        clipBehavior: Clip.antiAlias,
        child: Ink(
          decoration: BoxDecoration(
            gradient: gradient,
            borderRadius: shape,
            border: border ? Border.all(color: AppColors.outline) : null,
          ),
          child: InkWell(
            onTap: onTap,
            borderRadius: shape,
            child: Padding(padding: padding, child: child),
          ),
        ),
      ),
    );
  }
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
    this.mood,
  });

  final IconData icon;
  final String title;
  final String description;
  final VoidCallback onTap;

  /// Kept for callers that predate [mood]; ignored when a mood is given.
  final Color tint;

  /// The section's colour personality. Supplying it gives the card its own
  /// blossom, a coloured icon and a matching wash, which is what makes the
  /// three areas legible as three different places.
  final AppMood? mood;

  @override
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(AppRadii.card),
      boxShadow: AppShadows.soft,
    ),
    child: Material(
      // The tint washes down from the top so the card has its own identity
      // while the copy at the bottom still sits on something close to white.
      // Both ends clear AA against ink and deep mauve — see AppColors.
      color: AppColors.surface,
      clipBehavior: Clip.antiAlias,
      shape: RoundedRectangleBorder(
        side: const BorderSide(color: AppColors.outline),
        borderRadius: BorderRadius.circular(AppRadii.card),
      ),
      child: Ink(
        decoration: BoxDecoration(
          gradient:
              mood?.cardGradient ??
              LinearGradient(
                begin: Alignment.topLeft,
                end: Alignment.bottomRight,
                colors: [tint, AppColors.surface],
                stops: const [0, 0.85],
              ),
        ),
        child: Stack(
          children: [
            // The section's blossom, tucked into the corner and clipped by the
            // card. Faint enough that the description reads straight over it.
            if (mood != null)
              Positioned(
                right: -16,
                top: -18,
                width: 86,
                height: 86,
                child: IgnorePointer(
                  child: CustomPaint(
                    painter: BlossomPainter(
                      color: mood!.accent,
                      opacity: 0.34,
                      rotation: 0.4,
                    ),
                  ),
                ),
              ),

            InkWell(
              onTap: onTap,
              child: Padding(
                padding: const EdgeInsets.all(AppSpacing.lg),
                // Top-aligned so the three titles share a line when the cards
                // sit side by side and one description wraps further than the
                // others.
                child: Row(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Container(
                      width: 52,
                      height: 52,
                      decoration: BoxDecoration(
                        color: AppColors.surface,
                        borderRadius: BorderRadius.circular(18),
                        border: Border.all(
                          color:
                              mood?.accent.withValues(alpha: 0.5) ??
                              AppColors.outline,
                          width: mood == null ? 1 : 1.4,
                        ),
                      ),
                      child: Icon(
                        icon,
                        color: mood?.deep ?? AppColors.primary,
                        size: 25,
                      ),
                    ),

                    const SizedBox(width: AppSpacing.lg),

                    Expanded(
                      child: Column(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            title,
                            style: Theme.of(context).textTheme.titleMedium,
                          ),

                          const SizedBox(height: AppSpacing.xs),

                          // Wraps rather than truncating: these descriptions
                          // are the only place the sub-sections are named.
                          Text(
                            description,
                            style: Theme.of(context).textTheme.bodyMedium
                                ?.copyWith(color: AppColors.muted, height: 1.4),
                          ),
                        ],
                      ),
                    ),

                    const SizedBox(width: AppSpacing.sm),

                    // Centred against the icon tile rather than the whole
                    // card, so it stays level with the title however far the
                    // description wraps.
                    SizedBox(
                      height: 52,
                      child: Center(
                        child: Icon(
                          Icons.chevron_right_rounded,
                          color: mood?.deep ?? AppColors.primary,
                          size: 24,
                        ),
                      ),
                    ),
                  ],
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
