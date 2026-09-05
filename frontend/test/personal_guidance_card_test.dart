import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:provider/provider.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/features/auth/application/auth_provider.dart';
import 'package:shezen_harmony/features/auth/data/auth_challenge.dart';
import 'package:shezen_harmony/features/auth/data/auth_session.dart';
import 'package:shezen_harmony/features/guidance/data/personal_guidance.dart';
import 'package:shezen_harmony/features/guidance/presentation/personal_guidance_card.dart';
import 'package:shezen_harmony/core/storage/secure_token_storage.dart';

void main() {
  Future<AuthProvider> signedIn() async {
    final provider = AuthProvider(
      apiService: _AuthStub(),
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

  Future<void> pump(WidgetTester tester, ApiService api) async {
    tester.view.physicalSize = const Size(430, 2200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    await tester.pumpWidget(
      ChangeNotifierProvider.value(
        value: await signedIn(),
        child: MaterialApp(
          home: Scaffold(
            body: SingleChildScrollView(
              child: PersonalGuidanceCard(apiService: api),
            ),
          ),
        ),
      ),
    );
  }

  const affirmation = PersonalGuidance(
    id: 1,
    type: GuidanceType.affirmation,
    content: 'I am capable of handling whatever today brings.',
  );
  const quote = PersonalGuidance(
    id: 2,
    type: GuidanceType.quote,
    content: 'It always seems impossible until it is done.',
    author: 'Nelson Mandela',
  );
  const guidance = PersonalGuidance(
    id: 3,
    type: GuidanceType.guidance,
    content: 'One honest step is enough for today.',
  );

  testWidgets('renders an affirmation with its reminder header and no author', (
    tester,
  ) async {
    await pump(tester, _Stub(current: affirmation));
    await tester.pumpAndSettle();

    expect(find.text('💗  A LITTLE REMINDER'), findsOneWidget);
    expect(
      find.text('“I am capable of handling whatever today brings.”'),
      findsOneWidget,
    );
    expect(find.textContaining('—'), findsNothing);
  });

  testWidgets('renders a quote with its author attribution', (tester) async {
    await pump(tester, _Stub(current: quote));
    await tester.pumpAndSettle();

    expect(find.text('✨  TODAY\'S INSPIRATION'), findsOneWidget);
    expect(
      find.text('“It always seems impossible until it is done.”'),
      findsOneWidget,
    );
    expect(find.text('— Nelson Mandela'), findsOneWidget);
  });

  testWidgets('signs admin guidance as SheZen when no author is set', (
    tester,
  ) async {
    await pump(tester, _Stub(current: guidance));
    await tester.pumpAndSettle();

    expect(find.text('One honest step is enough for today.'), findsOneWidget);
    expect(find.text('— SheZen'), findsOneWidget);
  });

  testWidgets('"Another one" swaps in a new item', (tester) async {
    await pump(tester, _Stub(current: affirmation, another: quote));
    await tester.pumpAndSettle();

    expect(
      find.text('“I am capable of handling whatever today brings.”'),
      findsOneWidget,
    );

    await tester.tap(find.text('Another one ✨'));
    await tester.pumpAndSettle();

    expect(
      find.text('“It always seems impossible until it is done.”'),
      findsOneWidget,
    );
    expect(
      find.text('“I am capable of handling whatever today brings.”'),
      findsNothing,
    );
  });

  testWidgets('shows a soft loading state first', (tester) async {
    await pump(
      tester,
      _Stub(
        current: affirmation,
        currentDelay: const Duration(milliseconds: 400),
      ),
    );
    await tester.pump();

    expect(find.textContaining('Finding a little something'), findsOneWidget);

    await tester.pump(const Duration(milliseconds: 400));
    await tester.pumpAndSettle();
    expect(find.textContaining('Finding a little something'), findsNothing);
  });

  testWidgets('shows the gentle empty state when nothing is published', (
    tester,
  ) async {
    await pump(tester, _Stub(current: null));
    await tester.pumpAndSettle();

    expect(find.text('Your little space is quiet for now.'), findsOneWidget);
  });

  testWidgets('shows a non-technical error state and can retry', (
    tester,
  ) async {
    final stub = _Stub(current: affirmation, failCurrentOnce: true);
    await pump(tester, stub);
    await tester.pumpAndSettle();

    expect(find.text('Oops 🌷'), findsOneWidget);
    expect(find.text('Try again ✨'), findsOneWidget);

    await tester.tap(find.text('Try again ✨'));
    await tester.pumpAndSettle();

    expect(
      find.text('“I am capable of handling whatever today brings.”'),
      findsOneWidget,
    );
  });

  testWidgets('favourite heart toggles and calls the API', (tester) async {
    final stub = _Stub(current: affirmation);
    await pump(tester, stub);
    await tester.pumpAndSettle();

    expect(find.byIcon(Icons.favorite_border_rounded), findsOneWidget);

    await tester.tap(find.byIcon(Icons.favorite_border_rounded));
    await tester.pumpAndSettle();

    expect(find.byIcon(Icons.favorite_rounded), findsOneWidget);
    expect(stub.favouriteCalls, [(1, true)]);
  });
}

class _Stub extends ApiService {
  _Stub({
    required this.current,
    this.another,
    this.currentDelay = Duration.zero,
    this.failCurrentOnce = false,
  });

  final PersonalGuidance? current;
  final PersonalGuidance? another;
  final Duration currentDelay;
  bool failCurrentOnce;
  final List<(int, bool)> favouriteCalls = [];

  @override
  Future<PersonalGuidance?> currentGuidance(String token) async {
    if (currentDelay != Duration.zero) await Future<void>.delayed(currentDelay);
    if (failCurrentOnce) {
      failCurrentOnce = false;
      throw const ApiException('network down');
    }
    return current;
  }

  @override
  Future<PersonalGuidance?> anotherGuidance(
    String token, {
    int? excludeId,
  }) async {
    return another ?? current;
  }

  @override
  Future<void> setGuidanceFavourite(
    String token,
    int id, {
    required bool favourite,
  }) async {
    favouriteCalls.add((id, favourite));
  }
}

class _AuthStub extends ApiService {
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
