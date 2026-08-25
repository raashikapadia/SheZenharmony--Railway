import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../auth/application/auth_provider.dart';
import '../application/questionnaire_list_provider.dart';
import '../data/questionnaire.dart';
import 'questionnaire_builder_screen.dart';
import 'questionnaire_form_screen.dart';
import 'widgets/confirm_dialog.dart';
import 'widgets/state_placeholders.dart';
import 'widgets/status_badge.dart';

class QuestionnaireListScreen extends StatelessWidget {
  const QuestionnaireListScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final token = context.read<AuthProvider>().session!.token;
    return ChangeNotifierProvider(
      create: (_) => QuestionnaireListProvider(apiService: ApiService(), token: token)..load(),
      child: const _QuestionnaireListView(),
    );
  }
}

class _QuestionnaireListView extends StatefulWidget {
  const _QuestionnaireListView();

  @override
  State<_QuestionnaireListView> createState() => _QuestionnaireListViewState();
}

class _QuestionnaireListViewState extends State<_QuestionnaireListView> {
  final _searchController = TextEditingController();
  final _scrollController = ScrollController();

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_onScroll);
  }

  @override
  void dispose() {
    _searchController.dispose();
    _scrollController.dispose();
    super.dispose();
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= _scrollController.position.maxScrollExtent - 200) {
      context.read<QuestionnaireListProvider>().loadMore();
    }
  }

  Future<void> _createQuestionnaire() async {
    final createdId = await Navigator.of(
      context,
    ).push<int>(MaterialPageRoute(builder: (_) => const QuestionnaireFormScreen()));

    if (createdId != null && mounted) {
      await Navigator.of(
        context,
      ).push(MaterialPageRoute(builder: (_) => QuestionnaireBuilderScreen(questionnaireId: createdId)));
      if (mounted) context.read<QuestionnaireListProvider>().load();
    }
  }

  Future<void> _delete(Questionnaire questionnaire) async {
    final confirmed = await showConfirmDialog(
      context,
      title: 'Archive questionnaire?',
      message:
          'This will archive "${questionnaire.title}" and hide it from the active list. '
          'It is kept (not permanently deleted) because it may have historical assessment data attached.',
      confirmLabel: 'Archive',
    );
    if (!confirmed || !mounted) return;

    final provider = context.read<QuestionnaireListProvider>();
    final ok = await provider.deleteQuestionnaire(questionnaire.id);
    if (!mounted) return;
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(ok ? 'Questionnaire archived.' : (provider.actionError ?? 'Failed to archive.'))),
    );
  }

  Future<void> _toggleActive(Questionnaire questionnaire) async {
    final provider = context.read<QuestionnaireListProvider>();
    final ok = await provider.setActive(questionnaire.id, !questionnaire.isActive);
    if (!mounted) return;
    if (!ok) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(provider.actionError ?? 'Action failed.')));
    }
  }

  @override
  Widget build(BuildContext context) {
    final provider = context.watch<QuestionnaireListProvider>();

    return Scaffold(
      appBar: AppBar(title: const Text('Questionnaires')),
      floatingActionButton: FloatingActionButton.extended(
        onPressed: _createQuestionnaire,
        icon: const Icon(Icons.add),
        label: const Text('Create Questionnaire'),
      ),
      body: Column(
        children: [
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 8),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Search by title or description',
                prefixIcon: const Icon(Icons.search),
                border: const OutlineInputBorder(),
                suffixIcon: _searchController.text.isEmpty
                    ? null
                    : IconButton(
                        icon: const Icon(Icons.clear),
                        onPressed: () {
                          _searchController.clear();
                          context.read<QuestionnaireListProvider>().setSearch('');
                        },
                      ),
              ),
              onSubmitted: (value) => context.read<QuestionnaireListProvider>().setSearch(value.trim()),
            ),
          ),
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 16),
            child: Row(
              children: [
                _FilterChip(label: 'All', value: 'all', current: provider.filter),
                const SizedBox(width: 8),
                _FilterChip(label: 'Active', value: 'active', current: provider.filter),
                const SizedBox(width: 8),
                _FilterChip(label: 'Inactive', value: 'inactive', current: provider.filter),
              ],
            ),
          ),
          const SizedBox(height: 8),
          Expanded(child: _buildBody(context, provider)),
        ],
      ),
    );
  }

  Widget _buildBody(BuildContext context, QuestionnaireListProvider provider) {
    switch (provider.state) {
      case ListLoadState.loading:
        return const LoadingState();
      case ListLoadState.error:
        return ErrorStateView(
          message: provider.errorMessage ?? 'Unable to load questionnaires. Please try again.',
          onRetry: provider.load,
        );
      case ListLoadState.loaded:
        if (provider.items.isEmpty) {
          return EmptyState(
            message: 'No questionnaires found.',
            actionLabel: 'Create Questionnaire',
            onAction: _createQuestionnaire,
          );
        }
        return RefreshIndicator(
          onRefresh: provider.load,
          child: ListView.separated(
            controller: _scrollController,
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 96),
            itemCount: provider.items.length + (provider.isLoadingMore ? 1 : 0),
            separatorBuilder: (_, _) => const SizedBox(height: 12),
            itemBuilder: (context, index) {
              if (index >= provider.items.length) {
                return const Padding(
                  padding: EdgeInsets.symmetric(vertical: 16),
                  child: Center(child: CircularProgressIndicator(strokeWidth: 2)),
                );
              }
              final questionnaire = provider.items[index];
              return _QuestionnaireCard(
                questionnaire: questionnaire,
                onDelete: () => _delete(questionnaire),
                onToggleActive: () => _toggleActive(questionnaire),
              );
            },
          ),
        );
    }
  }
}

