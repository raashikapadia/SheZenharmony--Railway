import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/features/activities/presentation/games/games_quizzes_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/positive_engagement_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/resource_screen.dart';
import 'package:shezen_harmony/features/activities/presentation/wellbeing_hub_screen.dart';
import 'package:shezen_harmony/features/assessment/data/assessment_result.dart';
import 'package:shezen_harmony/features/assessment/presentation/widgets/intervention_destination.dart';
import 'package:shezen_harmony/features/diary/presentation/diary_library_screen.dart';
import 'package:shezen_harmony/features/guidance/presentation/personal_guidance_screen.dart';
import 'package:shezen_harmony/features/shezen/presentation/shezen_chat_screen.dart';

RecommendedIntervention item({String? appScreen, String? externalUrl}) =>
    RecommendedIntervention(
      title: 'Support',
      appScreen: appScreen,
      externalUrl: externalUrl,
    );

void main() {
  test('each configured destination resolves to the screen it names', () {
    final expected = {
      'screen.wellbeing_activities': WellbeingHubScreen,
      'screen.journaling': DiaryLibraryScreen,
      'screen.personal_guidance': PersonalGuidanceScreen,
      'screen.positive_engagement': PositiveEngagementScreen,
      'screen.games_quizzes': GamesQuizzesScreen,
      'screen.resources': ResourceScreen,
      'screen.chat_buddy': ShezenChatScreen,
    };

    expected.forEach((key, type) {
      final screen = interventionDestination(item(appScreen: key));
      expect(screen.runtimeType, type, reason: '$key should open $type');
      expect(opensInApp(item(appScreen: key)), isTrue);
    });
  });

  test('support with no destination stays on its link or details', () {
    for (final noDestination in [
      item(),
      item(appScreen: ''),
      item(externalUrl: 'https://example.org'),
    ]) {
      expect(interventionDestination(noDestination), isNull);
      expect(opensInApp(noDestination), isFalse);
    }
  });

  test('a destination this build does not know is ignored, not fatal', () {
    // An older app against a newer admin: the item falls back rather than
    // failing to render.
    final unknown = item(appScreen: 'some_future_screen');
    expect(interventionDestination(unknown), isNull);
    expect(opensInApp(unknown), isFalse);
  });
}
