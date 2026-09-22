import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../../auth/application/auth_provider.dart';
import '../application/assessment_provider.dart';
import '../application/questionnaire_pager.dart';
import '../data/assessment_questionnaire.dart';
import 'assessment_result_screen.dart';

/// Takes one questionnaire: the registration baseline when [questionnaireId]
/// is null (the mandatory first check-in and its optional repeat), or the
/// library questionnaire with that id. Everything shown — title, sections,
/// questions, answer controls — comes from the loaded configuration.
class QuestionnaireScreen extends StatelessWidget {
  const QuestionnaireScreen({
    super.key,
    required this.mandatory,
    this.questionnaireId,
    ApiService? apiService,
  }) : _injectedApiService = apiService;

  final bool mandatory;
  final int? questionnaireId;
  final ApiService? _injectedApiService;

  @override
  Widget build(BuildContext context) {
    final token = context.read<AuthProvider>().session!.token;
    // Size the pages to this screen once, up front. A rotation mid-way keeps
    // the pages it has, so nothing shifts under the user.
    final budget = QuestionnairePager.targetCostForHeight(
      MediaQuery.sizeOf(context).height,
    );
    return ChangeNotifierProvider(
      create: (_) => AssessmentProvider(
        apiService: _injectedApiService ?? ApiService(),
        token: token,
        questionnaireId: questionnaireId,
        closeApiServiceOnDispose: _injectedApiService == null,
        pager: QuestionnairePager(targetCost: budget),
      )..load(),
      child: _QuestionnaireView(mandatory: mandatory),
    );
  }
}

class _QuestionnaireView extends StatefulWidget {
  const _QuestionnaireView({required this.mandatory});

  final bool mandatory;

  @override
  State<_QuestionnaireView> createState() => _QuestionnaireViewState();
}

class _QuestionnaireViewState extends State<_QuestionnaireView> {
  bool _started = false;

  Future<void> _submit(BuildContext context) async {
    final provider = context.read<AssessmentProvider>();
    if (!await provider.submit() || !context.mounted) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              provider.submitError ??
                  'Please answer each required question before submitting.',
            ),
          ),
        );
      }
      return;
    }

    final resultScreen = AssessmentResultScreen(
      result: provider.result!,
      mandatory: widget.mandatory,
    );
    if (widget.mandatory) {
      await Navigator.of(
        context,
      ).push(MaterialPageRoute(builder: (_) => resultScreen));
    } else {
      context.read<AuthProvider>().markAssessmentCompleted();
      await Navigator.of(
        context,
      ).pushReplacement(MaterialPageRoute(builder: (_) => resultScreen));
    }
  }

  /// Leaving mid-way throws the answers so far away, so an optional check-in
  /// with progress asks first. The mandatory one cannot be left at all.
  Future<void> _onPopBlocked(BuildContext context) async {
    if (widget.mandatory) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Complete this check-in to continue to SheZen.'),
        ),
      );
      return;
    }

    final leave = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Leave this check-in?'),
        content: const Text(
          'Your answers so far won\'t be saved. You can start again any time.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Keep going'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            child: const Text('Leave'),
          ),
        ],
      ),
    );
    if (leave == true && context.mounted) Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    final hasProgress =
        _started &&
        context.select<AssessmentProvider, bool>(
          (provider) => provider.answeredCount > 0 && !provider.isSubmitting,
        );
    return PopScope(
      canPop: !widget.mandatory && !hasProgress,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) _onPopBlocked(context);
      },
      child: Scaffold(
        appBar: AppBar(
          // The questionnaire's own title once it has loaded; a neutral
          // fallback until then.
          title: Text(
            context.select<AssessmentProvider, String?>(
                  (provider) => provider.questionnaire?.title,
                ) ??
                (widget.mandatory ? 'Your first check-in' : 'Check-in'),
          ),
          automaticallyImplyLeading: !widget.mandatory,
        ),
        body: Consumer<AssessmentProvider>(
          builder: (context, provider, _) {
            return switch (provider.state) {
              AssessmentLoadState.loading => const _LoadingView(),
              AssessmentLoadState.error => _ErrorView(
                message: provider.errorMessage,
                onRetry: provider.load,
              ),
              AssessmentLoadState.loaded =>
                !_started
                    ? _AssessmentIntro(
                        provider: provider,
                        mandatory: widget.mandatory,
                        onStart: () => setState(() => _started = true),
                      )
                    : _LoadedQuestionnaire(
                        provider: provider,
                        title: provider.questionnaire?.title,
                        mandatory: widget.mandatory,
                        onSubmit: () => _submit(context),
                      ),
            };
          },
        ),
      ),
    );
  }
}

