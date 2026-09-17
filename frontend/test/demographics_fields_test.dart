import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/shared/data/reference_data.dart';
import 'package:shezen_harmony/shared/widgets/form_fields.dart';

void main() {
  group('date of birth', () {
    // Fixed so the 18th-birthday boundary is exact, whatever day tests run.
    final today = DateTime(2026, 9, 18, 10, 30);
    const tooYoung = 'You must be 18 years or older to participate.';

    test('nothing chosen is asked for', () {
      expect(validateDateOfBirth(null, today: today), isNotNull);
    });

    test('under 18 is rejected with a plain message', () {
      expect(validateDateOfBirth(DateTime(2010, 5, 5), today: today), tooYoung);
      expect(
        validateDateOfBirth(DateTime(2009, 3, 15), today: today),
        tooYoung,
      );
    });

    test('exactly 18 today is accepted', () {
      expect(validateDateOfBirth(DateTime(2008, 9, 18), today: today), isNull);
    });

    test('born in 2008 but the birthday has not come round yet is rejected', () {
      expect(
        validateDateOfBirth(DateTime(2008, 9, 19), today: today),
        tooYoung,
      );
      expect(
        validateDateOfBirth(DateTime(2008, 12, 31), today: today),
        tooYoung,
      );
    });

    test('born in 2008 and already 18 is accepted', () {
      expect(validateDateOfBirth(DateTime(2008, 1, 1), today: today), isNull);
      expect(validateDateOfBirth(DateTime(2008, 9, 17), today: today), isNull);
    });

    test('a date in the future is rejected', () {
      expect(
        validateDateOfBirth(DateTime(2026, 9, 19), today: today),
        'Date of birth cannot be in the future.',
      );
      expect(
        validateDateOfBirth(DateTime(2030, 1, 1), today: today),
        'Date of birth cannot be in the future.',
      );
    });

    test('a 29 February birthday turns 18 on 1 March in a common year', () {
      final leapBaby = DateTime(2008, 2, 29);
      expect(
        validateDateOfBirth(leapBaby, today: DateTime(2026, 2, 28)),
        tooYoung,
      );
      expect(validateDateOfBirth(leapBaby, today: DateTime(2026, 3, 1)), isNull);
    });

    testWidgets('the field blocks the form until the date is acceptable', (
      tester,
    ) async {
      final formKey = GlobalKey<FormState>();
      DateTime? chosen = DateTime(2010, 5, 5);
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: StatefulBuilder(
              builder: (context, setState) => Form(
                key: formKey,
                child: DateOfBirthField(
                  value: chosen,
                  today: today,
                  onChanged: (value) => setState(() => chosen = value),
                ),
              ),
            ),
          ),
        ),
      );

      expect(formKey.currentState!.validate(), isFalse);
      await tester.pump();
      expect(find.text(tooYoung), findsOneWidget);
    });

    testWidgets('an 18-year-old passes the field', (tester) async {
      final formKey = GlobalKey<FormState>();
      await tester.pumpWidget(
        MaterialApp(
          home: Scaffold(
            body: Form(
              key: formKey,
              child: DateOfBirthField(
                value: DateTime(2008, 9, 18),
                today: today,
                onChanged: (_) {},
              ),
            ),
          ),
        ),
      );

      expect(formKey.currentState!.validate(), isTrue);
      await tester.pump();
      expect(find.text(tooYoung), findsNothing);
    });
  });

  group('country list', () {
    test('covers the world, not just the region', () {
      expect(ReferenceData.countries.length, greaterThan(240));
      for (final required in const [
        'Australia',
        'Fiji',
        'New Zealand',
        'Samoa',
        'Tonga',
        'United States',
        'Afghanistan',
        'Zimbabwe',
        'Brazil',
        'Germany',
        'India',
        'Japan',
        'Nigeria',
        'Papua New Guinea',
        'Vanuatu',
        'Kiribati',
        'Tuvalu',
      ]) {
        expect(ReferenceData.countries, contains(required));
      }
    });

    test('is alphabetical with no duplicates', () {
      String key(String name) => name
          .toLowerCase()
          .replaceAll('å', 'a')
          .replaceAll('ã', 'a')
          .replaceAll('ô', 'o')
          .replaceAll('é', 'e')
          .replaceAll('ç', 'c')
          .replaceAll('ü', 'u')
          .replaceAll('í', 'i');
      final sorted = [...ReferenceData.countries]
        ..sort((a, b) => key(a).compareTo(key(b)));
      expect(ReferenceData.countries, sorted);
      expect(
        ReferenceData.countries.toSet().length,
        ReferenceData.countries.length,
      );
    });
  });

  group('country field', () {
    Widget field(TextEditingController controller) => MaterialApp(
      home: Scaffold(
        body: Form(child: CountryField(controller: controller)),
      ),
    );

    testWidgets('the form shows a search prompt, not the whole list', (
      tester,
    ) async {
      final controller = TextEditingController();
      addTearDown(controller.dispose);
      await tester.pumpWidget(field(controller));

      expect(find.text('Country'), findsOneWidget);
      expect(find.text('Search country...'), findsOneWidget);
      expect(find.byType(ListTile), findsNothing);
      expect(find.text('Australia'), findsNothing);
      expect(find.text('Zimbabwe'), findsNothing);
    });

    testWidgets('typing narrows the list and a tap selects', (tester) async {
      final controller = TextEditingController();
      addTearDown(controller.dispose);
      await tester.pumpWidget(field(controller));

      await tester.tap(find.byKey(const Key('country-field')));
      await tester.pumpAndSettle();
      expect(find.byKey(const Key('country-search')), findsOneWidget);

      await tester.enterText(find.byKey(const Key('country-search')), 'fij');
      await tester.pumpAndSettle();
      expect(find.widgetWithText(ListTile, 'Fiji'), findsOneWidget);
      expect(find.widgetWithText(ListTile, 'Australia'), findsNothing);

      await tester.tap(find.widgetWithText(ListTile, 'Fiji'));
      await tester.pumpAndSettle();
      expect(controller.text, 'Fiji');
      // The chosen country sits in the field with a clear selected state.
      expect(find.text('Fiji'), findsOneWidget);
      expect(find.byIcon(Icons.check_circle_rounded), findsOneWidget);
      expect(find.byKey(const Key('country-search')), findsNothing);
    });

    testWidgets('search ignores accents and reports no matches plainly', (
      tester,
    ) async {
      final controller = TextEditingController();
      addTearDown(controller.dispose);
      await tester.pumpWidget(field(controller));
      await tester.tap(find.byKey(const Key('country-field')));
      await tester.pumpAndSettle();

      await tester.enterText(find.byKey(const Key('country-search')), 'cote');
      await tester.pumpAndSettle();
      expect(find.widgetWithText(ListTile, "Côte d'Ivoire"), findsOneWidget);

      await tester.enterText(find.byKey(const Key('country-search')), 'zzzz');
      await tester.pumpAndSettle();
      expect(find.byType(ListTile), findsNothing);
      expect(find.text('No countries match your search.'), findsOneWidget);
    });

    testWidgets('reopening the sheet marks the current choice', (
      tester,
    ) async {
      final controller = TextEditingController(text: 'Tonga');
      addTearDown(controller.dispose);
      await tester.pumpWidget(field(controller));
      await tester.tap(find.byKey(const Key('country-field')));
      await tester.pumpAndSettle();

      await tester.enterText(find.byKey(const Key('country-search')), 'tonga');
      await tester.pumpAndSettle();
      final tile = tester.widget<ListTile>(
        find.widgetWithText(ListTile, 'Tonga'),
      );
      expect(tile.selected, isTrue);
      expect(find.byIcon(Icons.check_rounded), findsOneWidget);
    });
  });

  group('year of study', () {
    Widget field({
      required GlobalKey<FormState> formKey,
      required TextEditingController year,
      required TextEditingController detail,
    }) => MaterialApp(
      home: Scaffold(
        body: SingleChildScrollView(
          child: Form(
            key: formKey,
            child: YearOfStudyField(controller: year, detailController: detail),
          ),
        ),
      ),
    );

    testWidgets('offers the fixed options and nothing extra', (tester) async {
      final formKey = GlobalKey<FormState>();
      final year = TextEditingController();
      final detail = TextEditingController();
      addTearDown(year.dispose);
      addTearDown(detail.dispose);
      await tester.pumpWidget(field(formKey: formKey, year: year, detail: detail));

      for (final option in ReferenceData.yearOfStudy) {
        expect(find.widgetWithText(ChoiceChip, option), findsOneWidget);
      }
      expect(find.text('Please specify'), findsNothing);
      expect(find.byKey(const Key('year-of-study-detail')), findsNothing);

      await tester.tap(find.widgetWithText(ChoiceChip, 'Year 2'));
      await tester.pumpAndSettle();
      expect(find.text('Please specify'), findsNothing);
      expect(formKey.currentState!.validate(), isTrue);
    });

    testWidgets('"Other" reveals a required specification', (tester) async {
      final formKey = GlobalKey<FormState>();
      final year = TextEditingController();
      final detail = TextEditingController();
      addTearDown(year.dispose);
      addTearDown(detail.dispose);
      await tester.pumpWidget(field(formKey: formKey, year: year, detail: detail));

      await tester.tap(find.widgetWithText(ChoiceChip, 'Other'));
      await tester.pumpAndSettle();
      expect(find.text('Please specify'), findsOneWidget);
      expect(find.text('Enter your year/status...'), findsOneWidget);

      expect(formKey.currentState!.validate(), isFalse);
      await tester.pump();
      expect(find.text('Please specify your year of study.'), findsOneWidget);

      await tester.enterText(
        find.byKey(const Key('year-of-study-detail')),
        'Part-time diploma',
      );
      expect(formKey.currentState!.validate(), isTrue);
      expect(detail.text, 'Part-time diploma');
    });

    testWidgets('moving off "Other" hides and clears the specification', (
      tester,
    ) async {
      final formKey = GlobalKey<FormState>();
      final year = TextEditingController();
      final detail = TextEditingController();
      addTearDown(year.dispose);
      addTearDown(detail.dispose);
      await tester.pumpWidget(field(formKey: formKey, year: year, detail: detail));

      await tester.tap(find.widgetWithText(ChoiceChip, 'Other'));
      await tester.pumpAndSettle();
      await tester.enterText(
        find.byKey(const Key('year-of-study-detail')),
        'Part-time diploma',
      );
      expect(detail.text, 'Part-time diploma');

      await tester.tap(find.widgetWithText(ChoiceChip, 'Postgraduate'));
      await tester.pumpAndSettle();
      expect(year.text, 'Postgraduate');
      expect(detail.text, isEmpty);
      expect(find.byKey(const Key('year-of-study-detail')), findsNothing);
      expect(formKey.currentState!.validate(), isTrue);
    });
  });
}
