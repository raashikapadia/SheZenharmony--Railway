import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

/// Shared category vocabulary for the wellbeing activity screens.
///
/// The hub, the searchable collections, and the activity header all label the
/// same categories, so the icon and wash for a category live here rather than
/// being restated on each screen.
abstract final class WellbeingPalette {
  /// Extra pastels that sit alongside the theme's washes, so neighbouring
  /// category tiles stay distinguishable without leaving the palette.
  static const softSky = Color(0xFFE7F0F6);
  static const softPetal = Color(0xFFF6EBF3);

  static IconData iconFor(String category) =>
      switch (category.trim().toLowerCase()) {
        'breathing' => Icons.air_rounded,
        'grounding' => Icons.spa_rounded,
        'meditation' || 'mindfulness' => Icons.self_improvement_rounded,
        'relaxation' => Icons.bedtime_rounded,
        'yoga' => Icons.self_improvement_rounded,
        'asmr' => Icons.headphones_rounded,
        'journaling' => Icons.edit_note_rounded,
        'resource' => Icons.menu_book_rounded,
        _ => Icons.favorite_rounded,
      };

  static Color tintFor(String category) =>
      switch (category.trim().toLowerCase()) {
        'breathing' => AppColors.softSage,
        'grounding' => softSky,
        'meditation' || 'mindfulness' => AppColors.softLavender,
        'relaxation' => AppColors.softBlush,
        'yoga' => softPetal,
        'asmr' => softSky,
        'journaling' => AppColors.softGold,
        'resource' => AppColors.softGold,
        _ => AppColors.softLavender,
      };
}

/// A wash of colour that fades out at its own edge. A radial gradient rather
/// than a flat circle: a hard rim reads as a shape sitting on the header
/// instead of light falling across it.
class WellbeingBlob extends StatelessWidget {
  const WellbeingBlob({super.key, required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) => Container(
    width: size,
    height: size,
    decoration: BoxDecoration(
      shape: BoxShape.circle,
      gradient: RadialGradient(
        colors: [color, color.withValues(alpha: 0)],
        stops: const [0.35, 1],
      ),
    ),
  );
}

class WellbeingSparkle extends StatelessWidget {
  const WellbeingSparkle({super.key, required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) =>
      Icon(Icons.auto_awesome_rounded, size: size, color: color);
}

/// An icon on a white squircle with a tilted twin peeking out behind it.
///
/// The signature shape of the activity headers; shared so the hub opens with
/// the same motif the detail screens close with.
class WellbeingIconTile extends StatelessWidget {
  const WellbeingIconTile({
    super.key,
    required this.icon,
    this.size = 84,
    this.accent = AppColors.primary,
  });

  final IconData icon;
  final double size;
  final Color accent;

  @override
  Widget build(BuildContext context) => SizedBox(
    // Room for the tilted twin to show past the corners of the front tile.
    width: size * 1.24,
    height: size * 1.1,
    child: Stack(
      alignment: Alignment.center,
      children: [
        Transform.rotate(
          angle: 0.28,
          child: Container(
            width: size,
            height: size,
            decoration: BoxDecoration(
              color: accent.withValues(alpha: 0.28),
              borderRadius: BorderRadius.circular(size / 3),
            ),
          ),
        ),
        Container(
          width: size,
          height: size,
          decoration: BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.circular(size / 3),
            boxShadow: [
              BoxShadow(
                color: accent.withValues(alpha: 0.16),
                blurRadius: 16,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Icon(icon, size: size * 0.45, color: accent),
        ),
      ],
    ),
  );
}