class _AssessmentIntro extends StatelessWidget {
  const _AssessmentIntro({
    required this.provider,
    required this.mandatory,
    required this.onStart,
  });

  final AssessmentProvider provider;
  final bool mandatory;
  final VoidCallback onStart;

  /// "74 questions across 8 sections, in 9 short pages · about 10 minutes" —
  /// so the user knows the shape of what is coming before a wall of
  /// questions can ever appear.
  static String _lengthSummary(AssessmentProvider provider) {
    final questions = provider.questionCount;
    final sections = provider.renderedSectionCount;
    final pages = provider.pageCount;
    final minutes = provider.questionnaire?.estimatedMinutes;
    var summary = '$questions ${questions == 1 ? 'question' : 'questions'}';
    if (sections > 1) summary += ' across $sections sections';
    if (pages > 1) summary += ', in $pages short pages';
    if (minutes != null && minutes > 0) {
      summary += ' · about $minutes ${minutes == 1 ? 'minute' : 'minutes'}';
    }
    return summary;
  }

  @override
  Widget build(BuildContext context) {
    final questionnaire = provider.questionnaire;
    return SafeArea(
      child: Center(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(24, 12, 24, 32),
          child: ConstrainedBox(
            constraints: const BoxConstraints(maxWidth: 520),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const Align(
                  child: CircleAvatar(
                    radius: 38,
                    backgroundColor: AppColors.softLavender,
                    child: Icon(
                      Icons.monitor_heart_outlined,
                      size: 36,
                      color: AppColors.primary,
                    ),
                  ),
                ),
                const SizedBox(height: 24),
                Text(
                  questionnaire?.title.isNotEmpty == true
                      ? questionnaire!.title
                      : (mandatory
                            ? 'Let\'s begin with a check-in'
                            : 'Take a moment to check in'),
                  textAlign: TextAlign.center,
                  style: Theme.of(context).textTheme.headlineSmall,
                ),
                const SizedBox(height: 10),
                Text(
                  questionnaire?.description?.isNotEmpty == true
                      ? questionnaire!.description!
                      : 'Choose the response that feels most true for you right now.',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    color: Theme.of(context).colorScheme.onSurfaceVariant,
                  ),
                ),
                const SizedBox(height: 26),
                Card(
                  child: Padding(
                    padding: const EdgeInsets.all(18),
                    child: Column(
                      children: [
                        _IntroRow(
                          icon: Icons.format_list_numbered_rounded,
                          text: _lengthSummary(provider),
                        ),
                        const SizedBox(height: 14),
                        const _IntroRow(
                          icon: Icons.lock_outline_rounded,
                          text:
                              'Answers are saved with your SheZen ID, not shown with your university email',
                        ),
                        const SizedBox(height: 14),
                        const _IntroRow(
                          icon: Icons.health_and_safety_outlined,
                          text:
                              'Your result supports reflection and is not a medical diagnosis',
                        ),
                      ],
                    ),
                  ),
                ),
                if (mandatory) ...[
                  const SizedBox(height: 16),
                  const Text(
                    'This initial check is required before entering the rest of SheZen.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppColors.muted),
                  ),
                ],
                const SizedBox(height: 28),
                FilledButton.icon(
                  onPressed: provider.questionCount == 0 ? null : onStart,
                  icon: const Icon(Icons.arrow_forward_rounded),
                  label: const Text('Begin'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _IntroRow extends StatelessWidget {
  const _IntroRow({required this.icon, required this.text});
  final IconData icon;
  final String text;

  @override
  Widget build(BuildContext context) => Row(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Icon(icon, size: 21, color: Theme.of(context).colorScheme.primary),
      const SizedBox(width: 12),
      Expanded(child: Text(text)),
    ],
  );
}

/// The paged questionnaire: a quiet progress strip, the current page's
/// questions grouped under their section headings, and Back / Next below.
///
/// Every dimension of this layout — how many pages, which questions share
/// one, how each is answered — comes from the loaded questionnaire via
/// [QuestionnairePager]; nothing assumes a particular question count.
class _LoadedQuestionnaire extends StatefulWidget {
  const _LoadedQuestionnaire({
    required this.provider,
    required this.title,
    required this.mandatory,
    required this.onSubmit,
  });

  final AssessmentProvider provider;
  final String? title;
  final bool mandatory;
  final VoidCallback onSubmit;

  @override
  State<_LoadedQuestionnaire> createState() => _LoadedQuestionnaireState();
}

class _LoadedQuestionnaireState extends State<_LoadedQuestionnaire> {
  /// Set when the user tries to move on with required questions unanswered,
  /// so those cards can say so. Cleared as soon as the page changes.
  bool _showMissing = false;

  /// Whether the last page change was forwards, for the slide direction.
  bool _forward = true;

  /// The section just finished, while its "complete" moment is on screen in
  /// place of the next page. Null the rest of the time.
  AssessmentSection? _completedSection;

  /// One key per question on the page, so a missing answer can be scrolled
  /// into view.
  final Map<int, GlobalKey> _questionKeys = {};

  GlobalKey _keyFor(int questionId) =>
      _questionKeys.putIfAbsent(questionId, GlobalKey.new);

  void _next() {
    final provider = widget.provider;

    // Continue past the section-complete moment onto the page behind it.
    if (_completedSection != null) {
      setState(() => _completedSection = null);
      return;
    }

    final leaving = provider.currentPage;
    if (provider.goNext()) {
      final entering = provider.currentPage;
      // Crossing from one section into another is worth a pause — but only
      // between two real sections, never for a sectionless tail.
      final crossedSection =
          leaving != null &&
          entering != null &&
          leaving.isLastInSection &&
          leaving.section != null &&
          entering.section != null &&
          entering.section!.id != leaving.section!.id;
      setState(() {
        _showMissing = false;
        _forward = true;
        _completedSection = crossedSection ? leaving.section : null;
      });
      return;
    }

    final missing = provider.unansweredOnCurrentPage;
    if (missing.isEmpty) return;
    setState(() => _showMissing = true);

    final target = _questionKeys[missing.first.question.id]?.currentContext;
    if (target != null) {
      Scrollable.ensureVisible(
        target,
        alignment: 0.1,
        duration: const Duration(milliseconds: 320),
        curve: Curves.easeOutCubic,
      );
    }
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(
        SnackBar(
          content: Text(
            missing.length == 1
                ? 'One question on this page still needs an answer.'
                : '${missing.length} questions on this page still need an answer.',
          ),
        ),
      );
  }

  void _previous() {
    widget.provider.goPrevious();
    setState(() {
      _showMissing = false;
      _forward = false;
      _completedSection = null;
    });
  }

  void _submit() {
    final provider = widget.provider;
    if (_completedSection != null) {
      _next();
      return;
    }
    if (provider.unansweredOnCurrentPage.isNotEmpty) {
      _next(); // Same nudge as Next: point at what is missing.
      return;
    }
    widget.onSubmit();
  }

  @override
  Widget build(BuildContext context) {
    final provider = widget.provider;
    final page = provider.currentPage;
    if (page == null) {
      return const _CenteredMessage(
        icon: Icons.assignment_outlined,
        message: 'No questions are available right now.',
      );
    }

    final completed = _completedSection;
    final bodyKey = completed == null
        ? ValueKey<Object>(provider.pageIndex)
        : ValueKey<Object>('complete-${completed.id}');

    return SafeArea(
      child: Column(
        children: [
          if (completed == null) _ProgressStrip(provider: provider),
          Expanded(
            child: AnimatedSwitcher(
              duration: const Duration(milliseconds: 280),
              switchInCurve: Curves.easeOutCubic,
              switchOutCurve: Curves.easeInCubic,
              transitionBuilder: (child, animation) {
                final incoming = child.key == bodyKey;
                final offset = Tween<Offset>(
                  begin: Offset(incoming == _forward ? 0.06 : -0.06, 0),
                  end: Offset.zero,
                ).animate(animation);
                return FadeTransition(
                  opacity: animation,
                  child: SlideTransition(position: offset, child: child),
                );
              },
              layoutBuilder: (current, previous) => Stack(
                alignment: Alignment.topCenter,
                children: [...previous, ?current],
              ),
              child: completed != null
                  ? _SectionComplete(
                      key: bodyKey,
                      completed: completed,
                      next: page.section!,
                      provider: provider,
                    )
                  : _PageBody(
                      key: bodyKey,
                      page: page,
                      provider: provider,
                      showMissing: _showMissing,
                      keyFor: _keyFor,
                      title: widget.title,
                      mandatory: widget.mandatory,
                    ),
            ),
          ),
          _NavigationBar(
            provider: provider,
            nextLabel: completed != null ? 'Continue' : 'Next',
            // The moment between sections is never the last page.
            showSubmit: completed == null && provider.isLastPage,
            onPrevious: _previous,
            onNext: _next,
            onSubmit: _submit,
          ),
        ],
      ),
    );
  }
}

/// Where the user is: which section, which of its questions this page
/// holds, and how far through the whole questionnaire they are. Every value
/// is read off the loaded questionnaire; nothing is assumed about its size.
class _ProgressStrip extends StatelessWidget {
  const _ProgressStrip({required this.provider});

  final AssessmentProvider provider;

  static const _encouragement = [
    'One step at a time.',
    "You're doing great.",
    "You're making progress.",
    'Take your time — there are no wrong answers.',
    'Nearly there.',
  ];

  String get _microcopy {
    if (provider.isLastPage && provider.pageCount > 1) return 'Nearly there.';
    if (provider.pageIndex == 0) return _encouragement.first;
    // Cycle the middle lines; the first and last are reserved.
    final middle = _encouragement.sublist(1, _encouragement.length - 1);
    return middle[(provider.pageIndex - 1) % middle.length];
  }

  /// "SECTION 2 OF 5", or the page count when there are no sections.
  String _eyebrow(QuestionnairePage page) {
    final sections = provider.renderedSectionCount;
    if (page.section == null || sections == 0) {
      return 'PAGE ${provider.pageIndex + 1} OF ${provider.pageCount}';
    }
    return 'SECTION ${page.sectionIndex + 1} OF $sections';
  }

  /// "Question 4 of 7" when the page holds one, "Questions 8–14 of 14" when
  /// it holds a run.
  String _range(QuestionnairePage page) {
    final (from, to) = page.sectionRange;
    final total = page.sectionQuestionCount;
    return from == to
        ? 'Question $from of $total'
        : 'Questions $from–$to of $total';
  }

  @override
  Widget build(BuildContext context) {
    final page = provider.currentPage!;
    final percent = (provider.progress * 100).round();
    final title = page.section?.title;

    return Padding(
      padding: const EdgeInsets.fromLTRB(20, 10, 20, 4),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 620),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(child: AppEyebrow(_eyebrow(page))),
                  const SizedBox(width: AppSpacing.sm),
                  Text(
                    '${provider.answeredCount} of ${provider.questionCount} '
                    'answered · $percent%',
                    style: const TextStyle(
                      color: AppColors.muted,
                      fontSize: 12.5,
                    ),
                  ),
                ],
              ),
              const SizedBox(height: AppSpacing.xs),
              Text.rich(
                TextSpan(
                  children: [
                    if (title != null && title.isNotEmpty) ...[
                      TextSpan(
                        text: title,
                        style: const TextStyle(fontWeight: FontWeight.w800),
                      ),
                      const TextSpan(text: '  ·  '),
                    ],
                    TextSpan(text: _range(page)),
                  ],
                ),
                style: Theme.of(context).textTheme.titleMedium?.copyWith(
                  color: AppColors.ink,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: AppSpacing.sm),
              ClipRRect(
                borderRadius: BorderRadius.circular(AppRadii.pill),
                child: TweenAnimationBuilder<double>(
                  tween: Tween(end: provider.progress),
                  duration: const Duration(milliseconds: 360),
                  curve: Curves.easeOutCubic,
                  builder: (context, value, _) => LinearProgressIndicator(
                    value: value,
                    minHeight: 6,
                    backgroundColor: AppColors.softLavender,
                    semanticsLabel: 'Questionnaire progress',
                    semanticsValue: '$percent',
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.sm),
              AppScriptAccent(_microcopy, fontSize: 13.5),
            ],
          ),
        ),
      ),
    );
  }
}

class _PageBody extends StatelessWidget {
  const _PageBody({
    super.key,
    required this.page,
    required this.provider,
    required this.showMissing,
    required this.keyFor,
    required this.title,
    required this.mandatory,
  });

