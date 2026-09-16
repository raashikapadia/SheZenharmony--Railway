import 'dart:convert';

import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/features/diary/data/diary.dart';
import 'package:shezen_harmony/features/diary/data/diary_lock.dart';
import 'package:shezen_harmony/features/diary/data/diary_storage.dart';
import 'package:shezen_harmony/features/diary/data/diary_sync_service.dart';
import 'package:shezen_harmony/features/diary/data/diary_tombstone.dart';

void main() {
  /// Builds a sync service whose server echoes [response] and records what it
  /// was sent.
  ({
    DiarySyncService sync,
    _FakeStorage storage,
    List<dynamic> Function() sent,
    Map<String, dynamic>? Function() sentLock,
  })
  build({
    List<Map<String, dynamic>> response = const [],
    Map<String, dynamic>? responseLock,
    _FakeStorage? store,
  }) {
    final storage = store ?? _FakeStorage();
    final captured = <dynamic>[];
    Map<String, dynamic>? capturedLock;

    final client = _StubClient((request) {
      final body = jsonDecode(request.body) as Map<String, dynamic>;
      captured
        ..clear()
        ..addAll(body['diaries'] as List<dynamic>);
      capturedLock = body['lock'] as Map<String, dynamic>?;
      return jsonEncode({'data': response, 'lock': responseLock});
    });

    return (
      sync: DiarySyncService(
        storage: storage,
        apiService: ApiService(client: client),
      ),
      storage: storage,
      sent: () => captured,
      sentLock: () => capturedLock,
    );
  }

  test('the whole device diary is offered, not just recent changes', () async {
    final storage = _FakeStorage([
      Diary.create(
        title: 'Quiet thoughts',
      ).withPage(DiaryPage.blank().copyWith(title: 'First', body: 'a line')),
      Diary.create(title: 'Second'),
    ]);
    final harness = build(store: storage);

    await harness.sync.sync('token');

    // No queue to lose: everything on the device is sent every time.
    expect(harness.sent(), hasLength(2));
    expect(harness.sent().first['pages'], hasLength(1));
  });

  test(
    'a deleted diary is sent as deleted rather than simply omitted',
    () async {
      final storage = _FakeStorage(const [], [
        DiaryTombstone(
          diaryId: 'gone-1',
          pageId: null,
          deletedAt: DateTime(2026, 9, 10),
        ),
      ]);
      final harness = build(store: storage);

      await harness.sync.sync('token');

      final sent = harness.sent().single as Map<String, dynamic>;
      expect(sent['client_id'], 'gone-1');
      expect(sent['deleted'], isTrue);
    },
  );

  test('a deleted page travels with the diary that held it', () async {
    final diary = Diary.create(title: 'Quiet thoughts');
    final storage = _FakeStorage(
      [diary],
      [
        DiaryTombstone(
          diaryId: diary.id,
          pageId: 'page-gone',
          deletedAt: DateTime(2026, 9, 10),
        ),
      ],
    );
    final harness = build(store: storage);

    await harness.sync.sync('token');

    final pages =
        (harness.sent().single as Map<String, dynamic>)['pages']
            as List<dynamic>;
    expect(pages.single['client_id'], 'page-gone');
    expect(pages.single['deleted'], isTrue);
  });

  test(
    'a diary that was deleted is not also sent as if it still existed',
    () async {
      final diary = Diary.create(title: 'Quiet thoughts');
      final storage = _FakeStorage(
        [diary],
        [
          DiaryTombstone(
            diaryId: diary.id,
            pageId: null,
            deletedAt: DateTime(2026, 9, 10),
          ),
        ],
      );
      final harness = build(store: storage);

      await harness.sync.sync('token');

      expect(harness.sent(), hasLength(1));
      expect(
        (harness.sent().single as Map<String, dynamic>)['deleted'],
        isTrue,
      );
    },
  );

  test('what the server returns replaces the device copy', () async {
    final harness = build(
      store: _FakeStorage([Diary.create(title: 'only on this phone')]),
      response: [
        {
          'client_id': 'server-1',
          'title': 'written on the other phone',
          'cover_index': 2,
          'created_at': '2026-09-01T09:00:00.000Z',
          'updated_at': '2026-09-02T09:00:00.000Z',
          'pages': [
            {
              'client_id': 'page-1',
              'title': 'First evening',
              'body': 'Long day, but it passed.',
              'created_at': '2026-09-01T09:00:00.000Z',
              'updated_at': '2026-09-01T09:00:00.000Z',
            },
          ],
        },
      ],
    );

    final result = (await harness.sync.sync('token')).diaries;

    expect(result.single.title, 'written on the other phone');
    expect(result.single.pages.single.body, 'Long day, but it passed.');
    expect(harness.storage.saved.single.title, 'written on the other phone');
  });

  test(
    'the student PIN survives the round trip, so a restored diary stays locked',
    () async {
      final lock = DiaryLock.fromPin('2468');
      final harness = build(
        store: _FakeStorage(
          [Diary.create(title: 'Quiet thoughts', isLocked: true)],
          const [],
          DiaryLockState(lock: lock, updatedAt: _aDate),
        ),
        response: [
          {
            'client_id': 'server-1',
            'title': 'Quiet thoughts',
            'cover_index': 0,
            'is_locked': true,
            'created_at': '2026-09-01T09:00:00.000Z',
            'updated_at': '2026-09-01T09:00:00.000Z',
            'pages': <Map<String, dynamic>>[],
          },
        ],
        responseLock: {
          'salt': lock.salt,
          'hash': lock.hash,
          'updated_at': '2026-09-01T09:00:00.000Z',
        },
      );

      final result = await harness.sync.sync('token');

      expect(result.diaries.single.isLocked, isTrue);
      expect(result.lock!.matches('2468'), isTrue);

      // One PIN for the student, sent once — not repeated on every diary.
      expect(harness.sent().single, isNot(contains('lock')));
      expect(jsonEncode(harness.sentLock()), isNot(contains('2468')));
      expect(harness.sentLock()!['hash'], lock.hash);
    },
  );

  test(
    'a device with no PIN offers none, rather than claiming there is none',
    () async {
      final harness = build(store: _FakeStorage([Diary.create(title: 'A')]));

      await harness.sync.sync('token');

      // Silence, so the server does not read this as "unlock everything".
      expect(harness.sentLock(), isNull);
    },
  );

  test(
    'a PIN the student removed is offered as an explicit clearing',
    () async {
      final harness = build(
        store: _FakeStorage(
          const [],
          const [],
          DiaryLockState(lock: null, updatedAt: _aDate),
        ),
      );

      await harness.sync.sync('token');

      expect(harness.sentLock(), isNotNull);
      expect(harness.sentLock()!['cleared'], isTrue);
    },
  );

  test('a PIN set on another device is adopted by this one', () async {
    final lock = DiaryLock.fromPin('1357');
    final storage = _FakeStorage();
    final harness = build(
      store: storage,
      responseLock: {
        'salt': lock.salt,
        'hash': lock.hash,
        'updated_at': '2026-09-05T09:00:00.000Z',
      },
    );

    final result = await harness.sync.sync('token');

    expect(result.lock!.matches('1357'), isTrue);
    // And kept, so the next launch already knows it without a round trip.
    expect(storage.lockState!.lock!.matches('1357'), isTrue);
  });

  test(
    'deletions are only forgotten once the server has accepted them',
    () async {
      final storage = _FakeStorage(const [], [
        DiaryTombstone(
          diaryId: 'gone-1',
          pageId: null,
          deletedAt: DateTime(2026, 9, 10),
        ),
      ]);
      final harness = build(store: storage);

      await harness.sync.sync('token');

      expect(storage.tombstones, isEmpty);
    },
  );

  test(
    'a failed sync keeps the local diary and the pending deletions',
    () async {
      final storage = _FakeStorage(
        [Diary.create(title: 'Quiet thoughts')],
        [
          DiaryTombstone(
            diaryId: 'gone-1',
            pageId: null,
            deletedAt: DateTime(2026, 9, 10),
          ),
        ],
      );
      final sync = DiarySyncService(
        storage: storage,
        apiService: ApiService(
          client: _StubClient((_) => throw http.ClientException('offline')),
        ),
      );

      await expectLater(sync.sync('token'), throwsA(isA<ApiException>()));

      // Nothing was thrown away, so the next sync can try the whole thing again.
      expect(storage.saved.single.title, 'Quiet thoughts');
      expect(storage.tombstones, hasLength(1));
    },
  );

  test(
    'one unreadable diary in the response does not lose the others',
    () async {
      final harness = build(
        response: [
          {'title': 'no client id at all'},
          {
            'client_id': 'server-2',
            'title': 'Quiet thoughts',
            'cover_index': 0,
            'created_at': '2026-09-01T09:00:00.000Z',
            'updated_at': '2026-09-01T09:00:00.000Z',
            'pages': <Map<String, dynamic>>[],
          },
        ],
      );

      final result = (await harness.sync.sync('token')).diaries;

      expect(result, hasLength(1));
      expect(result.single.title, 'Quiet thoughts');
    },
  );
}

