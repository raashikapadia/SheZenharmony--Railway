import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/core/network/api_service.dart';
import 'package:shezen_harmony/features/activities/data/support_content.dart';
import 'package:shezen_harmony/features/activities/presentation/positive_engagement_screen.dart';

void main() {
  testWidgets('shows the related link configured by the admin', (tester) async {
    await tester.pumpWidget(
      MaterialApp(home: PositiveEngagementScreen(apiService: _ContentApi())),
    );
    await tester.pumpAndSettle();

    await tester.tap(find.text('A gentle prompt'));
    await tester.pumpAndSettle();

    expect(find.text('https://example.com/resource'), findsOneWidget);
    expect(find.text('Open related link'), findsOneWidget);
  });
}

class _ContentApi extends ApiService {
  @override
  Future<List<PositiveContent>> positiveEngagement() async => const [
    PositiveContent(
      title: 'A gentle prompt',
      description: 'Pause for a moment.',
      contentType: 'motivation',
      instructions: 'Choose one kind next step.',
      externalUrl: 'https://example.com/resource',
    ),
  ];
}