  final QuestionnairePage page;
  final AssessmentProvider provider;
  final bool showMissing;
  final GlobalKey Function(int questionId) keyFor;
  final String? title;
  final bool mandatory;

  @override
  Widget build(BuildContext context) {
    final description = page.section?.description;
    // A fresh scroll view per page, so each one opens at the top.
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 24),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 620),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (provider.pageIndex == 0) ...[
                Text(
                  title?.isNotEmpty == true ? title! : 'Wellbeing check-in',
                  style: Theme.of(
                    context,
                  ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
                ),
                const SizedBox(height: 5),
                Text(
                  mandatory
                      ? 'Take a quiet moment and choose the answer that feels most true for you.'
                      : 'Choose the answer that best reflects how you feel today.',
                  style: const TextStyle(color: AppColors.muted, height: 1.45),
                ),
                const SizedBox(height: AppSpacing.lg),
              ],
              // The admin's instructions for this section, on its first page.
              if (page.isFirstInSection &&
                  description != null &&
                  description.isNotEmpty) ...[
                _SectionNote(text: description),
                const SizedBox(height: AppSpacing.xs),
              ],
              for (final item in page.questions) ...[
                const SizedBox(height: AppSpacing.md),
                _QuestionCard(
                  key: keyFor(item.question.id),
                  item: item,
                  selectedOptionIds: provider.selectedOptionsFor(
                    item.question.id,
                  ),
                  missing:
                      showMissing &&
                      item.question.required &&
                      !provider.isAnswered(item.question.id),
                  onSelect: provider.isSubmitting
                      ? null
                      : (optionId) {
                          final question = item.question;
                          if (!question.allowsMultiple) {
                            provider.selectAnswer(question.id, optionId);
                            return;
                          }
                          if (!provider.toggleAnswer(question.id, optionId)) {
                            ScaffoldMessenger.of(context).showSnackBar(
                              SnackBar(
                                content: Text(
                                  'You can choose up to ${question.maxSelections} for this question.',
                                ),
                              ),
                            );
                          }
                        },
                ),
              ],
              if (provider.submitError != null) ...[
                const SizedBox(height: AppSpacing.lg),
                _InlineError(message: provider.submitError!),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// A section's description, as the admin wrote it, in a soft note.
class _SectionNote extends StatelessWidget {
  const _SectionNote({required this.text});

  final String text;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(
      horizontal: AppSpacing.lg,
      vertical: AppSpacing.md,
    ),
    decoration: BoxDecoration(
      color: AppColors.softLavender,
      borderRadius: BorderRadius.circular(AppRadii.input),
    ),
    child: Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Icon(
          Icons.info_outline_rounded,
          size: 18,
          color: AppColors.primary,
        ),
        const SizedBox(width: AppSpacing.sm),
        Expanded(
          child: Text(
            text,
            style: const TextStyle(color: AppColors.ink, height: 1.45),
          ),
        ),
      ],
    ),
  );
}

