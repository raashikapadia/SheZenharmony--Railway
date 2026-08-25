import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../auth/application/auth_provider.dart';
import '../application/assessment_provider.dart';
import 'assessment_result_screen.dart';

class QuestionnaireScreen extends StatelessWidget {
  const QuestionnaireScreen({super.key, required this.mandatory});

  final bool mandatory;

  @override
  Widget build(BuildContext context) {
    final token = context.read<AuthProvider>().session!.token;
    return ChangeNotifierProvider(
      create: (_) =>
          AssessmentProvider(apiService: ApiService(), token: token)..load(),
      child: _QuestionnaireView(mandatory: mandatory),
    );
  }
}

class _QuestionnaireView extends StatelessWidget {
  const _QuestionnaireView({required this.mandatory});

  final bool mandatory;

  Future<void> _submit(BuildContext context) async {
    final provider = context.read<AssessmentProvider>();
    if (!await provider.submit() || !context.mounted) {
      if (context.mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text(
              'We couldn\'t submit your check-in. Please try again.',
            ),
          ),
        );
      }
      return;
    }

    final resultScreen = AssessmentResultScreen(
      result: provider.result!,
      mandatory: mandatory,
    );
    if (mandatory) {
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

  @override
  Widget build(BuildContext context) {
    final loadedTitle = context.select<AssessmentProvider, String?>(
      (provider) => provider.questionnaire?.title,
    );

    return PopScope(
      canPop: !mandatory,
      onPopInvokedWithResult: (didPop, result) {
        if (!didPop) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Complete this check-in to continue to SheZen.'),
            ),
          );
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(mandatory ? 'Your first check-in' : 'Stress check-in'),
          automaticallyImplyLeading: !mandatory,
        ),
        body: Consumer<AssessmentProvider>(
          builder: (context, provider, _) {
            return switch (provider.state) {
              AssessmentLoadState.loading => const _LoadingView(),
              AssessmentLoadState.error => _ErrorView(onRetry: provider.load),
              AssessmentLoadState.loaded => _LoadedQuestionnaire(
                provider: provider,
                title: loadedTitle,
                mandatory: mandatory,
                onSubmit: () => _submit(context),
              ),
            };
          },
        ),
      ),
    );
  }
}

class _LoadedQuestionnaire extends StatelessWidget {
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
  Widget build(BuildContext context) {
    final question = provider.currentQuestion;
    if (question == null) {
      return const _CenteredMessage(
        icon: Icons.assignment_outlined,
        message: 'No questions are available right now.',
      );
    }
    final colors = Theme.of(context).colorScheme;
    final currentNumber = provider.currentIndex + 1;
    final progress = currentNumber / provider.questionCount;

    return SafeArea(
      child: Column(
        children: [
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.fromLTRB(20, 8, 20, 20),
              child: ConstrainedBox(
                constraints: const BoxConstraints(maxWidth: 620),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    if (provider.currentIndex == 0) ...[
                      Text(
                        title?.isNotEmpty == true
                            ? title!
                            : 'Wellbeing check-in',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: 5),
                      Text(
                        mandatory
                            ? 'Take a quiet moment and choose the answer that feels most true for you.'
                            : 'Choose the answer that best reflects how you feel today.',
                        style: TextStyle(color: colors.onSurfaceVariant),
                      ),
                      const SizedBox(height: 22),
                    ],
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Text(
                          'Question $currentNumber of ${provider.questionCount}',
                          style: TextStyle(
                            color: colors.primary,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                        Text(
                          '${(progress * 100).round()}%',
                          style: TextStyle(color: colors.onSurfaceVariant),
                        ),
                      ],
                    ),
                    const SizedBox(height: 10),
                    ClipRRect(
                      borderRadius: BorderRadius.circular(8),
                      child: LinearProgressIndicator(
                        value: progress,
                        minHeight: 8,
                        backgroundColor: colors.surfaceContainerHighest,
                      ),
                    ),
                    const SizedBox(height: 28),
                    Card(
                      child: Padding(
                        padding: const EdgeInsets.all(22),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              question.text,
                              style: Theme.of(context).textTheme.headlineSmall
                                  ?.copyWith(
                                    fontWeight: FontWeight.w700,
                                    height: 1.25,
                                  ),
                            ),
                            const SizedBox(height: 22),
                            for (final option in question.options)
                              Padding(
                                padding: const EdgeInsets.only(bottom: 10),
                                child: _AnswerTile(
                                  label: option.label,
                                  selected:
                                      provider.selectedOptionFor(question.id) ==
                                      option.id,
                                  onTap: provider.isSubmitting
                                      ? null
                                      : () => provider.selectAnswer(
                                          question.id,
                                          option.id,
                                        ),
                                ),
                              ),
                          ],
                        ),
                      ),
                    ),
                    if (provider.submitError != null) ...[
                      const SizedBox(height: 14),
                      _InlineError(
                        message:
                            'Your check-in wasn\'t submitted. Please try again.',
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.fromLTRB(20, 12, 20, 20),
            decoration: BoxDecoration(
              color: colors.surface,
              boxShadow: const [
                BoxShadow(
                  color: Color(0x10000000),
                  blurRadius: 14,
                  offset: Offset(0, -3),
                ),
              ],
            ),
            child: Row(
              children: [
                if (!provider.isFirstQuestion) ...[
                  Expanded(
                    child: OutlinedButton(
                      onPressed: provider.isSubmitting
                          ? null
                          : provider.goPrevious,
                      child: const Text('Previous'),
                    ),
                  ),
                  const SizedBox(width: 12),
                ],
                Expanded(
                  flex: provider.isFirstQuestion ? 1 : 2,
                  child: provider.isLastQuestion
                      ? FilledButton(
                          onPressed:
                              provider.allRequiredAnswered &&
                                  !provider.isSubmitting
                              ? onSubmit
                              : null,
                          child: provider.isSubmitting
                              ? const SizedBox.square(
                                  dimension: 22,
                                  child: CircularProgressIndicator(
                                    strokeWidth: 2.5,
                                    color: Colors.white,
                                  ),
                                )
                              : const Text('Submit check-in'),
                        )
                      : FilledButton(
                          onPressed: provider.canGoNext
                              ? provider.goNext
                              : null,
                          child: const Text('Next'),
                        ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}

class _AnswerTile extends StatelessWidget {
  const _AnswerTile({
    required this.label,
    required this.selected,
    required this.onTap,
  });
  final String label;
  final bool selected;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Semantics(
      selected: selected,
      button: true,
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
            padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 16),
            child: Row(
              children: [
                Icon(
                  selected
                      ? Icons.check_circle_rounded
                      : Icons.radio_button_unchecked_rounded,
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
  const _ErrorView({required this.onRetry});
  final VoidCallback onRetry;
  @override
  Widget build(BuildContext context) => _CenteredMessage(
    icon: Icons.cloud_off_outlined,
    message:
        'We couldn\'t load your check-in. Check your connection and try again.',
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
