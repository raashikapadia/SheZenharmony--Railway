import 'package:flutter_test/flutter_test.dart';
import 'package:shezen_harmony/features/assessment/application/questionnaire_pager.dart';
import 'package:shezen_harmony/features/assessment/data/assessment_questionnaire.dart';

const _likert = [
  AssessmentOption(id: 1, label: 'Strongly disagree', value: 'sd'),
  AssessmentOption(id: 2, label: 'Disagree', value: 'd'),
  AssessmentOption(id: 3, label: 'Neutral', value: 'n'),
  AssessmentOption(id: 4, label: 'Agree', value: 'a'),
  AssessmentOption(id: 5, label: 'Strongly agree', value: 'sa'),
];

const _yesNo = [
  AssessmentOption(id: 1, label: 'Yes', value: 'yes'),
  AssessmentOption(id: 2, label: 'No', value: 'no'),
];

const _longChoices = [
  AssessmentOption(id: 1, label: 'Almost every day of the week', value: 'a'),
  AssessmentOption(id: 2, label: 'A few times a week', value: 'b'),
  AssessmentOption(id: 3, label: 'Once a week or less often', value: 'c'),
  AssessmentOption(id: 4, label: 'Hardly ever, if at all', value: 'd'),
];

/// Builds a questionnaire with the given section sizes, all scale questions.
AssessmentQuestionnaire _questionnaire(
  List<int> sectionSizes, {
  List<AssessmentOption> options = _likert,
  AssessmentQuestionType type = AssessmentQuestionType.scale,
}) {
  final sections = <AssessmentSection>[];
  final questions = <AssessmentQuestion>[];
  var position = 0;
  for (final (index, size) in sectionSizes.indexed) {
    final sectionId = index + 1;
    sections.add(
      AssessmentSection(
        id: sectionId,
        title: 'Section $sectionId',
        position: sectionId,
      ),
    );
    for (var i = 0; i < size; i++) {
      position++;
      questions.add(
        AssessmentQuestion(
          id: position,
          text: 'Question $position',
          required: true,
          position: position,
          options: options,
          type: type,
          sectionId: sectionId,
        ),
      );
    }
  }
  return AssessmentQuestionnaire(
    id: 1,
    title: 'Test',
    questions: questions,
    sections: sections,
  );
}

Matcher inRange(int low, int high) =>
    allOf(greaterThanOrEqualTo(low), lessThanOrEqualTo(high));

List<int> _sizes(List<QuestionnairePage> pages) => [
  for (final page in pages) page.questionCount,
];