/// The pause between sections: what was just finished, what comes next.
/// Deliberately small — a breath, not a ceremony.
class _SectionComplete extends StatelessWidget {
  const _SectionComplete({
    super.key,
    required this.completed,
    required this.next,
    required this.provider,
  });

  final AssessmentSection completed;
  final AssessmentSection next;
  final AssessmentProvider provider;

  @override
  Widget build(BuildContext context) {
    final sections = provider.renderedSectionCount;
    final done = provider.currentPage!.sectionIndex; // sections finished
    return SingleChildScrollView(
      padding: const EdgeInsets.fromLTRB(24, 24, 24, 24),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 520),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: AppSpacing.xl),
              const Align(
                child: CircleAvatar(
                  radius: 34,
                  backgroundColor: AppColors.softSage,
                  child: Icon(
                    Icons.check_rounded,
                    size: 34,
                    color: AppColors.primary,
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.xl),
              Text(
                '${completed.title} complete',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineSmall,
              ),
              const SizedBox(height: AppSpacing.sm),
              Text(
                sections > 0
                    ? '$done of $sections sections done · '
                          '${provider.answeredCount} of ${provider.questionCount} answered'
                    : '${provider.answeredCount} of ${provider.questionCount} answered',
                textAlign: TextAlign.center,
                style: const TextStyle(color: AppColors.muted),
              ),
              const SizedBox(height: AppSpacing.xxl),
              Container(
                padding: const EdgeInsets.all(AppSpacing.lg),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: BorderRadius.circular(AppRadii.card),
                  border: Border.all(color: AppColors.outline),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const AppEyebrow('NEXT', icon: Icons.arrow_forward_rounded),
                    const SizedBox(height: AppSpacing.xs),
                    Text(
                      next.title,
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    if (next.description?.isNotEmpty == true) ...[
                      const SizedBox(height: AppSpacing.xs),
                      Text(
                        next.description!,
                        style: const TextStyle(
                          color: AppColors.muted,
                          height: 1.4,
                        ),
                      ),
                    ],
                  ],
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              const AppScriptAccent(
                'Well done — keep going at your own pace  ♡',
                textAlign: TextAlign.center,
                fontSize: 14,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// One question: its number, its wording, and whichever answer control suits
/// its options — chips when the labels are short enough to sit in a row,
/// tiles when they are not.
class _QuestionCard extends StatelessWidget {
  const _QuestionCard({
    super.key,
    required this.item,
    required this.selectedOptionIds,
    required this.missing,
    required this.onSelect,
  });

  final PagedQuestion item;
  final List<int> selectedOptionIds;
  final bool missing;
  final ValueChanged<int>? onSelect;

  /// "Choose all that apply" / "Choose up to 3" — from the question's own
  /// configured limit, never assumed.
  static String _multiHint(AssessmentQuestion question) {
    final limit = question.maxSelections;
    return limit >= question.options.length
        ? 'Choose all that apply'
        : 'Choose up to $limit';
  }

  @override
  Widget build(BuildContext context) {
    final question = item.question;
    final answered = selectedOptionIds.isNotEmpty;
    // Multi-select questions always list their options in full so the
    // checkbox affordance is unmistakable.
    final asChips =
        !question.allowsMultiple && QuestionnairePager.rendersAsChips(question);

    return AnimatedContainer(
      duration: const Duration(milliseconds: 200),
      decoration: BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.circular(AppRadii.card),
        border: Border.all(
          color: missing
              ? AppColors.secondary
              : answered
              ? AppColors.lilac
              : AppColors.outline,
          width: missing ? 1.5 : 1,
        ),
        boxShadow: AppShadows.soft,
      ),
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        AppSpacing.lg,
        AppSpacing.lg,
        AppSpacing.lg,
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _NumberBadge(number: item.number, answered: answered),
              const SizedBox(width: AppSpacing.md),
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.only(top: 3),
                  child: Text(
                    question.text,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w600,
                      height: 1.35,
                    ),
                  ),
                ),
              ),
            ],
          ),
          if (question.helpText?.isNotEmpty == true ||
              question.allowsMultiple) ...[
            const SizedBox(height: AppSpacing.xs),
            Padding(
              padding: const EdgeInsets.only(left: 40),
              child: Text(
                [
                  if (question.helpText?.isNotEmpty == true)
                    question.helpText!,
                  if (question.allowsMultiple) _multiHint(question),
                ].join(' · '),
                style: const TextStyle(color: AppColors.muted, fontSize: 13),
              ),
            ),
          ],
          const SizedBox(height: AppSpacing.md),
          if (asChips)
            Wrap(
              spacing: AppSpacing.sm,
              runSpacing: AppSpacing.sm,
              children: [
                for (final option in question.options)
                  _AnswerChip(
                    label: option.label,
                    selected: selectedOptionIds.contains(option.id),
                    onTap: onSelect == null ? null : () => onSelect!(option.id),
                  ),
              ],
            )
          else
            for (final (index, option) in question.options.indexed)
              Padding(
                padding: EdgeInsets.only(top: index == 0 ? 0 : AppSpacing.sm),
                child: _AnswerTile(
                  label: option.label,
                  selected: selectedOptionIds.contains(option.id),
                  multiple: question.allowsMultiple,
                  onTap: onSelect == null ? null : () => onSelect!(option.id),
                ),
              ),
          if (missing) ...[
            const SizedBox(height: AppSpacing.md),
            const Row(
              children: [
                Icon(
                  Icons.info_outline_rounded,
                  size: 16,
                  color: AppColors.secondary,
                ),
                SizedBox(width: AppSpacing.sm),
                Expanded(
                  child: Text(
                    'Please choose an answer to continue.',
                    style: TextStyle(
                      color: AppColors.secondary,
                      fontSize: 13,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
            ),
          ],
        ],
      ),
    );
  }
}

/// The question's number, which turns into a tick once it has an answer —
/// a glance down the page shows what is done and what is waiting.
class _NumberBadge extends StatelessWidget {
  const _NumberBadge({required this.number, required this.answered});

