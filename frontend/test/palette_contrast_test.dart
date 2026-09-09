import 'dart:math' as math;

import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/core/theme/app_theme.dart';

/// The palette is bold on purpose, and this is what keeps it honest.
///
/// Every colour pair the app actually places text on is checked against the
/// WCAG AA threshold, so lightening a tint or softening the ink for aesthetic
/// reasons fails here instead of quietly making the app harder to read. A
/// wellbeing app is exactly the wrong place to trade legibility for mood.
void main() {
  /// Relative luminance, per WCAG 2.1.
  double luminance(Color color) {
    double channel(double v) =>
        v <= 0.03928 ? v / 12.92 : math.pow((v + 0.055) / 1.055, 2.4) as double;

    return 0.2126 * channel(color.r) +
        0.7152 * channel(color.g) +
        0.0722 * channel(color.b);
  }

  double contrast(Color a, Color b) {
    final la = luminance(a);
    final lb = luminance(b);
    final lighter = math.max(la, lb);
    final darker = math.min(la, lb);
    return (lighter + 0.05) / (darker + 0.05);
  }

  /// 4.5:1 is the AA threshold for body text.
  void expectReadable(Color foreground, Color background, String what) {
    final ratio = contrast(foreground, background);
    expect(
      ratio,
      greaterThanOrEqualTo(4.5),
      reason:
          '$what is ${ratio.toStringAsFixed(2)}:1, below the 4.5:1 AA floor',
    );
  }

  test('body and heading text is readable on every surface it lands on', () {
    for (final (surface, name) in const [
      (AppColors.surface, 'surface'),
      (AppColors.background, 'background'),
      (AppColors.softLavender, 'softLavender'),
      (AppColors.softBlush, 'softBlush'),
      (AppColors.softSage, 'softSage'),
      (AppColors.softGold, 'softGold'),
    ]) {
      expectReadable(AppColors.ink, surface, 'ink on $name');
      expectReadable(AppColors.muted, surface, 'muted on $name');
      expectReadable(AppColors.primary, surface, 'primary on $name');
    }
  });

  test('text on the filled brand colours is readable', () {
    expectReadable(Colors.white, AppColors.primary, 'white on primary');
    expectReadable(Colors.white, AppColors.secondary, 'white on secondary');
  });

  test('the theme keeps its scheme in step with the palette', () {
    final scheme = AppTheme.light.colorScheme;

    expect(scheme.primary, AppColors.primary);
    expect(scheme.secondary, AppColors.secondary);
    expect(scheme.onSurface, AppColors.ink);

    expectReadable(scheme.onPrimary, scheme.primary, 'onPrimary on primary');
    expectReadable(
      scheme.onSecondary,
      scheme.secondary,
      'onSecondary on secondary',
    );
  });
}
