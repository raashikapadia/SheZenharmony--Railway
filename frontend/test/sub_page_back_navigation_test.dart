import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';
import 'package:shezen_harmony/core/theme/app_theme.dart';
import 'package:shezen_harmony/features/auth/data/auth_challenge.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';
import 'package:shezen_harmony/features/activities/data/support_content.dart';
import 'package:shezen_harmony/features/activities/presentation/games/games_quizzes_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/games/gratitude_jar_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/games/mindful_memory_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/games/mindful_spark_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/positive_engagement_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/wellbeing_activities_screen.dart';
import 'package:shezen_harmony/features/diary/presentation/diary_library_screen.dart';
import 'package:shezen_harmony/features/guidance/presentation/saved_guidance_screen.dart';
import 'package:shezen_harmony/features/profile/presentation/profile_view_screen.dart';
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
  /// Screens behind the sign-in wall read the session straight off the
  /// provider, so the harness signs in before pushing anything.
  Future<AuthProvider> signedIn() async {
    final provider = AuthProvider(
      apiService: _SignedInApiService(),
      storage: _MemoryStorage(),
    );
    await provider.register(
      email: 'student@student.usp.ac.fj',
      password: 'safe-password',
      passwordConfirmation: 'safe-password',
      demographics: const {},
      privacyConsent: true,
    );
    await provider.verifyOtp('123456');
    return provider;
  }

  Future<void> pumpPushed(WidgetTester tester, Widget screen) async {
    tester.view.physicalSize = const Size(430, 1000);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final auth = await signedIn();

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: auth,
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

  testWidgets('saved guidance offers a way back', (tester) async {
    await pumpPushed(
      tester,
      SavedGuidanceScreen(apiService: _FakeApiService(const [])),
    );
    expectCanGoBack(tester);
  });

  testWidgets('the flat activities list offers a way back', (tester) async {
    await pumpPushed(
      tester,
      WellbeingActivitiesScreen(apiService: _FakeApiService(const [])),
    );
    expectCanGoBack(tester);
  });

  testWidgets('games and quizzes offers a way back', (tester) async {
    await pumpPushed(tester, const GamesQuizzesScreen());
    expectCanGoBack(tester);
  });

  testWidgets('the gratitude jar offers a way back', (tester) async {
    await pumpPushed(tester, const GratitudeJarScreen());
    expectCanGoBack(tester);
  });

  testWidgets('mindful memory offers a way back', (tester) async {
    await pumpPushed(tester, const MindfulMemoryScreen());
    expectCanGoBack(tester);
  });

  testWidgets('mindful spark offers a way back', (tester) async {
    await pumpPushed(tester, const MindfulSparkScreen());
    expectCanGoBack(tester);

    // The screen fades each spark out on a delay. Those futures check
    // `mounted` rather than being cancellable, so unmount first and then let
    // them fire, or the test ends holding pending timers.
    await tester.pumpWidget(const SizedBox());
    await tester.pump(const Duration(seconds: 3));
  });

  testWidgets('the diary library offers a way back', (tester) async {
    await pumpPushed(tester, const DiaryLibraryScreen());
    expectCanGoBack(tester);
  });

  testWidgets('profile and account offers a way back', (tester) async {
    await pumpPushed(
      tester,
      ProfileViewScreen(apiService: _FakeApiService(const [])),
    );
    expectCanGoBack(tester);
  });

  // The activity detail screen puts its artwork behind a transparent bar, so
  // it draws its own back control instead of an AppBar's.
  testWidgets('an activity detail offers a way back', (tester) async {
    await pumpPushed(
      tester,
      const ActivityDetailScreen(
        activity: WellbeingActivity(
          title: 'Ocean breathing',
          description: 'A slow breath to settle with.',
          category: 'Breathing',
          sourceUrl: '',
          sourceType: 'guided',
          instructions: 'Breathe in, breathe out.',
          hasVideo: false,
        ),
      ),
    );

    expect(
      find.byIcon(Icons.arrow_back_rounded),
      findsOneWidget,
      reason: 'a pushed sub page must show a back control',
    );
  });
}

class _FakeApiService extends ApiService {
  _FakeApiService(this.activities);

  final List<WellbeingActivity> activities;

  @override
  Future<List<WellbeingActivity>> wellbeingActivities() async => activities;
}

class _SignedInApiService extends ApiService {
  @override
  Future<AuthChallenge> register({
    required String email,
    required String password,
    required String passwordConfirmation,
    required Map<String, dynamic> demographics,
    required bool privacyConsent,
    String deviceName = 'SheZen mobile app',
  }) async => const AuthChallenge(
    id: '11111111-1111-4111-8111-111111111111',
    purpose: 'registration',
    maskedEmail: 's*****@student.usp.ac.fj',
    expiresInSeconds: 600,
    resendAfterSeconds: 60,
  );

  @override
  Future<AuthSession> verifyOtp({
    required String challengeId,
    required String code,
  }) async => const AuthSession(
    token: 'token',
    role: 'student',
    shezenId: 'SZ-TESTIDENTITY',
    hasCompletedRequiredAssessment: true,
  );
}

class _MemoryStorage extends SecureTokenStorage {
  @override
  Future<void> save({required String token, required String role}) async {}
}
