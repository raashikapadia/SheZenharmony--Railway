import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../auth/application/auth_provider.dart';
import '../application/questionnaire_builder_provider.dart';
import '../data/score_band.dart';
import '../data/stress_question.dart';
import 'question_form_screen.dart';
import 'score_band_form_screen.dart';
import 'widgets/confirm_dialog.dart';
import 'widgets/state_placeholders.dart';
import 'widgets/status_badge.dart';

class QuestionnaireBuilderScreen extends StatelessWidget {
  const QuestionnaireBuilderScreen({super.key, required this.questionnaireId});

  final int questionnaireId;

  @override
  Widget build(BuildContext context) {
    final token = context.read<AuthProvider>().session!.token;
    return ChangeNotifierProvider(
      create: (_) => QuestionnaireBuilderProvider(
        apiService: ApiService(),
        token: token,
        questionnaireId: questionnaireId,
      )..load(),
      child: const _QuestionnaireBuilderView(),
    );
  }
}

class _QuestionnaireBuilderView extends StatefulWidget {
  const _QuestionnaireBuilderView();

  @override
  State<_QuestionnaireBuilderView> createState() => _QuestionnaireBuilderViewState();
}

class _QuestionnaireBuilderViewState extends State<_QuestionnaireBuilderView>
    with SingleTickerProviderStateMixin {
  late final TabController _tabController;

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this)..addListener(() => setState(() {}));
  }

  @override
  void dispose() {
    _tabController.dispose();
    super.dispose();
  }

  Future<void> _addQuestion() async {
    final provider = context.read<QuestionnaireBuilderProvider>();
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => QuestionFormScreen(builderProvider: provider)),
    );
  }

  Future<void> _editQuestion(StressQuestion question) async {
    final provider = context.read<QuestionnaireBuilderProvider>();
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => QuestionFormScreen(builderProvider: provider, question: question)),
    );
  }

  Future<void> _deleteQuestion(StressQuestion question) async {
    final confirmed = await showConfirmDialog(
      context,
      title: 'Delete this question?',
      message: 'Are you sure you want to delete "${question.questionText}"?',
    );
    if (!confirmed || !mounted) return;

    final provider = context.read<QuestionnaireBuilderProvider>();
    final ok = await provider.deleteQuestion(question.id);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          ok ? (provider.lastActionMessage ?? 'Question deleted.') : (provider.actionError ?? 'Failed to delete question.'),
        ),
      ),
    );
  }

  Future<void> _reorder(List<StressQuestion> questions, int oldIndex, int newIndex) async {
    if (newIndex > oldIndex) newIndex -= 1;
    final reordered = List<StressQuestion>.from(questions);
    final moved = reordered.removeAt(oldIndex);
    reordered.insert(newIndex, moved);

    final provider = context.read<QuestionnaireBuilderProvider>();
    final ok = await provider.reorder(reordered.map((q) => q.id).toList());
    if (!mounted) return;
    if (!ok) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(provider.actionError ?? 'Failed to reorder questions.')));
    }
  }

  Future<void> _addScoreBand() async {
    final provider = context.read<QuestionnaireBuilderProvider>();
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ScoreBandFormScreen(builderProvider: provider)),
    );
  }

  Future<void> _editScoreBand(ScoreBand band) async {
    final provider = context.read<QuestionnaireBuilderProvider>();
    await Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => ScoreBandFormScreen(builderProvider: provider, band: band)),
    );
  }

  Future<void> _deleteScoreBand(ScoreBand band) async {
    final confirmed = await showConfirmDialog(
      context,
      title: 'Delete this score range?',
      message: 'Are you sure you want to delete "${band.label}" (${band.minScore}–${band.maxScore})?',
    );
    if (!confirmed || !mounted) return;

    final provider = context.read<QuestionnaireBuilderProvider>();
    final ok = await provider.deleteScoreBand(band.id);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          ok ? (provider.lastActionMessage ?? 'Score range deleted.') : (provider.actionError ?? 'Failed to delete score range.'),
        ),
      ),
    );
  }

  Future<void> _createNewVersion() async {
    final confirmed = await showConfirmDialog(
      context,
      title: 'Create a new version?',
      message: 'This clones the questionnaire — including its questions, options, and score ranges — into a '
          'new draft you can edit freely. The current version and its assessment history are untouched. '
          'When you publish the new draft, it becomes what new users take.',
      confirmLabel: 'Create Draft',
      destructive: false,
    );
    if (!confirmed || !mounted) return;

    final provider = context.read<QuestionnaireBuilderProvider>();
    final newId = await provider.createNewVersion();
    if (!mounted) return;

    if (newId != null) {
      ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('New draft version created.')));
      Navigator.of(context).pushReplacement(
        MaterialPageRoute(builder: (_) => QuestionnaireBuilderScreen(questionnaireId: newId)),
      );
    } else {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(provider.actionError ?? 'Failed to create a new version.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<QuestionnaireBuilderProvider>();
    final loaded = provider.state == BuilderLoadState.loaded;

    return Scaffold(
      appBar: AppBar(
        title: Text(provider.questionnaire?.title ?? 'Questionnaire Builder'),
        actions: loaded
            ? [
                IconButton(
                  tooltip: 'Create New Version',
                  icon: const Icon(Icons.fork_right),
                  onPressed: provider.isMutating ? null : _createNewVersion,
                ),
              ]
            : null,
        bottom: loaded
            ? TabBar(
                controller: _tabController,
                tabs: const [
                  Tab(text: 'Questions'),
                  Tab(text: 'Score Ranges'),
                ],
              )
            : null,
      ),
      floatingActionButton: loaded
          ? FloatingActionButton.extended(
              onPressed: _tabController.index == 0 ? _addQuestion : _addScoreBand,
              icon: const Icon(Icons.add),
              label: Text(_tabController.index == 0 ? 'Add Question' : 'Add Score Range'),
            )
          : null,
      body: _buildBody(context, provider),
    );
  }

  Widget _buildBody(BuildContext context, QuestionnaireBuilderProvider provider) {
    switch (provider.state) {
      case BuilderLoadState.loading:
        return const LoadingState();
      case BuilderLoadState.error:
        return ErrorStateView(
          message: provider.errorMessage ?? 'Unable to load this questionnaire. Please try again.',
          onRetry: provider.load,
        );
      case BuilderLoadState.loaded:
        final questionnaire = provider.questionnaire!;
        return Column(
          children: [
            Padding(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Row(
                    children: [
                      Expanded(
                        child: Text(questionnaire.title, style: Theme.of(context).textTheme.headlineSmall),
                      ),
                      StatusBadge(isActive: questionnaire.isActive),
                    ],
                  ),
                  if ((questionnaire.description ?? '').isNotEmpty) ...[
                    const SizedBox(height: 4),
                    Text(questionnaire.description!, style: Theme.of(context).textTheme.bodyMedium),
                  ],
                  const SizedBox(height: 4),
                  Text(
                    'v${questionnaire.version}'
                    '${(questionnaire.period ?? '').isNotEmpty ? ' · ${questionnaire.period}' : ''} · '
                    'Status: ${questionnaire.status} · ${questionnaire.questionCount} question(s) · '
                    '${questionnaire.scoreBands.length} score range(s)',
                    style: Theme.of(context).textTheme.bodySmall,
                  ),
                ],
              ),
            ),
            const Divider(height: 1),
            Expanded(
              child: TabBarView(
                controller: _tabController,
                children: [
                  questionnaire.questions.isEmpty
                      ? EmptyState(
                          message: 'No questions yet.',
                          actionLabel: 'Add Question',
                          onAction: _addQuestion,
                          icon: Icons.quiz_outlined,
                        )
                      : ReorderableListView.builder(
                          padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
                          itemCount: questionnaire.questions.length,
                          onReorder: (oldIndex, newIndex) =>
                              _reorder(questionnaire.questions, oldIndex, newIndex),
                          itemBuilder: (context, index) {
                            final question = questionnaire.questions[index];
                            return _QuestionTile(
                              key: ValueKey(question.id),
                              index: index + 1,
                              question: question,
                              onEdit: () => _editQuestion(question),
                              onDelete: () => _deleteQuestion(question),
                            );
                          },
                        ),
                  questionnaire.scoreBands.isEmpty
                      ? EmptyState(
                          message: 'No score ranges yet — without them, submissions can\'t be scored '
                              'into a stress level.',
                          actionLabel: 'Add Score Range',
                          onAction: _addScoreBand,
                          icon: Icons.speed_outlined,
                        )
                      : ListView.builder(
                          padding: const EdgeInsets.fromLTRB(16, 12, 16, 96),
                          itemCount: questionnaire.scoreBands.length,
                          itemBuilder: (context, index) {
                            final band = questionnaire.scoreBands[index];
                            return _ScoreBandTile(
                              band: band,
                              onEdit: () => _editScoreBand(band),
                              onDelete: () => _deleteScoreBand(band),
                            );
                          },
                        ),
                ],
              ),
            ),
          ],
        );
    }
  }
}

