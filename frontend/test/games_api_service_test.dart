import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:shezen_harmony/core/network/api_service.dart';

void main() {
  test(
    'games come from the backend, which decides what students see',
    () async {
      final api = ApiService(
        client: MockClient((request) async {
          expect(request.url.path, endsWith('/interventions'));
          expect(request.url.queryParameters['content_type'], 'game');
          return http.Response(
            jsonEncode({
              'data': [
                {
                  'id': 7,
                  'slug': 'memory-spark',
                  'title': 'Spark Moments',
                  'description': 'A renamed game.',
                  'content_type': 'game',
                  'instructions': null,
                  'external_url': null,
                },
              ],
            }),
            200,
          );
        }),
      );

      final games = await api.games();

      // Only the published game is offered: nothing is merged back in, or an
      // admin could never hide a game from students.
      expect(games, hasLength(1));
      expect(games.single.id, 7);
      expect(games.single.slug, 'memory-spark');
      expect(games.single.title, 'Spark Moments');
    },
  );

  test('a game an admin has hidden does not reappear', () async {
    final api = ApiService(
      client: MockClient(
        (request) async => http.Response(jsonEncode({'data': []}), 200),
      ),
    );

    expect(await api.games(), isEmpty);
  });

  test(
    'the bundled games stand in only when the backend is unreachable',
    () async {
      final api = ApiService(
        client: MockClient(
          (request) async =>
              http.Response(jsonEncode({'message': 'Down'}), 503),
        ),
      );

      final games = await api.games();

      expect(games.map((game) => game.slug), [
        'breathing-challenge',
        'gratitude-jar',
        'memory-spark',
        'mindful-memory',
      ]);
      // No id means no play is recorded against a game the backend never named.
      expect(games.every((game) => game.id == null), isTrue);
    },
  );
}
