import 'package:flutter/material.dart';

import '../data/assessment_result.dart';

class AssessmentResultScreen extends StatelessWidget {
  const AssessmentResultScreen({super.key, required this.result});

  final AssessmentResult result;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Scaffold(
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Icon(Icons.spa_outlined, size: 56, color: colors.primary),
              const SizedBox(height: 24),
              Text(
                'Your Stress Level',
                style: Theme.of(context).textTheme.titleMedium?.copyWith(color: colors.onSurfaceVariant),
              ),
              const SizedBox(height: 8),
              Text(
                result.bandLabel,
                style: Theme.of(context).textTheme.displaySmall?.copyWith(fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 24),
              Card(
                color: colors.surfaceContainerHighest,
                child: Padding(
                  padding: const EdgeInsets.all(20),
                  child: Column(
                    children: [
                      Text('Your Score', style: Theme.of(context).textTheme.labelLarge),
                      const SizedBox(height: 4),
                      Text('${result.totalScore}', style: Theme.of(context).textTheme.headlineMedium),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: 20),
              Text(
                'This is a stress check-in, not a medical diagnosis. It reflects how you\'ve '
                'been feeling based on your answers today — it can change over time, and you '
                'can check in again whenever you\'d like.',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.bodyMedium?.copyWith(color: colors.onSurfaceVariant),
              ),
              const SizedBox(height: 32),
              SizedBox(
                width: double.infinity,
                child: FilledButton(
                  onPressed: () => Navigator.of(context).pop(),
                  child: const Text('Continue'),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