/// A fixed date for records whose exact timestamp is not what is under test.
final _aDate = DateTime.utc(2026, 9, 1);

class _FakeStorage extends DiaryStorage {
  _FakeStorage([
    List<Diary> initial = const [],
    List<DiaryTombstone> pending = const [],
    this.lockState,
  ]) : saved = [...initial],
       tombstones = [...pending];

  List<Diary> saved;
  List<DiaryTombstone> tombstones;
  DiaryLockState? lockState;

  @override
  Future<DiaryLockState?> readLock() async => lockState;

  @override
  Future<void> writeLock(DiaryLockState state) async => lockState = state;

  @override
  Future<List<Diary>> readAll() async => saved;

  @override
  Future<void> writeAll(List<Diary> diaries) async => saved = [...diaries];

  @override
  Future<List<DiaryTombstone>> readTombstones() async => tombstones;

  @override
  Future<void> writeTombstones(List<DiaryTombstone> next) async =>
      tombstones = [...next];
}

class _StubClient extends http.BaseClient {
  _StubClient(this.respond);

  final String Function(http.Request request) respond;

  @override
  Future<http.StreamedResponse> send(http.BaseRequest request) async {
    final body = respond(request as http.Request);
    return http.StreamedResponse(
      Stream.value(utf8.encode(body)),
      200,
      headers: {'content-type': 'application/json'},
    );
  }
}