class _FilterChip extends StatelessWidget {
  const _FilterChip({required this.label, required this.value, required this.current});

  final String label;
  final String value;
  final String current;

  @override
  Widget build(BuildContext context) {
    return ChoiceChip(
      label: Text(label),
      selected: current == value,
      onSelected: (_) => context.read<QuestionnaireListProvider>().setFilter(value),
    );
  }
}

class _QuestionnaireCard extends StatelessWidget {
  const _QuestionnaireCard({
    required this.questionnaire,
    required this.onDelete,
    required this.onToggleActive,
  });

  final Questionnaire questionnaire;
  final VoidCallback onDelete;
  final VoidCallback onToggleActive;

  @override
  Widget build(BuildContext context) {
    final dateFormat = questionnaire.updatedAt;
    return Card(
      elevation: 1,
      child: InkWell(
        borderRadius: BorderRadius.circular(12),
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => QuestionnaireBuilderScreen(questionnaireId: questionnaire.id)),
        ),
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                children: [
                  Expanded(
                    child: Text(
                      questionnaire.title,
                      style: Theme.of(context).textTheme.titleMedium,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ),
                  StatusBadge(isActive: questionnaire.isActive),
                ],
              ),
              if ((questionnaire.description ?? '').isNotEmpty) ...[
                const SizedBox(height: 4),
                Text(
                  questionnaire.description!,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
              ],
              const SizedBox(height: 8),
              Wrap(
                spacing: 12,
                runSpacing: 4,
                children: [
                  Text('${questionnaire.questionCount} question(s)', style: Theme.of(context).textTheme.bodySmall),
                  Text('Status: ${questionnaire.status}', style: Theme.of(context).textTheme.bodySmall),
                  if (dateFormat != null)
                    Text('Updated ${_formatDate(dateFormat)}', style: Theme.of(context).textTheme.bodySmall),
                ],
              ),
              const SizedBox(height: 4),
              Row(
                mainAxisAlignment: MainAxisAlignment.end,
                children: [
                  IconButton(
                    tooltip: 'Edit',
                    icon: const Icon(Icons.edit_outlined),
                    onPressed: () => Navigator.of(context).push(
                      MaterialPageRoute(builder: (_) => QuestionnaireFormScreen(questionnaire: questionnaire)),
                    ),
                  ),
                  IconButton(
                    tooltip: questionnaire.isActive ? 'Deactivate' : 'Activate',
                    icon: Icon(questionnaire.isActive ? Icons.toggle_on : Icons.toggle_off),
                    onPressed: onToggleActive,
                  ),
                  IconButton(
                    tooltip: 'Archive',
                    icon: const Icon(Icons.delete_outline),
                    onPressed: onDelete,
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  String _formatDate(DateTime date) {
    return '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
  }
}
