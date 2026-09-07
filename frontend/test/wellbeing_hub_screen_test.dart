import 'package:flutter/material.dart';
import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/theme/app_theme.dart';
import 'package:shezen_harmony/features/activities/data/support_content.dart';
import 'package:shezen_harmony/features/activities/presentation/wellbeing_hub_screen.dart';

void main() {
  Future<void> pumpHub(
    WidgetTester tester, {
    Size size = const Size(430, 1400),
  }) async {
    tester.view.physicalSize = size;
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final api = _FakeApiService([
      WellbeingActivity.fromInterventionJson({
        'title': 'Morning meditation',
        'description': 'Start the day slowly.',
        'content_type': 'mindfulness',
        'external_url': 'youtu.be/dQw4w9WgXcQ',
      }),
      WellbeingActivity.fromInterventionJson({
        'title': 'Ocean breathing',
        'description': 'A slow breath to settle with.',
        'content_type': 'breathing',
        'external_url': 'youtu.be/aaaaaaaaaaa',
      }),
      WellbeingActivity.fromInterventionJson({
        'title': 'Gratitude journal',
        'description': 'Three good things from today.',
        'content_type': 'journaling',
      }),
      WellbeingActivity.fromInterventionJson({
        'title': 'Box breathing',
        'description': 'Withheld from the student lists.',
        'content_type': 'breathing',
      }),
    ]);
    addTearDown(api.close);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: WellbeingHubScreen(apiService: api),
      ),
    );
    await tester.pumpAndSettle();
  }

  testWidgets('the hub offers collections instead of a flat list', (
    tester,
  ) async {
    await pumpHub(tester);

    expect(find.text('Wellbeing videos'), findsOneWidget);
    expect(find.text('2 videos'), findsOneWidget);
    expect(find.text('Journaling'), findsOneWidget);
    expect(find.text('BROWSE BY FEELING'), findsOneWidget);

    // Collections is videos and the diary only.
    expect(find.text('Guided practices'), findsNothing);
    expect(find.text('All activities'), findsNothing);

    // The hub is a menu, so no activity has been listed yet.
    expect(find.text('Morning meditation'), findsNothing);
  });

  testWidgets('box breathing stays hidden from the category it belongs to', (
    tester,
  ) async {
    await pumpHub(tester);

    expect(find.text('Box breathing'), findsNothing);

    await tester.tap(find.text('Breathing'));
    await tester.pumpAndSettle();

    expect(find.text('Box breathing'), findsNothing);
    expect(find.text('Ocean breathing'), findsOneWidget);
    expect(find.text('1 activity'), findsOneWidget);
  });

  testWidgets('the videos collection lists only videos', (tester) async {
    await pumpHub(tester);

    await tester.tap(find.text('Wellbeing videos'));
    await tester.pumpAndSettle();

    expect(find.text('Morning meditation'), findsOneWidget);
    expect(find.text('Ocean breathing'), findsOneWidget);
    expect(find.text('Gratitude journal'), findsNothing);
    expect(find.text('2 videos'), findsOneWidget);
  });

  testWidgets('search narrows the videos collection', (tester) async {
    await pumpHub(tester);

    await tester.tap(find.text('Wellbeing videos'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'ocean');
    await tester.pumpAndSettle();

    expect(find.text('Ocean breathing'), findsOneWidget);
    expect(find.text('Morning meditation'), findsNothing);
    expect(find.text('1 of 2 videos'), findsOneWidget);
  });

  testWidgets('search matches the description as well as the title', (
    tester,
  ) async {
    await pumpHub(tester);

    await tester.tap(find.text('Wellbeing videos'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'slow breath to settle');
    await tester.pumpAndSettle();

    expect(find.text('Ocean breathing'), findsOneWidget);
    expect(find.text('1 of 2 videos'), findsOneWidget);
  });

  testWidgets('an unmatched search offers a way back', (tester) async {
    await pumpHub(tester);

    await tester.tap(find.text('Wellbeing videos'));
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'kayaking');
    await tester.pumpAndSettle();

    expect(find.text('Nothing matches that'), findsOneWidget);

    await tester.tap(find.text('Clear search'));
    await tester.pumpAndSettle();

    expect(find.text('Ocean breathing'), findsOneWidget);
    expect(find.text('2 videos'), findsOneWidget);
  });

  testWidgets('a category chip filters the collection', (tester) async {
    await pumpHub(tester);

    await tester.tap(find.text('Wellbeing videos'));
    await tester.pumpAndSettle();

    await tester.tap(find.text('Breathing'));
    await tester.pumpAndSettle();

    expect(find.text('Ocean breathing'), findsOneWidget);
    expect(find.text('Morning meditation'), findsNothing);
  });

  testWidgets('a feeling tile opens that category', (tester) async {
    await pumpHub(tester);

    await tester.tap(find.text('Breathing'));
    await tester.pumpAndSettle();

    expect(find.text('Ocean breathing'), findsOneWidget);
    expect(find.text('Morning meditation'), findsNothing);
  });

  testWidgets('the journaling tile opens the private diary', (tester) async {
    FlutterSecureStorage.setMockInitialValues({});
    await pumpHub(tester);

    await tester.tap(find.text('Journaling'));
    await tester.pumpAndSettle();

    expect(find.text('Only on this phone'), findsOneWidget);
    // Admin-published journaling content does not live behind this tile.
    expect(find.text('Gratitude journal'), findsNothing);
  });

  testWidgets('admin journaling content is not surfaced anywhere on the hub', (
    tester,
  ) async {
    await pumpHub(tester);

    // "Journaling" now means the private diary, and there is no longer an
    // all-activities or guided-practices list to reach the seeded prompt
    // through. It stays available via the assessment result screen's list.
    expect(find.text('Gratitude journal'), findsNothing);
    expect(find.text('Reflection'), findsNothing);
  });

  testWidgets('the hub and a collection lay out on a narrow phone', (
    tester,
  ) async {
    // A RenderFlex overflow throws during the pump, so reaching the
    // expectations is itself the assertion that nothing overflowed.
    await pumpHub(tester, size: const Size(320, 700));

    expect(find.text('Wellbeing videos'), findsOneWidget);

    await tester.tap(find.text('Wellbeing videos'));
    await tester.pumpAndSettle();

    expect(find.text('Ocean breathing'), findsOneWidget);
  });
}

class _FakeApiService extends ApiService {
  _FakeApiService(this.activities);

  final List<WellbeingActivity> activities;

  @override
  Future<List<WellbeingActivity>> wellbeingActivities() async => activities;
}