  final int number;
  final bool answered;

  @override
  Widget build(BuildContext context) => AnimatedContainer(
    duration: const Duration(milliseconds: 200),
    width: 30,
    height: 30,
    alignment: Alignment.center,
    decoration: BoxDecoration(
      color: answered ? AppColors.primary : AppColors.softLavender,
      shape: BoxShape.circle,
    ),
    child: answered
        ? const Icon(Icons.check_rounded, size: 18, color: Colors.white)
        : Text(
            '$number',
            style: const TextStyle(
              color: AppColors.primary,
              fontSize: 13,
              fontWeight: FontWeight.w800,
            ),
          ),
  );
}

class _AnswerChip extends StatelessWidget {
  const _AnswerChip({
    required this.label,
    required this.selected,
    required this.onTap,
  });

  final String label;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    selected: selected,
    button: true,
    child: Material(
      color: selected ? AppColors.primary : AppColors.surface,
      shape: StadiumBorder(
        side: BorderSide(
          color: selected ? AppColors.primary : AppColors.outline,
          width: selected ? 1.5 : 1,
        ),
      ),
      child: InkWell(
        customBorder: const StadiumBorder(),
        onTap: onTap,
        child: ConstrainedBox(
          // Comfortable to tap even when the label is short.
          constraints: const BoxConstraints(minHeight: 44, minWidth: 56),
          child: Padding(
            padding: const EdgeInsets.symmetric(
              horizontal: AppSpacing.lg,
              vertical: AppSpacing.sm,
            ),
            child: Center(
              widthFactor: 1,
              child: Text(
                label,
                style: TextStyle(
                  color: selected ? Colors.white : AppColors.ink,
                  fontSize: 14,
                  fontWeight: selected ? FontWeight.w700 : FontWeight.w600,
                ),
              ),
            ),
          ),
        ),
      ),
    ),
  );
}

