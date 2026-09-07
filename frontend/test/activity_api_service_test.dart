import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/features/activities/data/support_content.dart';

void main() {
  test(
    'guided and video wellbeing content are combined for students',
    () async {
      final api = ApiService(
        client: MockClient((request) async {
          if (request.url.path.endsWith('/wellbeing-activities')) {
            return http.Response(
              jsonEncode({
                'data': [
                  {
                    'title': 'Grounding video',
                    'description': 'A short video.',
                    'category': 'Grounding',
                    'video_url': 'https://example.com/video',
                    'video_type': 'youtube',
                  },
                ],
              }),
              200,
            );
          }

          expect(request.url.path, endsWith('/interventions'));
          expect(
            request.url.queryParameters['content_type'],
            contains('breathing'),
          );
          expect(
            request.url.queryParameters['content_type'],
            contains('journaling'),
          );
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'title': 'Box breathing',
                  'description': 'A guided breathing activity.',
                  'content_type': 'breathing',
                  'instructions': 'Breathe in for four counts.',
                  'external_url': null,
                },
                {
                  'title': 'Private reflection',
                  'description': 'A journaling prompt.',
                  'content_type': 'journaling',
                  'instructions': 'Write down what is on your mind.',
                  'external_url': null,
                },
              ],
            }),
            200,
          );
        }),
      );

      final activities = await api.wellbeingActivities();

      expect(activities.map((item) => item.title), [
        'Box breathing',
        'Private reflection',
        'Grounding video',
      ]);
      expect(activities.first.instructions, 'Breathe in for four counts.');
      expect(activities.first.hasVideo, isFalse);
      expect(activities[1].category, 'Journaling');
      expect(activities.last.hasVideo, isTrue);
    },
  );

  test(
    'positive engagement excludes support journaling and affirmations',
    () async {
      final api = ApiService(
        client: MockClient((request) async {
          expect(
            request.url.queryParameters['content_type'],
            isNot(contains('journaling')),
          );
          expect(
            request.url.queryParameters['content_type'],
            isNot(contains('affirmation')),
          );
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'title': 'A positive prompt',
                  'description': 'Notice one good thing.',
                  'content_type': 'motivation',
                  'instructions': 'Pause and reflect.',
                },
              ],
            }),
            200,
          );
        }),
      );

      final content = await api.positiveEngagement();

      expect(content.single.title, 'A positive prompt');
      expect(content.single.contentType, 'motivation');
    },
  );

  test('video URLs on support content use the video player flow', () {
    final youtubeActivity = WellbeingActivity.fromInterventionJson({
      'title': 'New meditation video',
      'description': 'A newly published video.',
      'content_type': 'mindfulness',
      'instructions': 'Find a comfortable place before starting.',
      'external_url': 'youtu.be/dQw4w9WgXcQ',
    });
    final tiktokActivity = WellbeingActivity.fromInterventionJson({
      'title': 'New movement video',
      'content_type': 'activity',
      'external_url': 'https://www.tiktok.com/@example/video/1234567890',
    });

    expect(youtubeActivity.hasVideo, isTrue);
    expect(youtubeActivity.sourceType, 'youtube');
    expect(
      youtubeActivity.instructions,
      'Find a comfortable place before starting.',
    );
    expect(tiktokActivity.hasVideo, isTrue);
    expect(tiktokActivity.sourceType, 'tiktok');
  });
}
