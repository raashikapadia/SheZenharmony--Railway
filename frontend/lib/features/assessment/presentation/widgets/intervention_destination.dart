import 'package:flutter/material.dart';

import '../../../activities/presentation/games/games_quizzes_screen.dart';
import '../../../activities/presentation/positive_engagement_screen.dart';
import '../../../activities/presentation/resource_screen.dart';
import '../../../activities/presentation/wellbeing_hub_screen.dart';
import '../../../diary/presentation/diary_library_screen.dart';
import '../../../guidance/presentation/personal_guidance_screen.dart';
import '../../../shezen/presentation/shezen_chat_screen.dart';
import '../../data/assessment_result.dart';

/// Maps the destination an admin chose for a piece of support onto the screen
/// it lives on. The keys match the backend's `AppScreen` enum, and an
/// unrecognised one resolves to null so an older app build simply falls back
/// to the item's link or detail sheet rather than failing.
Widget? _screenFor(String key) => switch (key) {
  'screen.wellbeing_activities' => const WellbeingHubScreen(),
  'screen.journaling' => const DiaryLibraryScreen(),
  'screen.personal_guidance' => const PersonalGuidanceScreen(),
  'screen.positive_engagement' => const PositiveEngagementScreen(),
  'screen.games_quizzes' => const GamesQuizzesScreen(),
  'screen.resources' => const ResourceScreen(),
  'screen.chat_buddy' => const ShezenChatScreen(),
  _ => null,
};

/// The in-app screen [item] should open, or null when the admin set no
/// destination (or set one this build does not know).
Widget? interventionDestination(RecommendedIntervention item) {
  final key = item.appScreen;
  if (key == null || key.isEmpty) return null;
  return _screenFor(key);
}

/// True when tapping [item] opens a screen inside the app rather than leaving
/// for a browser.
bool opensInApp(RecommendedIntervention item) =>
    interventionDestination(item) != null;

/// Pushes [item]'s screen. Returns false when it has no in-app destination,
/// leaving the caller to fall back to its link or detail sheet.
bool openInterventionScreen(
  BuildContext context,
  RecommendedIntervention item,
) {
  final screen = interventionDestination(item);
  if (screen == null) return false;
  Navigator.of(context).push(MaterialPageRoute(builder: (_) => screen));
  return true;
}
