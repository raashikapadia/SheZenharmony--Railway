import 'package:flutter/material.dart';

import '../application/questionnaire_builder_provider.dart';
import '../data/score_band.dart';
import 'widgets/confirm_dialog.dart';

/// Create/edit form for one score range (e.g. "Moderate", 6-10) — this is
/// what determines the stress level shown to the user for a given total
/// score, fully admin-editable rather than hardcoded thresholds.
class ScoreBandFormScreen extends StatefulWidget {
  const ScoreBandFormScreen({
    super.key,
    required this.builderProvider,
    this.band,
  });

  final QuestionnaireBuilderProvider builderProvider;
  final ScoreBand? band;

  bool get isEditing => band != null;

  @override
  State<ScoreBandFormScreen> createState() => _ScoreBandFormScreenState();
}

class _ScoreBandFormScreenState extends State<ScoreBandFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _labelController;
  late final TextEditingController _codeController;
  late final TextEditingController _minController;
  late final TextEditingController _maxController;
  late bool _isActive;
  bool _isSaving = false;
  bool _dirty = false;

  @override
  void initState() {
    super.initState();
    final band = widget.band;
    _labelController = TextEditingController(text: band?.label ?? '');
    _codeController = TextEditingController(text: band?.code ?? '');
    _minController = TextEditingController(
      text: band?.minScore.toString() ?? '',
    );
    _maxController = TextEditingController(
      text: band?.maxScore.toString() ?? '',
    );
    _isActive = band?.isActive ?? true;

    for (final controller in [
      _labelController,
      _codeController,
      _minController,
      _maxController,
    ]) {
      controller.addListener(_markDirty);
    }
  }

  void _markDirty() {
    if (!_dirty) setState(() => _dirty = true);
  }

  @override
  void dispose() {
    _labelController.dispose();
    _codeController.dispose();
    _minController.dispose();
    _maxController.dispose();
    super.dispose();
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    setState(() => _isSaving = true);
    final label = _labelController.text.trim();
    final code = _codeController.text.trim();
    final min = int.parse(_minController.text.trim());
    final max = int.parse(_maxController.text.trim());

    final ok = widget.isEditing
        ? await widget.builderProvider.updateScoreBand(
            bandId: widget.band!.id,
            code: code,
            label: label,
            minScore: min,
            maxScore: max,
            isActive: _isActive,
          )
        : await widget.builderProvider.addScoreBand(
            code: code,
            label: label,
            minScore: min,
            maxScore: max,
          );

    if (!mounted) return;
    setState(() => _isSaving = false);

    if (ok) {
      _dirty = false;
      Navigator.of(context).pop();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            widget.builderProvider.actionError ?? 'Failed to save score range.',
          ),
        ),
      );
    }
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
        if (await _confirmDiscard() && context.mounted) {
          Navigator.of(context).pop();
        }
      },
      child: Scaffold(
        appBar: AppBar(
          title: Text(
            widget.isEditing ? 'Edit Score Range' : 'Add Score Range',
          ),
        ),
        body: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _labelController,
                  maxLength: 100,
                  decoration: const InputDecoration(
                    labelText: 'Stress level label *',
                    hintText: 'e.g. Low, Moderate, High',
                    border: OutlineInputBorder(),
                  ),
                  validator: (value) => (value == null || value.trim().isEmpty)
                      ? 'A label is required.'
                      : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _codeController,
                  maxLength: 50,
                  decoration: const InputDecoration(
                    labelText: 'Code *',
                    hintText:
                        'e.g. low, moderate, high — unique within this questionnaire',
                    border: OutlineInputBorder(),
                  ),
                  validator: (value) => (value == null || value.trim().isEmpty)
                      ? 'A code is required.'
                      : null,
                ),
                const SizedBox(height: 16),
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _minController,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                          labelText: 'Min score *',
                          border: OutlineInputBorder(),
                        ),
                        validator: (value) => int.tryParse(value ?? '') == null
                            ? 'Enter a whole number.'
                            : null,
                      ),
                    ),
                    const SizedBox(width: 16),
                    Expanded(
                      child: TextFormField(
                        controller: _maxController,
                        keyboardType: TextInputType.number,
                        decoration: const InputDecoration(
                          labelText: 'Max score *',
                          border: OutlineInputBorder(),
                        ),
                        validator: (value) {
                          final max = int.tryParse(value ?? '');
                          final min = int.tryParse(_minController.text);
                          if (max == null) return 'Enter a whole number.';
                          if (min != null && max < min) {
                            return 'Must be ≥ min score.';
                          }
                          return null;
                        },
                      ),
                    ),
                  ],
                ),
                if (widget.isEditing) ...[
                  const SizedBox(height: 8),
                  SwitchListTile(
                    contentPadding: EdgeInsets.zero,
                    title: const Text('Active'),
                    subtitle: const Text(
                      'Inactive ranges are skipped when scoring a submission.',
                    ),
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
                      ? const SizedBox.square(
                          dimension: 20,
                          child: CircularProgressIndicator(strokeWidth: 2),
                        )
                      : Text(
                          widget.isEditing ? 'Save Changes' : 'Add Score Range',
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
