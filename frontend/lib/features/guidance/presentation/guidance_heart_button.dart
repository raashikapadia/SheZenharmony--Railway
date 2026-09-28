import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';

/// The one bookmark interaction Personal Guidance uses everywhere it can be
/// saved: a heart that fills and springs slightly larger once tapped.
///
/// Originally lived only on the Home Page card; pulled out here so the Tips
/// and Quotes tabs use the exact same look and feel rather than a second,
/// slightly different heart button.
class GuidanceHeartButton extends StatelessWidget {
  const GuidanceHeartButton({
    super.key,
    required this.isFavourite,
    required this.onTap,
  });

  final bool isFavourite;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => IconButton(
    onPressed: onTap,
    tooltip: isFavourite ? 'Remove from saved' : 'Save this',
    icon: AnimatedScale(
      scale: isFavourite ? 1.15 : 1,
      duration: const Duration(milliseconds: 180),
      curve: Curves.easeOutBack,
      child: Icon(
        isFavourite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
        color: AppColors.secondary,
      ),
    ),
  );
}
