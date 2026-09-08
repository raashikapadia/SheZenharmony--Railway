import 'dart:async';
import 'dart:math';

import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

class MindfulSparkScreen extends StatefulWidget {
  const MindfulSparkScreen({super.key});

  @override
  State<MindfulSparkScreen> createState() => _MindfulSparkScreenState();
}

class _MindfulSparkScreenState extends State<MindfulSparkScreen> {
  final Random _random = Random();

  final List<_Spark> _sparks = [];

  Timer? _sparkTimer;

  int _tapped = 0;
  int _nextId = 0;

  @override
  void initState() {
    super.initState();

    // Add the first spark shortly after opening.
    Future.delayed(const Duration(milliseconds: 300), () {
      if (mounted) {
        _addSpark();
      }
    });

    // Continue adding sparks every 1.4 seconds.
    _sparkTimer = Timer.periodic(const Duration(milliseconds: 1400), (_) {
      if (mounted) {
        _addSpark();
      }
    });
  }

  void _addSpark() {
    final int id = _nextId++;

    // Keep sparks away from the very edges.
    final double x = 10 + _random.nextDouble() * 80;

    final double y = 10 + _random.nextDouble() * 70;

    final double size = 38 + _random.nextDouble() * 24;

    final _Spark spark = _Spark(id: id, x: x, y: y, size: size);

    setState(() {
      _sparks.add(spark);
    });

    // Fade the spark out after a short period.
    Future.delayed(const Duration(milliseconds: 2200), () {
      if (!mounted) return;

      final index = _sparks.indexWhere((item) => item.id == id);

      if (index == -1) return;

      setState(() {
        _sparks[index] = _sparks[index].copyWith(fadingOut: true);
      });

      // Remove it after the fade animation.
      Future.delayed(const Duration(milliseconds: 600), () {
        if (!mounted) return;

        setState(() {
          _sparks.removeWhere((item) => item.id == id);
        });
      });
    });
  }

  void _tapSpark(int id) {
    final index = _sparks.indexWhere((spark) => spark.id == id);

    if (index == -1) return;

    setState(() {
      _sparks.removeAt(index);
      _tapped++;
    });
  }

