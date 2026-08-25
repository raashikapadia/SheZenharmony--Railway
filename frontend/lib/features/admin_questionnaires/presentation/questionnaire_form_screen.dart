import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../application/questionnaire_list_provider.dart';
import '../data/questionnaire.dart';
import 'widgets/confirm_dialog.dart';

const _statusOptions = ['draft', 'published', 'archived'];

/// Create/edit form for a questionnaire's own metadata. Pass an existing
/// [questionnaire] to edit it; omit it to create a new one. On successful
/// create, pops with the new questionnaire's id so the caller can open the
/// builder immediately.
class QuestionnaireFormScreen extends StatefulWidget {
  const QuestionnaireFormScreen({super.key, this.questionnaire});

  final Questionnaire? questionnaire;

  bool get isEditing => questionnaire != null;

  @override
  State<QuestionnaireFormScreen> createState() => _QuestionnaireFormScreenState();
}

class _QuestionnaireFormScreenState extends State<QuestionnaireFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _titleController;
  late final TextEditingController _descriptionController;
  late final TextEditingController _periodController;
  late String _status;
  late bool _isActive;
  bool _isSaving = false;
  bool _dirty = false;
  String? _titleError;
  String? _descriptionError;

  @override
  void initState() {
    super.initState();
    final q = widget.questionnaire;
    _titleController = TextEditingController(text: q?.title ?? '');
    _descriptionController = TextEditingController(text: q?.description ?? '');
    _periodController = TextEditingController(text: q?.period ?? '');
    _status = q?.status ?? 'draft';
    _isActive = q?.isActive ?? false;

    _titleController.addListener(_markDirty);
    _descriptionController.addListener(_markDirty);
    _periodController.addListener(_markDirty);
  }

  void _markDirty() {
    if (!_dirty) setState(() => _dirty = true);
  }

  @override
  void dispose() {
    _titleController.dispose();
    _descriptionController.dispose();
    _periodController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() {
      _isSaving = true;
      _titleError = null;
      _descriptionError = null;
    });

    final provider = context.read<QuestionnaireListProvider>();
    final title = _titleController.text.trim();
    final description = _descriptionController.text.trim();
    final period = _periodController.text.trim();

    if (widget.isEditing) {
      final ok = await provider.updateQuestionnaire(
        widget.questionnaire!.id,
        title: title,
        description: description.isEmpty ? null : description,
        period: period.isEmpty ? null : period,
        status: _status,
        isActive: _isActive,
      );
      if (!mounted) return;
      setState(() => _isSaving = false);
      if (ok) {
        _dirty = false;
        Navigator.of(context).pop();
      } else {
        _applyFieldErrors(provider.actionFieldErrors);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(provider.actionError ?? 'Failed to update questionnaire.')));
      }
    } else {
      final createdId = await provider.createQuestionnaire(
        title: title,
        description: description.isEmpty ? null : description,
        period: period.isEmpty ? null : period,
        status: _status,
      );
      if (!mounted) return;
      setState(() => _isSaving = false);
      if (createdId != null) {
        _dirty = false;
        Navigator.of(context).pop(createdId);
      } else {
        _applyFieldErrors(provider.actionFieldErrors);
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(provider.actionError ?? 'Failed to create questionnaire.')));
      }
    }
  }

  void _applyFieldErrors(Map<String, List<String>>? errors) {
    if (errors == null) return;
    setState(() {
      _titleError = errors['title']?.firstOrNull;
      _descriptionError = errors['description']?.firstOrNull;
    });
  }

  Future<bool> _confirmDiscard() async {
    if (!_dirty) return true;
    return showConfirmDialog(
      context,
      title: 'Discard changes?',
      message: 'You have unsaved changes. Are you sure you want to leave?',
      confirmLabel: 'Discard',
    );
  }

  @override
  Widget build(BuildContext context) {
    return PopScope(
      canPop: !_dirty,
      onPopInvokedWithResult: (didPop, result) async {
        if (didPop) return;
        if (await _confirmDiscard() && mounted) {
          Navigator.of(context).pop();
        }
      },
      child: Scaffold(
        appBar: AppBar(title: Text(widget.isEditing ? 'Edit Questionnaire' : 'Create Questionnaire')),
        body: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _titleController,
                  maxLength: 255,
                  decoration: InputDecoration(
                    labelText: 'Title *',
                    border: const OutlineInputBorder(),
                    errorText: _titleError,
                  ),
                  validator: (value) {
                    if (value == null || value.trim().isEmpty) return 'Title is required.';
                    if (value.length > 255) return 'Title must be 255 characters or fewer.';
                    return null;
                  },
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _descriptionController,
                  maxLength: 5000,
                  maxLines: 4,
                  decoration: InputDecoration(
                    labelText: 'Description',
                    border: const OutlineInputBorder(),
                    errorText: _descriptionError,
                  ),
                  validator: (value) {
                    if (value != null && value.length > 5000) return 'Description is too long.';
                    return null;
                  },
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _periodController,
                  maxLength: 100,
                  decoration: const InputDecoration(
                    labelText: 'Period (optional)',
                    hintText: 'e.g. Semester 1 2026',
                    border: OutlineInputBorder(),
                    helperText: 'Which term/cohort this version is for — for your own organization.',
                  ),
                ),
                const SizedBox(height: 16),
                DropdownButtonFormField<String>(
                  initialValue: _status,
                  decoration: const InputDecoration(labelText: 'Status', border: OutlineInputBorder()),
                  items: _statusOptions
                      .map((s) => DropdownMenuItem(value: s, child: Text(_statusLabel(s))))
                      .toList(),
                  onChanged: (value) {
                    if (value == null) return;
                    setState(() {
                      _status = value;
                      _dirty = true;
                    });
                  },
                ),
                if (widget.isEditing) ...[
                  const SizedBox(height: 8),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Active'),
                    subtitle: const Text('Visible to students when published and active.'),
                    value: _isActive,
                    onChanged: (value) => setState(() {
                      _isActive = value;
                      _dirty = true;
                    }),
                  ),
                ],
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: _isSaving ? null : _submit,
                  child: _isSaving
                      ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
                      : Text(widget.isEditing ? 'Save Changes' : 'Create Questionnaire'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  String _statusLabel(String status) => status[0].toUpperCase() + status.substring(1);
}

extension _FirstOrNull<T> on List<T> {
  T? get firstOrNull => isEmpty ? null : first;
}
