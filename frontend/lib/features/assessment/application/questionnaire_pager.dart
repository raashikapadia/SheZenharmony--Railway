import 'dart:math' as math;

import '../data/assessment_questionnaire.dart';

/// One question placed on a page, numbered both across the questionnaire
/// ("21") and within its section ("4 of 7") so the UI never recounts.
class PagedQuestion {
  const PagedQuestion({
    required this.question,
    required this.number,
    required this.numberInSection,
  });

  final AssessmentQuestion question;

  /// 1-based, in the order the user meets the questions.
  final int number;

  /// 1-based within the question's section.
  final int numberInSection;
}

/// One screen of the questionnaire: a run of questions from a single section.
///
/// A page belongs to exactly one section — the admin's grouping is the one
/// boundary the pager never crosses. A long section spans several pages, and
/// [pageInSection] / [pagesInSection] say which of them this is.
class QuestionnairePage {
  const QuestionnairePage({
    required this.section,
    required this.sectionIndex,
    required this.sectionQuestionCount,
    required this.pageInSection,
    required this.pagesInSection,
    required this.questions,
  });

  /// Null only when the questionnaire has no sections at all.
  final AssessmentSection? section;

  /// 0-based index of [section] among the sections actually rendered.
  final int sectionIndex;

  final int sectionQuestionCount;

  /// 0-based.
  final int pageInSection;
  final int pagesInSection;

  final List<PagedQuestion> questions;

  int get questionCount => questions.length;
  bool get isFirstInSection => pageInSection == 0;
  bool get isLastInSection => pageInSection == pagesInSection - 1;

  /// The section-relative numbers this page covers, e.g. (8, 14).
  (int, int) get sectionRange =>
      (questions.first.numberInSection, questions.last.numberInSection);
}

/// Splits a questionnaire into pages the way a careful person would lay it
/// out by hand — without ever knowing how many questions or sections it has.
///
/// The structure is the admin's: sections in their configured order, each
/// holding its own questions in their configured order. The pager never
/// merges or reorders across sections; it only decides how many screens a
/// section needs and where the breaks fall.
///
/// Nothing here depends on a fixed count. Every question is given a *cost*:
/// an estimate of how much vertical room its card takes, driven by the answer
/// control it needs (a two-chip yes/no is cheap, a five-option list of tiles
/// is not) and by how long its wording is. A section whose total cost fits
/// the [targetCost] budget is one page; a longer one is spread evenly across
/// as few pages as the budget allows, so a 23-question section becomes
/// 8 / 8 / 7 rather than 10 / 10 / 3.
class QuestionnairePager {
  const QuestionnairePager({
    this.targetCost = defaultTargetCost,
    this.maxQuestionsPerPage = 10,
  });

  /// Roughly ten scale questions with chip answers.
  static const defaultTargetCost = 8.0;

  /// Cost budget per page. Comfortable for a phone at the default; a caller
  /// with more or less height can move it.
  final double targetCost;

  /// Summed costs drift by a few ulps; never let that add a page.
  static const _epsilon = 1e-9;

  /// A hard ceiling regardless of cost, so even the cheapest questions never
  /// turn into a wall.
  final int maxQuestionsPerPage;

  /// A page budget for a viewport of [height] logical pixels: fewer questions
  /// on a short phone, a few more where there is room to breathe.
  static double targetCostForHeight(double height) {
    if (height < 640) return 6;
    if (height < 820) return defaultTargetCost;
    return 10;
  }

  /// Estimated vertical cost of [question]'s card, in units of "one short
  /// scale question with a row or two of chips".
  static double costOf(AssessmentQuestion question) {
    final options = question.options.length;

    double cost;
    if (rendersAsChips(question)) {
      // Chips wrap to a line or two; yes/no is a single short line.
      cost = question.type == AssessmentQuestionType.yesNo || options <= 2
          ? 0.6
          : 0.8;
    } else {
      // Each option is its own full-width tile.
      cost = 0.5 + 0.4 * options;
    }

    // Long wording wraps to extra lines; count it, but gently.
    if (question.text.length > 110) cost += 0.25;
    if (question.text.length > 200) cost += 0.25;

    return cost;
  }