class _QuestionTile extends StatelessWidget {
  const _QuestionTile({
    super.key,
    required this.index,
    required this.question,
    required this.onEdit,
    required this.onDelete,
  });

  final int index;
  final StressQuestion question;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: Padding(
        padding: const EdgeInsets.all(12),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                CircleAvatar(radius: 14, child: Text('$index', style: const TextStyle(fontSize: 12))),
                const SizedBox(width: 10),
                Expanded(
                  child: Text(question.questionText, style: Theme.of(context).textTheme.titleSmall),
                ),
                const Icon(Icons.drag_handle),
              ],
            ),
            const SizedBox(height: 8),
            Wrap(
              spacing: 8,
              runSpacing: 4,
              children: [
                Chip(label: Text(QuestionType.label(question.questionType)), visualDensity: VisualDensity.compact),
                if (question.isRequired)
                  const Chip(label: Text('Required'), visualDensity: VisualDensity.compact),
                if (!question.isActive)
                  const Chip(label: Text('Inactive'), visualDensity: VisualDensity.compact),
              ],
            ),
            if (question.options.isNotEmpty) ...[
              const SizedBox(height: 8),
              Text(
                question.options.map((o) => o.label).join(' · '),
                style: Theme.of(context).textTheme.bodySmall,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
              ),
            ],
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                IconButton(icon: const Icon(Icons.edit_outlined), onPressed: onEdit),
                IconButton(icon: const Icon(Icons.delete_outline), onPressed: onDelete),
              ],
            ),
          ],
        ),
      ),
    );
  }
}

class _ScoreBandTile extends StatelessWidget {
  const _ScoreBandTile({required this.band, required this.onEdit, required this.onDelete});

  final ScoreBand band;
  final VoidCallback onEdit;
  final VoidCallback onDelete;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        title: Text(band.label, style: Theme.of(context).textTheme.titleSmall),
        subtitle: Text('Score ${band.minScore}–${band.maxScore}${band.isActive ? '' : ' · inactive'}'),
        trailing: Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            IconButton(icon: const Icon(Icons.edit_outlined), onPressed: onEdit),
            IconButton(icon: const Icon(Icons.delete_outline), onPressed: onDelete),
          ],
        ),
      ),
    );
  }
}