  @override
  void dispose() {
    _sparkTimer?.cancel();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Mindful Spark')),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(20, 12, 20, 24),
          child: Column(
            children: [
              // ------------------------------------------------
              // TITLE
              // ------------------------------------------------
              Text(
                'Mindful Spark',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w700,
                ),
              ),

              const SizedBox(height: 8),

              const Text(
                'Gently tap the sparks as they appear. '
                'There is no goal — just notice them.',
                textAlign: TextAlign.center,
                style: TextStyle(color: AppColors.muted, height: 1.45),
              ),

              const SizedBox(height: 20),

              // ------------------------------------------------
              // GAME AREA
              // ------------------------------------------------
              Expanded(
                child: Container(
                  width: double.infinity,
                  decoration: BoxDecoration(
                    color: AppColors.surface,
                    borderRadius: BorderRadius.circular(28),
                    border: Border.all(
                      color: AppColors.primary.withValues(alpha: 0.15),
                    ),
                  ),
                  clipBehavior: Clip.antiAlias,
                  child: LayoutBuilder(
                    builder: (context, constraints) {
                      return Stack(
                        children: [
                          // Background message
                          if (_sparks.isEmpty)
                            const Center(
                              child: Column(
                                mainAxisSize: MainAxisSize.min,
                                children: [
                                  Icon(
                                    Icons.auto_awesome_rounded,
                                    size: 42,
                                    color: AppColors.primary,
                                  ),
                                  SizedBox(height: 12),
                                  Text(
                                    'Watch for a spark',
                                    style: TextStyle(
                                      fontWeight: FontWeight.w700,
                                    ),
                                  ),
                                  SizedBox(height: 6),
                                  Text(
                                    'Tap it when you notice it.',
                                    style: TextStyle(color: AppColors.muted),
                                  ),
                                ],
                              ),
                            ),

                          // Sparks
                          for (final spark in _sparks)
                            _SparkWidget(
                              key: ValueKey(spark.id),
                              spark: spark,
                              onTap: () => _tapSpark(spark.id),
                            ),
                        ],
                      );
                    },
                  ),
                ),
              ),

              const SizedBox(height: 18),

              // ------------------------------------------------
              // SPARK COUNTER
              // ------------------------------------------------
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 18,
                  vertical: 12,
                ),
                decoration: BoxDecoration(
                  color: AppColors.softLavender,
                  borderRadius: BorderRadius.circular(20),
                ),
                child: Row(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.auto_awesome_rounded,
                      size: 20,
                      color: AppColors.primary,
                    ),
                    const SizedBox(width: 8),
                    Text(
                      'Sparks noticed: $_tapped',
                      style: const TextStyle(
                        fontWeight: FontWeight.w600,
                        color: AppColors.primary,
                      ),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 16),

              // ------------------------------------------------
              // DONE BUTTON
              // ------------------------------------------------
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: () {
                    Navigator.of(context).pop();
                  },
                  child: const Padding(
                    padding: EdgeInsets.symmetric(vertical: 13),
                    child: Text(
                      'Done',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ==========================================================
// SPARK MODEL
// ==========================================================

class _Spark {
  const _Spark({
    required this.id,
    required this.x,
    required this.y,
    required this.size,
    this.fadingOut = false,
  });

  final int id;

  // Position as a percentage.
  final double x;
  final double y;

  final double size;

  final bool fadingOut;

  _Spark copyWith({
    int? id,
    double? x,
    double? y,
    double? size,
    bool? fadingOut,
  }) {
    return _Spark(
      id: id ?? this.id,
      x: x ?? this.x,
      y: y ?? this.y,
      size: size ?? this.size,
      fadingOut: fadingOut ?? this.fadingOut,
    );
  }
}

// ==========================================================
// SPARK WIDGET
// ==========================================================

class _SparkWidget extends StatefulWidget {
  const _SparkWidget({super.key, required this.spark, required this.onTap});

  final _Spark spark;
  final VoidCallback onTap;

  @override
  State<_SparkWidget> createState() => _SparkWidgetState();
}

class _SparkWidgetState extends State<_SparkWidget>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  late final Animation<double> _fadeAnimation;

  late final Animation<double> _scaleAnimation;

  @override
  void initState() {
    super.initState();

    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 500),
    );

    _fadeAnimation = CurvedAnimation(
      parent: _controller,
      curve: Curves.easeOut,
    );

    _scaleAnimation = Tween<double>(
      begin: 0.65,
      end: 1.0,
    ).animate(CurvedAnimation(parent: _controller, curve: Curves.easeOutBack));

    _controller.forward();
  }

  @override
  void didUpdateWidget(covariant _SparkWidget oldWidget) {
    super.didUpdateWidget(oldWidget);

    if (widget.spark.fadingOut && !oldWidget.spark.fadingOut) {
      _controller.reverse();
    }
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Positioned(
      left: 0,
      top: 0,
      right: 0,
      bottom: 0,
      child: Align(
        alignment: Alignment(
          (widget.spark.x / 50) - 1,
          (widget.spark.y / 50) - 1,
        ),
        child: AnimatedBuilder(
          animation: _controller,
          builder: (context, child) {
            return Transform.scale(
              scale: _scaleAnimation.value,
              child: Opacity(opacity: _fadeAnimation.value, child: child),
            );
          },
          child: GestureDetector(
            onTap: widget.onTap,
            child: _SparkCircle(size: widget.spark.size),
          ),
        ),
      ),
    );
  }
}

// ==========================================================
// SPARK CIRCLE
// ==========================================================

class _SparkCircle extends StatelessWidget {
  const _SparkCircle({required this.size});

  final double size;

  @override
  Widget build(BuildContext context) {
    return SizedBox(
      width: size + 24,
      height: size + 24,
      child: Center(
        child: Container(
          width: size,
          height: size,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: AppColors.softLavender,
            border: Border.all(
              color: AppColors.primary.withValues(alpha: 0.45),
              width: 2,
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: 0.20),
                blurRadius: 18,
                spreadRadius: 4,
              ),
            ],
          ),
          child: const Icon(
            Icons.auto_awesome_rounded,
            color: AppColors.primary,
            size: 24,
          ),
        ),
      ),
    );
  }
}