void main() {
  const pager = QuestionnairePager();

  test('the 74-question, 8-section instrument lands in sensible pages', () {
    final pages = pager.paginate(_questionnaire([10, 10, 8, 9, 9, 9, 9, 10]));

    // Every question appears exactly once, in order.
    final numbers = [
      for (final page in pages)
        for (final item in page.questions) item.number,
    ];
    expect(numbers, List.generate(74, (i) => i + 1));

    // At the default budget each section is one page — "Section 3 of 8".
    expect(_sizes(pages), [10, 10, 8, 9, 9, 9, 9, 10]);
    for (final (index, page) in pages.indexed) {
      expect(page.sectionIndex, index);
    }

    // On a short phone the same data spreads over more, smaller pages, and
    // still never leaves a stub.
    final short = const QuestionnairePager(
      targetCost: 6,
    ).paginate(_questionnaire([10, 10, 8, 9, 9, 9, 9, 10]));
    expect(short.length, greaterThan(pages.length));
    for (final page in short) {
      expect(page.questionCount, inRange(4, 10));
    }
  });

  test('a long section is spread evenly rather than leaving a stub', () {
    final pages = pager.paginate(_questionnaire([23]));

    expect(_sizes(pages), [8, 8, 7]);
  });

  test('sections never share a page, however small they are', () {
    final pages = pager.paginate(_questionnaire([4, 3]));

    expect(_sizes(pages), [4, 3]);
    expect(pages.first.section?.title, 'Section 1');
    expect(pages.last.section?.title, 'Section 2');
  });

  test('the admin example — 7 / 14 / 5 — keeps its boundaries', () {
    final pages = pager.paginate(_questionnaire([7, 14, 5]));

    expect(_sizes(pages), [7, 7, 7, 5]);
    expect([for (final p in pages) p.sectionIndex], [0, 1, 1, 2]);
    expect([for (final p in pages) p.pageInSection], [0, 0, 1, 0]);
    expect([for (final p in pages) p.pagesInSection], [1, 2, 2, 1]);
    expect(pages[1].sectionRange, (1, 7));
    expect(pages[2].sectionRange, (8, 14));
    expect(pages[2].isLastInSection, isTrue);
    expect(pages[1].isLastInSection, isFalse);
  });

  test('sections follow their own order, and numbering follows the user', () {
    // Sections served out of storage order: the admin moved "Later" first.
    final questionnaire = AssessmentQuestionnaire(
      id: 1,
      title: 'Reordered',
      sections: const [
        AssessmentSection(id: 20, title: 'Later', position: 1),
        AssessmentSection(id: 10, title: 'Earlier', position: 2),
      ],
      questions: [
        for (var i = 1; i <= 3; i++)
          AssessmentQuestion(
            id: i,
            text: 'Earlier $i',
            required: true,
            position: i,
            options: _likert,
            sectionId: 10,
          ),
        for (var i = 4; i <= 5; i++)
          AssessmentQuestion(
            id: i,
            text: 'Later $i',
            required: true,
            position: i,
            options: _likert,
            sectionId: 20,
          ),
      ],
    );

    final pages = pager.paginate(questionnaire);
    expect([for (final p in pages) p.section?.title], ['Later', 'Earlier']);
    expect(
      [
        for (final p in pages)
          for (final q in p.questions) q.number,
      ],
      [1, 2, 3, 4, 5],
    );
    expect(pages.first.questions.first.question.text, 'Later 4');
  });

  test('questions whose section is missing are still shown, at the end', () {
    final questionnaire = AssessmentQuestionnaire(
      id: 1,
      title: 'Orphan',
      sections: const [AssessmentSection(id: 1, title: 'Only', position: 1)],
      questions: [
        AssessmentQuestion(
          id: 1,
          text: 'Orphan',
          required: true,
          position: 1,
          options: _likert,
          sectionId: 99,
        ),
        AssessmentQuestion(
          id: 2,
          text: 'Placed',
          required: true,
          position: 2,
          options: _likert,
          sectionId: 1,
        ),
      ],
    );

    final pages = pager.paginate(questionnaire);
    expect([for (final p in pages) p.section?.title], ['Only', null]);
    expect(pages.last.questions.single.question.text, 'Orphan');
  });

  test('cheap yes/no questions fit more per page, up to the cap', () {
    final pages = pager.paginate(
      _questionnaire([30], options: _yesNo, type: AssessmentQuestionType.yesNo),
    );

    expect(_sizes(pages), [10, 10, 10]);
  });

  test('tall multiple-choice questions get fewer per page', () {
    final pages = pager.paginate(
      _questionnaire(
        [12],
        options: _longChoices,
        type: AssessmentQuestionType.multipleChoice,
      ),
    );

    for (final page in pages) {
      expect(page.questionCount, lessThanOrEqualTo(4));
    }
    expect(pages.length, greaterThanOrEqualTo(3));
  });

  test('a questionnaire without sections still pages', () {
    final questionnaire = AssessmentQuestionnaire(
      id: 1,
      title: 'Flat',
      questions: [
        for (var i = 1; i <= 17; i++)
          AssessmentQuestion(
            id: i,
            text: 'Q$i',
            required: true,
            position: i,
            options: _likert,
          ),
      ],
    );

    final pages = pager.paginate(questionnaire);
    expect(_sizes(pages), [9, 8]);
    expect(pages.first.section, isNull);
  });

  test('a shorter screen gets a smaller budget, a taller one more', () {
    expect(
      QuestionnairePager.targetCostForHeight(600),
      lessThan(QuestionnairePager.targetCostForHeight(740)),
    );
    expect(
      QuestionnairePager.targetCostForHeight(740),
      lessThan(QuestionnairePager.targetCostForHeight(1000)),
    );

    final short = const QuestionnairePager(
      targetCost: 6,
    ).paginate(_questionnaire([20]));
    final tall = const QuestionnairePager(
      targetCost: 10,
    ).paginate(_questionnaire([20]));
    expect(short.length, greaterThan(tall.length));
  });

  test('chips are only for short option sets', () {
    AssessmentQuestion withOptions(List<AssessmentOption> options) =>
        AssessmentQuestion(
          id: 1,
          text: 'Q',
          required: true,
          position: 1,
          options: options,
        );

    expect(QuestionnairePager.rendersAsChips(withOptions(_likert)), isTrue);
    expect(QuestionnairePager.rendersAsChips(withOptions(_yesNo)), isTrue);
    expect(
      QuestionnairePager.rendersAsChips(withOptions(_longChoices)),
      isFalse,
    );
    expect(QuestionnairePager.rendersAsChips(withOptions(const [])), isFalse);
  });

  test('an empty questionnaire has no pages', () {
    expect(pager.paginate(_questionnaire([])), isEmpty);
  });
}
