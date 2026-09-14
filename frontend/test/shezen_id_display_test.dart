import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/shared/widgets/app_ui.dart';

void main() {
  test('a full SheZen ID is elided down to its recognisable ends', () {
    const full = 'SZ-787F89681D594B01A956479A239175FD';

    expect(shortShezenId(full), 'SZ-787F…75FD');
    // Short enough to fit a phone line, where the 35-character original is not.
    expect(shortShezenId(full).length, lessThan(full.length));
  });

  test('the identifier itself is untouched', () {
    const full = 'SZ-787F89681D594B01A956479A239175FD';

    // The helper only formats; nothing about the stored value changes.
    expect(full.length, 35);
    expect(shortShezenId(full), isNot(full));
  });

  test('an id short enough to read whole is left alone', () {
    expect(shortShezenId('SZ-ABCD1234'), 'SZ-ABCD1234');
    expect(shortShezenId(''), '');
  });

  test('a value without the SZ- prefix still elides safely', () {
    expect(shortShezenId('787F89681D594B01A956479A239175FD'), 'SZ-787F…75FD');
  });

  test('surrounding whitespace does not leak into the display', () {
    expect(
      shortShezenId('  SZ-787F89681D594B01A956479A239175FD  '),
      'SZ-787F…75FD',
    );
  });

  testWidgets('the identity card fits a narrow phone and keeps the full id', (
    tester,
  ) async {
    const full = 'SZ-787F89681D594B01A956479A239175FD';
    tester.view.physicalSize = const Size(320, 700);
    tester.view.devicePixelRatio = 1;
    addTearDown(tester.view.resetPhysicalSize);
    addTearDown(tester.view.resetDevicePixelRatio);

    // A RenderFlex overflow throws during the pump, so reaching the
    // expectations is itself the assertion that the card fits.
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(body: AppIdentityCard(shezenId: full)),
      ),
    );

    expect(find.text('SZ-787F…75FD'), findsOneWidget);
    expect(find.text(full), findsNothing);

    // The whole identifier is still reachable, just not drawn.
    expect(find.byTooltip('Copy your full SheZen ID'), findsOneWidget);
  });
}