class _AnswerTile extends StatelessWidget {
  const _AnswerTile({
    required this.label,
    required this.selected,
    required this.onTap,
    this.multiple = false,
  });
  final String label;
  final bool selected;
  final VoidCallback? onTap;

  /// Renders a checkbox rather than a radio, for questions that allow
  /// several answers.
  final bool multiple;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Semantics(
      selected: selected,
      checked: multiple ? selected : null,
      button: !multiple,
      child: Material(
        color: selected ? colors.primaryContainer : colors.surface,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(16),
          side: BorderSide(
            color: selected ? colors.primary : colors.outlineVariant,
            width: selected ? 1.5 : 1,
          ),
        ),
        child: InkWell(
          borderRadius: BorderRadius.circular(16),
          onTap: onTap,
          child: Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
            child: Row(
              children: [
                Icon(
                  multiple
                      ? (selected
                            ? Icons.check_box_rounded
                            : Icons.check_box_outline_blank_rounded)
                      : (selected
                            ? Icons.check_circle_rounded
                            : Icons.radio_button_unchecked_rounded),
                  color: selected ? colors.primary : colors.outline,
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Text(
                    label,
                    style: Theme.of(context).textTheme.bodyLarge?.copyWith(
                      fontWeight: selected ? FontWeight.w600 : null,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

class _NavigationBar extends StatelessWidget {
  const _NavigationBar({
    required this.provider,
    required this.nextLabel,
    required this.showSubmit,
    required this.onPrevious,
    required this.onNext,
    required this.onSubmit,
  });

  final AssessmentProvider provider;
  final String nextLabel;
  final bool showSubmit;
  final VoidCallback onPrevious;
  final VoidCallback onNext;
  final VoidCallback onSubmit;

  @override
  Widget build(BuildContext context) {
    final busy = provider.isSubmitting;
    return Container(
      padding: const EdgeInsets.fromLTRB(20, 12, 20, 16),
      decoration: const BoxDecoration(
        color: AppColors.surface,
        boxShadow: [
          BoxShadow(
            color: Color(0x10000000),
            blurRadius: 14,
            offset: Offset(0, -3),
          ),
        ],
      ),
      child: Center(
        child: ConstrainedBox(
          constraints: const BoxConstraints(maxWidth: 620),
          child: Row(
            children: [
              if (!provider.isFirstPage) ...[
                Expanded(
                  child: OutlinedButton.icon(
                    onPressed: busy ? null : onPrevious,
                    icon: const Icon(Icons.arrow_back_rounded, size: 18),
                    label: const Text('Back'),
                  ),
                ),
                const SizedBox(width: 12),
              ],
              Expanded(
                flex: provider.isFirstPage ? 1 : 2,
                child: showSubmit
                    ? FilledButton(
                        onPressed: busy ? null : onSubmit,
                        child: busy
                            ? const SizedBox.square(
                                dimension: 22,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.5,
                                  color: Colors.white,
                                ),
                              )
                            : const Text('Submit check-in'),
                      )
                    : FilledButton.icon(
                        onPressed: busy ? null : onNext,
                        icon: const Icon(Icons.arrow_forward_rounded, size: 18),
                        iconAlignment: IconAlignment.end,
                        label: Text(nextLabel),
                      ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _LoadingView extends StatelessWidget {
  const _LoadingView();
  @override
  Widget build(BuildContext context) => const _CenteredMessage(
    icon: Icons.favorite_outline,
    message: 'Preparing your check-in…',
    loading: true,
  );
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.onRetry, this.message});
  final VoidCallback onRetry;
  final String? message;
  @override
  Widget build(BuildContext context) => _CenteredMessage(
    icon: Icons.cloud_off_outlined,
    message: message?.isNotEmpty == true
        ? message!
        : 'We couldn\'t load your check-in. Check your connection and try again.',
    action: FilledButton.icon(
      onPressed: onRetry,
      icon: const Icon(Icons.refresh),
      label: const Text('Try again'),
    ),
  );
}

class _CenteredMessage extends StatelessWidget {
  const _CenteredMessage({
    required this.icon,
    required this.message,
    this.loading = false,
    this.action,
  });
  final IconData icon;
  final String message;
  final bool loading;
  final Widget? action;
  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(32),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          if (loading)
            const CircularProgressIndicator()
          else
            Icon(icon, size: 50, color: Theme.of(context).colorScheme.primary),
          const SizedBox(height: 18),
          Text(
            message,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyLarge,
          ),
          if (action != null) ...[const SizedBox(height: 20), action!],
        ],
      ),
    ),
  );
}

class _InlineError extends StatelessWidget {
  const _InlineError({required this.message});
  final String message;
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(14),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.errorContainer,
      borderRadius: BorderRadius.circular(14),
    ),
    child: Row(
      children: [
        const Icon(Icons.error_outline),
        const SizedBox(width: 10),
        Expanded(child: Text(message)),
      ],
    ),
  );
}
