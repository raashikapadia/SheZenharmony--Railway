import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/theme/app_theme.dart';
import 'package:shezen_harmony/features/activities/data/support_content.dart';
import 'package:shezen_harmony/features/activities/presentation/resource_screen.dart';

/// The Resource destination is a shortcut into the wellbeing library's
/// "Resource" feeling, so it must show that category and nothing else.
void main() {
  Future<void> pumpResources(
    WidgetTester tester,
    List<WellbeingActivity> activities,
  ) async {
    tester.view.physicalSize = const Size(430, 1200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final api = _FakeApiService(activities);
    addTearDown(api.close);

    await tester.pumpWidget(
      MaterialApp(theme: AppTheme.light, home: ResourceScreen(apiService: api)),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('lists the resource category and leaves other feelings out', (
    tester,
  ) async {
    await pumpResources(tester, [
      WellbeingActivity.fromInterventionJson({
        'title': 'Talk to a counsellor',
        'description': 'Book a confidential session with USP counselling.',
        'content_type': 'resource',
      }),
      WellbeingActivity.fromInterventionJson({
        'title': 'Ocean breathing',
        'description': 'A slow breath to settle with.',
        'content_type': 'breathing',
      }),
    ]);

    expect(find.text('Talk to a counsellor'), findsOneWidget);
    expect(find.text('Ocean breathing'), findsNothing);
  });

  testWidgets('withheld titles stay hidden here too', (tester) async {
    await pumpResources(tester, [
      WellbeingActivity.fromInterventionJson({
        'title': 'Box breathing',
        'description': 'Withheld from the student lists.',
        'content_type': 'resource',
      }),
    ]);

    expect(find.text('Box breathing'), findsNothing);
  });

  testWidgets('says resources are coming rather than showing an empty list', (
    tester,
  ) async {
    await pumpResources(tester, [
      WellbeingActivity.fromInterventionJson({
        'title': 'Ocean breathing',
        'description': 'A slow breath to settle with.',
        'content_type': 'breathing',
      }),
    ]);

    expect(find.text('Resources are being prepared'), findsOneWidget);
  });
}

class _FakeApiService extends ApiService {
  _FakeApiService(this.activities);

  final List<WellbeingActivity> activities;

  @override
  Future<List<WellbeingActivity>> wellbeingActivities() async => activities;
}
