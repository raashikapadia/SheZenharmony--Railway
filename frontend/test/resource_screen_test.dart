import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/core/theme/app_theme.dart';
import 'package:shezen_harmony/features/activities/data/support_content.dart';
import 'package:shezen_harmony/features/activities/presentation/resource_screen.dart';

/// The Resource destination merges two admin-published sources: the helplines
/// published in the Web Admin's Resource section, and the wellbeing library's
/// "Resource" feeling. It must lead with the helplines and show that category
/// and nothing else beneath them.
void main() {
  Future<void> pumpResources(
    WidgetTester tester,
    List<WellbeingActivity> activities, {
    List<HelplineResource> helplines = const [],
  }) async {
    tester.view.physicalSize = const Size(430, 1200);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    final api = _FakeApiService(activities, helplines);
    addTearDown(api.close);

    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: ResourceScreen(apiService: api),
      ),
    );
    await tester.pumpAndSettle();
  }

  HelplineResource helpline({
    String name = 'National Crisis Helpline',
    String phone = '1325',
    bool isEmergency = false,
  }) => HelplineResource(
    id: 1,
    name: name,
    organisation: '',
    description: '',
    phone: phone,
    alternatePhone: '',
    email: '',
    websiteUrl: '',
    availability: '',
    category: '',
    isEmergency: isEmergency,
  );

  testWidgets('lists the resource category and leaves other feelings out', (
    tester,
  ) async {
    await pumpResources(tester, [
      WellbeingActivity.fromInterventionJson({
        'title': 'Talk to a counsellor',
        'description': 'Book a confidential session with USP counselling.',
        'content_type': 'resource',
      }),
      WellbeingActivity.fromInterventionJson({
        'title': 'Ocean breathing',
        'description': 'A slow breath to settle with.',
        'content_type': 'breathing',
      }),
    ]);

    expect(find.text('Talk to a counsellor'), findsOneWidget);
    expect(find.text('Ocean breathing'), findsNothing);
  });

  testWidgets('withheld titles stay hidden here too', (tester) async {
    await pumpResources(tester, [
      WellbeingActivity.fromInterventionJson({
        'title': 'Box breathing',
        'description': 'Withheld from the student lists.',
        'content_type': 'resource',
      }),
    ]);

    expect(find.text('Box breathing'), findsNothing);
  });

  testWidgets('says resources are coming rather than showing an empty list', (
    tester,
  ) async {
    await pumpResources(tester, [
      WellbeingActivity.fromInterventionJson({
        'title': 'Ocean breathing',
        'description': 'A slow breath to settle with.',
        'content_type': 'breathing',
      }),
    ]);

    expect(find.text('Resources are being prepared'), findsOneWidget);
  });

  testWidgets('shows admin-published helplines with a call action', (
    tester,
  ) async {
    await pumpResources(
      tester,
      const [],
      helplines: [helpline(name: 'USP Student Counselling', phone: '323 1000')],
    );

    expect(find.text('USP Student Counselling'), findsOneWidget);
    expect(find.text('Call 323 1000'), findsOneWidget);
  });

  testWidgets('helplines still show when no resource activities exist', (
    tester,
  ) async {
    await pumpResources(tester, const [], helplines: [helpline()]);

    // The "being prepared" empty state belongs to the case where the admin has
    // published nothing at all — a helpline on its own is enough to show.
    expect(find.text('Resources are being prepared'), findsNothing);
    expect(find.text('National Crisis Helpline'), findsOneWidget);
  });

  testWidgets('helplines lead the list above the resource content', (
    tester,
  ) async {
    await pumpResources(
      tester,
      [
        WellbeingActivity.fromInterventionJson({
          'title': 'Talk to a counsellor',
          'description': 'Book a confidential session with USP counselling.',
          'content_type': 'resource',
        }),
      ],
      helplines: [helpline(isEmergency: true)],
    );

    expect(find.text('National Crisis Helpline'), findsOneWidget);
    expect(find.text('Emergency'), findsOneWidget);
    expect(find.text('Talk to a counsellor'), findsOneWidget);

    // The header sits above the first activity tile, not after it.
    final helplineY = tester
        .getTopLeft(find.text('National Crisis Helpline'))
        .dy;
    final activityY = tester.getTopLeft(find.text('Talk to a counsellor')).dy;
    expect(helplineY, lessThan(activityY));
  });

  testWidgets('the email address is its own tappable row', (tester) async {
    await pumpResources(
      tester,
      const [],
      helplines: [
        HelplineResource(
          id: 1,
          name: 'USP Student Counselling',
          organisation: '',
          description: '',
          phone: '323 1000',
          alternatePhone: '',
          email: 'counselling@usp.ac.fj',
          websiteUrl: '',
          availability: '',
          category: '',
          isEmergency: false,
        ),
      ],
    );

    final email = find.text('counselling@usp.ac.fj');
    expect(email, findsOneWidget);
    expect(find.text('Email them'), findsOneWidget);

    // Tappable rather than plain text to copy out by hand.
    expect(
      find.ancestor(of: email, matching: find.byType(InkWell)),
      findsOneWidget,
    );
  });

  testWidgets('the empty state needs both sources to be empty', (tester) async {
    await pumpResources(tester, const []);

    expect(find.text('Resources are being prepared'), findsOneWidget);
  });
}

class _FakeApiService extends ApiService {
  _FakeApiService(this.activities, this.helplineResources);

  final List<WellbeingActivity> activities;
  final List<HelplineResource> helplineResources;

  @override
  Future<List<WellbeingActivity>> wellbeingActivities() async => activities;

  @override
  Future<List<HelplineResource>> helplines() async => helplineResources;
}
