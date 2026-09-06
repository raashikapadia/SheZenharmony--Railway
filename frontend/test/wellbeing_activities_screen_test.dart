import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/theme/app_theme.dart';
import 'package:shezen_harmony/features/activities/data/support_content.dart';
import 'package:shezen_harmony/features/activities/presentation/wellbeing_activities_screen.dart';

void main() {
  testWidgets('a newly published video opens the redesigned detail screen', (
    tester,
  ) async {
    debugDefaultTargetPlatformOverride = TargetPlatform.windows;
    tester.view.physicalSize = const Size(430, 900);
    tester.view.devicePixelRatio = 1;
    addTearDown(() => debugDefaultTargetPlatformOverride = null);
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final api = _FakeApiService([
      WellbeingActivity.fromInterventionJson({
        'title': 'New meditation video',
        'description': 'A newly published video.',
        'content_type': 'mindfulness',
        'external_url': 'youtu.be/dQw4w9WgXcQ',
      }),
    ]);
    addTearDown(api.close);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: WellbeingActivitiesScreen(apiService: api),
      ),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('New meditation video'));
    await tester.pumpAndSettle();
    debugDefaultTargetPlatformOverride = null;

    expect(find.byType(SliverAppBar), findsOneWidget);
    expect(find.text('New meditation video'), findsWidgets);
    expect(find.text('Tap to watch video'), findsOneWidget);
    expect(
      find.text('Choose a safe, comfortable space before beginning.'),
      findsNothing,
    );
    expect(
      find.textContaining('Choose a safe, comfortable space before beginning'),
      findsOneWidget,
    );
  });
}

class _FakeApiService extends ApiService {
  _FakeApiService(this.activities);

  final List<WellbeingActivity> activities;

  @override
  Future<List<WellbeingActivity>> wellbeingActivities() async => activities;
}
