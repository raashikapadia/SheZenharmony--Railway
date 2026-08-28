import 'package:flutter/material.dart';

String toMathBold(String text) {
  return text.runes.map((rune) {
    if (rune >= 0x41 && rune <= 0x5A) {
      return String.fromCharCode(0x1D400 + rune - 0x41);
    }
    if (rune >= 0x61 && rune <= 0x7A) {
      return String.fromCharCode(0x1D41A + rune - 0x61);
    }
    if (rune >= 0x30 && rune <= 0x39) {
      return String.fromCharCode(0x1D7CE + rune - 0x30);
    }
    return String.fromCharCode(rune);
  }).join();
}

abstract final class AppColors {
  static const primary = Color(0xFF7042A3);
  static const secondary = Color(0xFF9A66C4);
  static const background = Color(0xFFF8F4FC);
  static const surface = Color(0xFFFFFEFF);
  static const ink = Color(0xFF321C4D);
  static const muted = Color(0xFF725F80);
  static const outline = Color(0xFFDCCCE8);
  static const softTeal = Color(0xFFF0E5FA);
  static const softPlum = Color(0xFFF3E8FA);
  static const softGold = Color(0xFFF8EFFC);
}

abstract final class AppSpacing {
  static const xs = 4.0;
  static const sm = 8.0;
  static const md = 12.0;
  static const lg = 16.0;
  static const xl = 20.0;
  static const xxl = 24.0;
  static const page = 20.0;
}

abstract final class AppRadii {
  static const input = 14.0;
  static const card = 20.0;
  static const pill = 999.0;
}

abstract final class AppTheme {
  static ThemeData get light {
    final scheme = ColorScheme.fromSeed(
      seedColor: AppColors.primary,
      brightness: Brightness.light,
      primary: AppColors.primary,
      secondary: AppColors.secondary,
      surface: AppColors.surface,
    );
    final textTheme = Typography.material2021().black.apply(
      bodyColor: AppColors.ink,
      displayColor: AppColors.ink,
    );

    return ThemeData(
      useMaterial3: true,
      colorScheme: scheme,
      scaffoldBackgroundColor: Colors.transparent,
      textTheme: textTheme.copyWith(
        headlineMedium: textTheme.headlineMedium?.copyWith(
          fontFamily: 'Cambria Math',
          fontWeight: FontWeight.w800,
          letterSpacing: -0.6,
          decoration: TextDecoration.underline,
        ),
        headlineSmall: textTheme.headlineSmall?.copyWith(
          fontFamily: 'Cambria Math',
          fontWeight: FontWeight.w800,
          letterSpacing: -0.35,
          decoration: TextDecoration.underline,
        ),
        titleLarge: textTheme.titleLarge?.copyWith(
          fontFamily: 'Cambria Math',
          fontWeight: FontWeight.w800,
          decoration: TextDecoration.underline,
        ),
        titleMedium: textTheme.titleMedium?.copyWith(
          fontFamily: 'Cambria Math',
          fontWeight: FontWeight.w700,
          decoration: TextDecoration.underline,
        ),
        bodyLarge: textTheme.bodyLarge?.copyWith(height: 1.45),
        bodyMedium: textTheme.bodyMedium?.copyWith(height: 1.45),
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: Colors.transparent,
        foregroundColor: AppColors.ink,
        surfaceTintColor: Colors.transparent,
        centerTitle: false,
        elevation: 0,
        titleTextStyle: TextStyle(
          color: AppColors.ink,
          fontSize: 20,
          fontFamily: 'Cambria Math',
          fontWeight: FontWeight.w800,
          decoration: TextDecoration.underline,
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
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          textStyle: const TextStyle(fontWeight: FontWeight.w700),
        ),
      ),
      navigationBarTheme: NavigationBarThemeData(
        height: 72,
        elevation: 0,
        backgroundColor: AppColors.surface.withValues(alpha: 0.94),
        indicatorColor: AppColors.softTeal,
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
        linearTrackColor: AppColors.softTeal,
      ),
    );
  }

  static OutlineInputBorder _inputBorder(Color color, {double width = 1}) {
    return OutlineInputBorder(
      borderRadius: BorderRadius.circular(AppRadii.input),
      borderSide: BorderSide(color: color, width: width),
    );
  }
}
