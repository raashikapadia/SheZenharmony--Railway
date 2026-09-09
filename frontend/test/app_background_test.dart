import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/core/theme/app_theme.dart';
import 'package:shezen_harmony/shared/widgets/app_ui.dart';

void main() {
  testWidgets('the artwork sits behind the content, not over it', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: const AppBackground(child: Text('content')),
      ),
    );

    expect(find.byType(Image), findsOneWidget);
    expect(find.text('content'), findsOneWidget);

    // The child is painted last, so it is never behind the veil.
    final stack = tester.widget<Stack>(find.byType(Stack).first);
    expect(stack.children.last, isA<Text>());
  });

  testWidgets('a missing artwork file still leaves a usable screen', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        theme: AppTheme.light,
        home: const AppBackground(child: Text('content')),
      ),
    );

    // In tests the asset bundle has no images, so the error builder runs. The
    // background is decoration: losing it must never cost the content.
    await tester.pump();

    expect(find.text('content'), findsOneWidget);
    expect(tester.takeException(), isNull);
  });
}
