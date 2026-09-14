import 'package:flutter/material.dart';

// Compatibility helper for older screens. Headings now use the app text theme
// instead of mathematical Unicode glyphs.
String toMathBold(String text) => text;

/// The SheZen palette: a calm cream ground with colourful, distinct
/// experiences laid on top of it.
///
/// The whole system rests on one division, and every colour below is placed by
/// it. Colour carries *joy*; two dark tones carry *meaning*:
///
///  * **Saturated colour is surface and illustration, never text.** Blush,
///    coral, peach, sage, sky and orchid all sit below 3.5:1 on cream. They
///    are what makes the app feel alive — as fills, blooms, petals and
///    sparkles — and they are illegible as type, so they never carry any.
///  * **[primary] and [ink] carry everything that must be read.** Deep purple
///    reaches 7.7:1 on cream and holds white text at 8.0:1; deep plum reaches
///    11.7:1. Between them they take every heading, label and paragraph, which
///    is what lets the rest of the palette be as bright as it likes.
///
/// So the app is not muted — the *type* is, and only the type.
abstract final class AppColors {
  /// Deep SheZen purple. The working brand colour: headings, buttons, active
  /// states, and any fill that has to hold white text.
  static const primary = Color(0xFF6B3F7A);

  /// The logo's signature mauve. Decorative — icons, large display type,
  /// hairlines, line art. Never small text, never behind white text.
  static const brand = Color(0xFFA66F91);

  /// Vibrant orchid. The lift in the palette: gradients, illustration, and the
  /// warm end of the stress wash. Large text and icons at most.
  static const orchid = Color(0xFFB86AC9);

  /// The warm end of the stress-check wash. Deliberately orchid-leaning rather
  /// than near-black, so a check-in reads as an invitation rather than a
  /// warning, while white body copy still clears AA (5.1:1).
  static const primaryDeep = Color(0xFF9455A3);

  static const secondary = Color(0xFFA64C6E);

  /// Page ground. Warm cream — the calm the colour sits on.
  static const background = Color(0xFFFFF9F5);
  static const surface = Color(0xFFFFFDFB);

  /// Deep plum. Body copy, 11.7:1 on cream.
  static const ink = Color(0xFF403042);

  /// Secondary copy. Still AA (6.1:1); quiet without being faint.
  static const muted = Color(0xFF6B5A6E);

  static const outline = Color(0xFFEEE2EC);

  // ---- Accents. Illustration and small flourishes; see the class doc. ----

  static const blushPink = Color(0xFFE8A1B5);
  static const coral = Color(0xFFF2A38F);
  static const peach = Color(0xFFF6C58B);
  static const sage = Color(0xFFA9C59D);
  static const sky = Color(0xFFA8CDE0);
  static const lilac = Color(0xFFC9A7D9);
  static const blush = blushPink;

  // ---- Surface tints. Card and panel fills. Every one of these holds both
  // ink and primary at AA. ----

  static const softBlush = Color(0xFFFDEEF2);
  static const softLavender = Color(0xFFF7E7FA);
  static const softCoral = Color(0xFFFDE9E2);
  static const softPeach = Color(0xFFFDF1DF);
  static const softSage = Color(0xFFEAF3E6);
  static const softSky = Color(0xFFE6F1F7);

  // Older names kept so screens that predate this palette keep compiling and
  // pick the new identity up on their own.
  static const softGold = softPeach;
  static const softTeal = softSage;
  static const softPlum = softLavender;

  /// Far end of the stress hero's wash, which runs from [primary].
  static const heroWashEnd = primaryDeep;

  /// The hero's body copy — white warmed towards the wash beneath it.
  static const onHeroMuted = Color(0xFFFBEFF8);
}

/// The colour personality of one area of the app.
///
/// Each experience gets its own mood so a student can tell where she is by
/// colour alone, and so the app reads as several bright places rather than one
/// long pastel wash. The parts are fixed by role, which is what keeps six
/// palettes from turning into noise:
///
///  * [tint] fills the card — always pale enough for [AppColors.ink] at AA;
///  * [accent] is illustration and the icon — saturated, never text;
///  * [deep] is the one tone in the mood that may sit behind white text.
class AppMood {
  const AppMood({required this.tint, required this.accent, required this.deep});

  final Color tint;
  final Color accent;
  final Color deep;

