import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../auth/application/auth_provider.dart';
import '../application/assessment_provider.dart';
import 'assessment_result_screen.dart';

/// The single questionnaire-taking experience used for both the mandatory
/// post-registration assessment and the optional in-app check-in — same
/// widget, same data source, so there is exactly one place that renders
/// questionnaire content.
class QuestionnaireScreen extends StatelessWidget {
  const QuestionnaireScreen({super.key, required this.mandatory});

  final bool mandatory;

  @override
  Widget build(BuildContext context) {
    final token = context.read<AuthProvider>().session!.token;
    return ChangeNotifierProvider(
      create: (_) => AssessmentProvider(apiService: ApiService(), token: token)..load(),
      child: _QuestionnaireView(mandatory: mandatory),
    );
  }
}

class _QuestionnaireView extends StatelessWidget {
  const _QuestionnaireView({required this.mandatory});

  final bool mandatory;

  Future<void> _submit(BuildContext context) async {
    final provider = context.read<AssessmentProvider>();
    final ok = await provider.submit();
    if (!context.mounted) return;

    if (ok) {
      context.read<AuthProvider>().markAssessmentCompleted();
      Navigator.of(
        context,
      ).pushReplacement(MaterialPageRoute(builder: (_) => AssessmentResultScreen(result: provider.result!)));
    } else {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(provider.submitError ?? 'Failed to submit. Please try again.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: !mandatory,
      onPopInvokedWithResult: (didPop, result) {
        if (didPop) return;
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(content: Text('Please complete the stress check-in to continue.')),
        );
      },
      child: Scaffold(
        appBar: AppBar(
          title: const Text('Stress Check-In'),
          automaticallyImplyLeading: !mandatory,
        ),
        body: Consumer<AssessmentProvider>(
          builder: (context, provider, _) {
            switch (provider.state) {
              case AssessmentLoadState.loading:
                return const Center(child: CircularProgressIndicator());
              case AssessmentLoadState.error:
                return _ErrorView(
                  message: provider.errorMessage ?? 'Unable to load the questionnaire. Please try again.',
                  onRetry: provider.load,
                );
              case AssessmentLoadState.loaded:
                final question = provider.currentQuestion;
                if (question == null) {
                  return const Center(child: Text('This questionnaire has no questions yet.'));
                }
                final progress = (provider.currentIndex + 1) / provider.questionCount;

                return SafeArea(
                  child: Column(
                    children: [
                      LinearProgressIndicator(value: progress, minHeight: 4),
                      Expanded(
                        child: SingleChildScrollView(
                          padding: const EdgeInsets.all(24),
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Text(
                                'Question ${provider.currentIndex + 1} of ${provider.questionCount}',
                                style: Theme.of(
                                  context,
                                ).textTheme.labelLarge?.copyWith(color: Theme.of(context).colorScheme.primary),
                              ),
                              const SizedBox(height: 12),
                              Text(question.text, style: Theme.of(context).textTheme.headlineSmall),
                              const SizedBox(height: 24),
                              ...question.options.map(
                                (option) => Padding(
                                  padding: const EdgeInsets.only(bottom: 8),
                                  child: _AnswerTile(
                                    label: option.label,
                                    selected: provider.selectedOptionFor(question.id) == option.id,
                                    onTap: () => provider.selectAnswer(question.id, option.id),
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                      Padding(
                        padding: const EdgeInsets.fromLTRB(24, 8, 24, 24),
                        child: Row(
                          children: [
                            if (!provider.isFirstQuestion)
                              Expanded(
                                child: OutlinedButton(
                                  onPressed: provider.goPrevious,
                                  child: const Text('Previous'),
                                ),
                              ),
                            if (!provider.isFirstQuestion) const SizedBox(width: 12),
                            Expanded(
                              flex: 2,
                              child: provider.isLastQuestion
                                  ? FilledButton(
                                      onPressed: provider.allRequiredAnswered && !provider.isSubmitting
                                          ? () => _submit(context)
                                          : null,
                                      child: provider.isSubmitting
                                          ? const SizedBox.square(
                                              dimension: 20,
                                              child: CircularProgressIndicator(strokeWidth: 2),
                                            )
                                          : const Text('Submit Questionnaire'),
                                    )
                                  : FilledButton(
                                      onPressed: provider.canGoNext ? provider.goNext : null,
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
          },
        ),
      ),
    );
  }
}

class _AnswerTile extends StatelessWidget {
  const _AnswerTile({required this.label, required this.selected, required this.onTap});

  final String label;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Material(
      color: selected ? colors.primaryContainer : colors.surfaceContainerHighest,
      borderRadius: BorderRadius.circular(12),
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: onTap,
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
          child: Row(
            children: [
              Icon(
                selected ? Icons.radio_button_checked : Icons.radio_button_unchecked,
                color: selected ? colors.primary : colors.outline,
              ),
              const SizedBox(width: 12),
              Expanded(child: Text(label, style: Theme.of(context).textTheme.bodyLarge)),
            ],
          ),
        ),
      ),
    );
  }
}

class _ErrorView extends StatelessWidget {
  const _ErrorView({required this.message, required this.onRetry});

  final String message;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(Icons.error_outline, size: 48, color: Theme.of(context).colorScheme.error),
            const SizedBox(height: 12),
            Text(message, textAlign: TextAlign.center),
            const SizedBox(height: 16),
            FilledButton.icon(onPressed: onRetry, icon: const Icon(Icons.refresh), label: const Text('Retry')),
          ],
        ),
      ),
    );
  }
}