  /// Short labels on a small option set fit as a wrapped row of chips. Long
  /// labels or many options need a vertical list to stay readable.
  static bool rendersAsChips(AssessmentQuestion question) {
    final options = question.options;
    if (options.isEmpty || options.length > 6) return false;
    return options.every((option) => option.label.length <= 22);
  }

  List<QuestionnairePage> paginate(AssessmentQuestionnaire questionnaire) {
    final pages = <QuestionnairePage>[];
    var number = 0;

    for (final (sectionIndex, (section, questions)) in _groupBySection(
      questionnaire,
    ).indexed) {
      // Numbered in the order the user meets them — which follows the
      // section order, not whatever global position the rows happen to have.
      final numbered = [
        for (final (index, question) in questions.indexed)
          PagedQuestion(
            question: question,
            number: ++number,
            numberInSection: index + 1,
          ),
      ];

      final chunks = _chunkEvenly(numbered);
      for (final (pageInSection, chunk) in chunks.indexed) {
        pages.add(
          QuestionnairePage(
            section: section,
            sectionIndex: sectionIndex,
            sectionQuestionCount: questions.length,
            pageInSection: pageInSection,
            pagesInSection: chunks.length,
            questions: chunk,
          ),
        );
      }
    }

    return pages;
  }

  /// Questions grouped by section, sections in their configured order and
  /// questions in theirs. Questions without a section (or whose section is
  /// not among those served) form a trailing group of their own — still
  /// shown, because the server still expects their answers.
  List<(AssessmentSection?, List<AssessmentQuestion>)> _groupBySection(
    AssessmentQuestionnaire questionnaire,
  ) {
    final bySection = <int?, List<AssessmentQuestion>>{};
    final knownIds = {for (final s in questionnaire.sections) s.id};
    for (final question in questionnaire.questions) {
      final id = question.sectionId;
      bySection
          .putIfAbsent(knownIds.contains(id) ? id : null, () => [])
          .add(question);
    }

    return [
      for (final section in questionnaire.sections)
        if (bySection[section.id]?.isNotEmpty ?? false)
          (section, bySection[section.id]!),
      if (bySection[null]?.isNotEmpty ?? false) (null, bySection[null]!),
    ];
  }

  /// Splits one section into as few pages as the budget allows, with the
  /// questions spread evenly across them.
  List<List<PagedQuestion>> _chunkEvenly(List<PagedQuestion> questions) {
    final costs = [for (final q in questions) costOf(q.question)];
    final total = costs.fold(0.0, (sum, cost) => sum + cost);

    final pageCount = math.max(
      ((total - _epsilon) / targetCost).ceil(),
      (questions.length / maxQuestionsPerPage).ceil(),
    );
    if (pageCount <= 1) return [questions];

    final ideal = total / pageCount;
    final chunks = <List<PagedQuestion>>[];
    var current = <PagedQuestion>[];
    var currentCost = 0.0;

    for (final (index, item) in questions.indexed) {
      final remainingQuestions = questions.length - index;
      final remainingPages = pageCount - chunks.length;

      // Close the page when adding this question would overshoot the even
      // share by more than half its own cost — unless doing so would leave
      // a later page with nothing to hold.
      final overshoots = currentCost + costs[index] / 2 > ideal + _epsilon;
      final mustKeepFilling = remainingQuestions <= remainingPages - 1;
      final canClose = current.isNotEmpty && remainingPages > 1;
      if (canClose && overshoots && !mustKeepFilling) {
        chunks.add(current);
        current = [];
        currentCost = 0;
      }

      current.add(item);
      currentCost += costs[index];

      // A hard cap is a hard cap, even if the cost model disagrees.
      if (current.length == maxQuestionsPerPage && remainingQuestions > 1) {
        chunks.add(current);
        current = [];
        currentCost = 0;
      }
    }
    if (current.isNotEmpty) chunks.add(current);

    return chunks;
  }
}