  /// The card wash: the mood's tint falling away to the surface, so the colour
  /// announces the section at the top and lets go before it reaches the copy.
  LinearGradient get cardGradient => LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [tint, AppColors.surface],
    stops: const [0, 0.85],
  );
}

/// The six experiences, each with its own colour personality.
abstract final class AppMoods {
  /// Personal Guidance — warm blush and coral.
  static const guidance = AppMood(
    tint: AppColors.softCoral,
    accent: AppColors.coral,
    deep: Color(0xFFB5563C),
  );

  /// Wellbeing Activities — lilac and orchid.
  static const activities = AppMood(
    tint: AppColors.softLavender,
    accent: AppColors.orchid,
    deep: AppColors.primary,
  );

  /// Positive Engagement — sage and fresh green, the playful corner.
  static const engagement = AppMood(
    tint: AppColors.softSage,
    accent: AppColors.sage,
    deep: Color(0xFF3F6B4A),
  );

  /// Stress Check — mauve and orchid under lavender moonlight.
  static const stress = AppMood(
    tint: AppColors.softLavender,
    accent: AppColors.lilac,
    deep: AppColors.primary,
  );

  /// ChatBuddy — pink and mauve.
  static const chat = AppMood(
    tint: AppColors.softBlush,
    accent: AppColors.blushPink,
    deep: AppColors.secondary,
  );

  /// Today's Inspiration — pink into peach.
  static const inspiration = AppMood(
    tint: AppColors.softBlush,
    accent: AppColors.peach,
    deep: AppColors.secondary,
  );
}

/// Type roles. Serif carries feeling, sans carries instruction.
///
/// [script] is for short emotional asides only — "You are enough" — never a
/// label, button or instruction. It resolves to an italic serif today; drop a
/// handwriting face into `assets/fonts/` and point this at it to upgrade the
/// accent without touching a single screen.
abstract final class AppFonts {
  static const serif = 'serif';
  static const script = 'serif';
}

abstract final class AppSpacing {
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 12.0;
  static const lg = 16.0;
  static const xl = 20.0;
  static const xxl = 24.0;
  static const xxxl = 32.0;
  static const page = 20.0;
}

abstract final class AppRadii {
  static const input = 18.0;
  static const card = 26.0;
  static const hero = 32.0;
  static const pill = 999.0;
}

/// Shadows are wide, faint and mauve-tinted rather than grey, so cards lift off
/// the ivory without a hard edge.
abstract final class AppShadows {
  static const soft = [
    BoxShadow(
      color: Color(0x0F6B4A72),
      blurRadius: 24,
      offset: Offset(0, 8),
      spreadRadius: -6,
    ),
  ];

  static const lifted = [
    BoxShadow(
      color: Color(0x1A6B4A72),
      blurRadius: 36,
      offset: Offset(0, 14),
      spreadRadius: -10,
    ),
  ];
}

