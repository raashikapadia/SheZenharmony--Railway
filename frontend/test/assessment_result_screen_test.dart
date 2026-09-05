import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/features/assessment/data/assessment_result.dart';
import 'package:shezen_harmony/features/assessment/presentation/assessment_result_screen.dart';

void main() {
  AssessmentResult result({
    List<RecommendedIntervention> recommended = const [],
  }) {
    return AssessmentResult(
      assessmentId: 5,
      totalScore: 72,
      scoreOutOf: 100,
      bandCode: 'high',
      bandLabel: 'High',
      completedAt: DateTime(2026, 9, 2),
      recommendedInterventions: recommended,
    );
  }

  testWidgets('shows score out of 100, level, date and recommended support', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(420, 2200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      MaterialApp(
        home: AssessmentResultScreen(
          result: result(
            recommended: const [
              RecommendedIntervention(
                title: 'Talk to someone',
                description: 'Confidential counselling support.',
              ),
            ],
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    expect(find.text('72 / 100'), findsOneWidget);
    expect(find.text('High'), findsOneWidget);
    expect(find.text('2 September 2026'), findsOneWidget);
    expect(find.text('Recommended Support'), findsOneWidget);
    expect(find.text('Talk to someone'), findsOneWidget);
  });

  testWidgets('hides the recommended support section when there is nothing', (
    tester,
  ) async {
    tester.view.physicalSize = const Size(420, 2200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      MaterialApp(home: AssessmentResultScreen(result: result())),
    );
    await tester.pumpAndSettle();

    expect(find.text('72 / 100'), findsOneWidget);
    expect(find.text('Recommended Support'), findsNothing);
  });
}
