import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/theme/app_theme.dart';
import 'package:shezen_harmony/features/activities/data/support_content.dart';
import 'package:shezen_harmony/features/activities/presentation/positive_engagement_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/resource_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/wellbeing_collection_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/wellbeing_hub_screen.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/guidance/presentation/personal_guidance_screen.dart';
import 'package:shezen_harmony/features/shezen/presentation/shezen_intro_screen.dart';

/// Every screen the student can be pushed into must offer a way back, or they
/// are stranded. A pushed [Scaffold] with no app bar has no back arrow, which
/// is easy to miss because the same screen looks fine while it is hosted
/// inside a tab that supplies one.
void main() {
  Future<void> pumpPushed(WidgetTester tester, Widget screen) async {
    tester.view.physicalSize = const Size(430, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ChangeNotifierProvider(
        create: (_) => AuthProvider(),
        child: MaterialApp(
          theme: AppTheme.light,
          home: Builder(
            builder: (context) => Scaffold(
              body: Center(
                child: TextButton(
                  onPressed: () => Navigator.of(
                    context,
                  ).push(MaterialPageRoute(builder: (_) => screen)),
                  child: const Text('open'),
                ),
              ),
            ),
          ),
        ),
      ),
    );

    await tester.tap(find.text('open'));
    await tester.pump();
    await tester.pump(const Duration(seconds: 1));
  }

  void expectCanGoBack(WidgetTester tester) {
    expect(
      find.byType(BackButton),
      findsOneWidget,
      reason: 'a pushed sub page must show a back control',
    );
  }

  testWidgets('personal guidance offers a way back', (tester) async {
    await pumpPushed(
      tester,
      PersonalGuidanceScreen(apiService: _FakeApiService(const [])),
    );
    expectCanGoBack(tester);
  });

  testWidgets('the wellbeing hub offers a way back', (tester) async {
    await pumpPushed(
      tester,
      WellbeingHubScreen(apiService: _FakeApiService(const [])),
    );
    expectCanGoBack(tester);
  });

  testWidgets('positive engagement offers a way back', (tester) async {
    await pumpPushed(tester, const PositiveEngagementScreen());
    expectCanGoBack(tester);
  });

  testWidgets('resource offers a way back', (tester) async {
    await pumpPushed(
      tester,
      ResourceScreen(apiService: _FakeApiService(const [])),
    );
    expectCanGoBack(tester);
  });

  testWidgets('a wellbeing collection offers a way back', (tester) async {
    await pumpPushed(
      tester,
      const WellbeingCollectionScreen(title: 'Breathing', activities: []),
    );
    expectCanGoBack(tester);
  });

  testWidgets('the Shezen intro offers a way back', (tester) async {
    await pumpPushed(tester, const ShezenIntroScreen());
    expectCanGoBack(tester);
  });
}

class _FakeApiService extends ApiService {
  _FakeApiService(this.activities);

  final List<WellbeingActivity> activities;

  @override
  Future<List<WellbeingActivity>> wellbeingActivities() async => activities;
}