abstract final class AppTheme {
  static ThemeData get light {
    const scheme = ColorScheme.light(
      primary: AppColors.primary,
      onPrimary: Colors.white,
      primaryContainer: AppColors.softLavender,
      onPrimaryContainer: AppColors.ink,
      secondary: AppColors.secondary,
      onSecondary: Colors.white,
      secondaryContainer: AppColors.softBlush,
      onSecondaryContainer: AppColors.ink,
      tertiary: Color(0xFF4A6B52),
      tertiaryContainer: AppColors.softSage,
      surface: AppColors.surface,
      onSurface: AppColors.ink,
      onSurfaceVariant: AppColors.muted,
      outline: AppColors.outline,
      outlineVariant: Color(0xFFF0E8F3),
      error: Color(0xFFB3261E),
      errorContainer: Color(0xFFFFDAD6),
    );
    final base = Typography.material2021().black.apply(
      bodyColor: AppColors.ink,
      displayColor: AppColors.ink,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: Colors.transparent,
      textTheme: base.copyWith(
        // Hero statements: serif, because the emotional lines are the ones a
        // student reads slowly.
        displaySmall: base.displaySmall?.copyWith(
          fontFamily: AppFonts.serif,
          fontSize: 30,
          fontWeight: FontWeight.w600,
          letterSpacing: -0.4,
          height: 1.18,
        ),
        headlineMedium: base.headlineMedium?.copyWith(
          fontFamily: AppFonts.serif,
          fontSize: 25,
          fontWeight: FontWeight.w600,
          letterSpacing: -0.3,
          height: 1.2,
        ),
        headlineSmall: base.headlineSmall?.copyWith(
          fontFamily: AppFonts.serif,
          fontSize: 21,
          fontWeight: FontWeight.w600,
          letterSpacing: -0.2,
          height: 1.25,
        ),
        // Titles stay sans: they label things rather than say them.
        titleLarge: base.titleLarge?.copyWith(
          fontSize: 18,
          fontWeight: FontWeight.w700,
          letterSpacing: -0.2,
        ),
        titleMedium: base.titleMedium?.copyWith(
          fontSize: 15.5,
          fontWeight: FontWeight.w700,
        ),
        titleSmall: base.titleSmall?.copyWith(
          fontSize: 13,
          fontWeight: FontWeight.w700,
        ),
        bodyLarge: base.bodyLarge?.copyWith(height: 1.5),
        bodyMedium: base.bodyMedium?.copyWith(height: 1.5),
        bodySmall: base.bodySmall?.copyWith(height: 1.45),
        labelLarge: base.labelLarge?.copyWith(fontWeight: FontWeight.w700),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.ink,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(
          color: AppColors.ink,
          fontFamily: AppFonts.serif,
          fontSize: 20,
          fontWeight: FontWeight.w600,
          letterSpacing: -0.2,
        ),
      ),
      cardTheme: const CardThemeData(
        elevation: 0,
        margin: EdgeInsets.zero,
        color: AppColors.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          side: BorderSide(color: AppColors.outline),
          borderRadius: BorderRadius.all(Radius.circular(AppRadii.card)),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: AppColors.surface,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 18,
          vertical: 16,
        ),
        labelStyle: const TextStyle(color: AppColors.muted),
        hintStyle: const TextStyle(color: AppColors.muted),
        errorMaxLines: 3,
        border: _inputBorder(AppColors.outline),
        enabledBorder: _inputBorder(AppColors.outline),
        focusedBorder: _inputBorder(AppColors.primary, width: 1.6),
        errorBorder: _inputBorder(scheme.error),
        focusedErrorBorder: _inputBorder(scheme.error, width: 1.6),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          minimumSize: const Size(0, 54),
          padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadii.pill),
          ),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(0, 54),
          padding: const EdgeInsets.symmetric(horizontal: 22, vertical: 14),
          side: const BorderSide(color: AppColors.outline),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadii.pill),
          ),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: AppColors.primary,
          textStyle: const TextStyle(fontSize: 14, fontWeight: FontWeight.w700),
        ),
      ),
      chipTheme: ChipThemeData(
        backgroundColor: AppColors.softLavender,
        selectedColor: AppColors.primary,
        side: const BorderSide(color: AppColors.outline),
        labelStyle: const TextStyle(
          color: AppColors.ink,
          fontSize: 13,
          fontWeight: FontWeight.w600,
        ),
        secondaryLabelStyle: const TextStyle(
          color: Colors.white,
          fontSize: 13,
          fontWeight: FontWeight.w700,
        ),
        shape: const StadiumBorder(),
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 68,
        elevation: 0,
        backgroundColor: Colors.transparent,
        surfaceTintColor: Colors.transparent,
        indicatorColor: AppColors.softBlush,
        indicatorShape: const StadiumBorder(),
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => TextStyle(
            color: states.contains(WidgetState.selected)
                ? AppColors.primary
                : AppColors.muted,
            fontSize: 11.5,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w700
                : FontWeight.w600,
          ),
        ),
        iconTheme: WidgetStateProperty.resolveWith(
          (states) => IconThemeData(
            size: 23,
            color: states.contains(WidgetState.selected)
                ? AppColors.primary
                : AppColors.muted,
          ),
        ),
      ),
      dividerTheme: const DividerThemeData(
        color: AppColors.outline,
        thickness: 1,
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: AppColors.ink,
        contentTextStyle: const TextStyle(color: Colors.white),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
      ),
      dialogTheme: const DialogThemeData(
        backgroundColor: AppColors.surface,
        surfaceTintColor: Colors.transparent,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.all(Radius.circular(AppRadii.card)),
        ),
      ),
      progressIndicatorTheme: const ProgressIndicatorThemeData(
        color: AppColors.primary,
        linearTrackColor: AppColors.softLavender,
      ),
    );
  }

  static OutlineInputBorder _inputBorder(Color color, {double width = 1}) =>
      OutlineInputBorder(
        borderRadius: BorderRadius.circular(AppRadii.input),
        borderSide: BorderSide(color: color, width: width),
      );
}
