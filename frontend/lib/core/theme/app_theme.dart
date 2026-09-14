import 'package:flutter/material.dart';

// Compatibility helper for older screens. Headings now use the app text theme
// instead of mathematical Unicode glyphs.
String toMathBold(String text) => text;

abstract final class AppColors {
  static const primary = Color(0xFF7B5A93);
  static const secondary = Color(0xFF9E5C72);
  static const background = Color(0xFFFBF8FC);
  static const surface = Color(0xFFFFFCFF);
  static const ink = Color(0xFF3B3340);
  static const muted = Color(0xFF6A5F73);
  static const outline = Color(0xFFE6DAEE);
  static const softLavender = Color(0xFFF0E8F7);
  static const softBlush = Color(0xFFFBE4EA);
  static const softSage = Color(0xFFDFEFE7);
  static const softGold = Color(0xFFFFF0CF);
  static const softTeal = softSage;
  static const softPlum = softLavender;

  /// Far end of the stress hero's wash, which runs from [primary].
  ///
  /// Kept deep enough to carry white body text: this is the only filled
  /// surface in the app that light type sits on, so it cannot be softened as
  /// far as the rest of the palette without the copy on it going unreadable.
  static const heroWashEnd = Color(0xFF8A5C84);

  /// The hero's body copy — white warmed towards the wash beneath it.
  static const onHeroMuted = Color(0xFFF9EEF8);
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
  static const input = 16.0;
  static const card = 22.0;
  static const pill = 999.0;
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
      tertiary: Color(0xFF46705C),
      tertiaryContainer: AppColors.softSage,
      surface: AppColors.surface,
      onSurface: AppColors.ink,
      onSurfaceVariant: AppColors.muted,
      outline: AppColors.outline,
      outlineVariant: Color(0xFFEDE6EF),
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
        headlineMedium: base.headlineMedium?.copyWith(
          fontSize: 24,
          fontWeight: FontWeight.w800,
          letterSpacing: -0.7,
          height: 1.15,
        ),
        headlineSmall: base.headlineSmall?.copyWith(
          fontSize: 20,
          fontWeight: FontWeight.w800,
          letterSpacing: -0.4,
          height: 1.2,
        ),
        titleLarge: base.titleLarge?.copyWith(
          fontSize: 19,
          fontWeight: FontWeight.w800,
          letterSpacing: -0.2,
        ),
        titleMedium: base.titleMedium?.copyWith(
          fontSize: 15.5,
          fontWeight: FontWeight.w700,
        ),
        bodyLarge: base.bodyLarge?.copyWith(height: 1.5),
        bodyMedium: base.bodyMedium?.copyWith(height: 1.45),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.ink,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        centerTitle: false,
        titleTextStyle: TextStyle(
          color: AppColors.ink,
          fontSize: 18,
          fontWeight: FontWeight.w800,
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
          horizontal: 16,
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
          minimumSize: const Size(0, 52),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadii.input),
          ),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          minimumSize: const Size(0, 52),
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 14),
          side: const BorderSide(color: AppColors.outline),
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(AppRadii.input),
          ),
          textStyle: const TextStyle(fontSize: 15, fontWeight: FontWeight.w700),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 70,
        elevation: 0,
        backgroundColor: AppColors.surface,
        indicatorColor: AppColors.softLavender,
        labelTextStyle: WidgetStateProperty.resolveWith(
          (states) => TextStyle(
            color: states.contains(WidgetState.selected)
                ? AppColors.primary
                : AppColors.muted,
            fontSize: 12,
            fontWeight: states.contains(WidgetState.selected)
                ? FontWeight.w700
                : FontWeight.w600,
          ),
        ),
      ),
      dividerTheme: const DividerThemeData(color: AppColors.outline),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        backgroundColor: AppColors.ink,
        contentTextStyle: const TextStyle(color: Colors.white),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(14)),
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
