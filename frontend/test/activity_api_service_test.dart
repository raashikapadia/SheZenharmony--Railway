import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shezen_harmony/core/network/api_service.dart';

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
              ],
            }),
            200,
          );
        }),
      );

      final activities = await api.wellbeingActivities();

      expect(activities.map((item) => item.title), [
        'Box breathing',
        'Grounding video',
      ]);
      expect(activities.first.instructions, 'Breathe in for four counts.');
      expect(activities.first.hasVideo, isFalse);
      expect(activities.last.hasVideo, isTrue);
    },
  );

  test('journaling content is included in positive engagement', () async {
    final api = ApiService(
      client: MockClient((request) async {
        expect(
          request.url.queryParameters['content_type'],
          contains('journaling'),
        );
        return http.Response(
          jsonEncode({
            'data': [
              {
                'title': 'Gratitude reflection',
                'description': 'Notice three good things.',
                'content_type': 'journaling',
                'instructions': 'Write down three small things.',
              },
            ],
          }),
          200,
        );
      }),
    );

    final content = await api.positiveEngagement();

    expect(content.single.title, 'Gratitude reflection');
    expect(content.single.contentType, 'journaling');
  });
}
