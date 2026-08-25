import 'package:flutter/material.dart';

import '../application/questionnaire_builder_provider.dart';
import '../data/question_option.dart';
import '../data/scale_template.dart';
import '../data/stress_question.dart';
import 'widgets/confirm_dialog.dart';

bool _typeRequiresOptions(String type) {
  // Every question type this backend currently supports ('scale') is
  // option-based. Kept as a function so a future free-text/numeric type
  // can opt out without touching the rest of this screen.
  return true;
}

String _slugify(String label) {
  final lower = label.trim().toLowerCase();
  final slug = lower.replaceAll(RegExp(r'[^a-z0-9]+'), '_').replaceAll(RegExp(r'^_+|_+$'), '');
  return slug;
}

class QuestionFormScreen extends StatefulWidget {
  const QuestionFormScreen({super.key, required this.builderProvider, this.question});

  final QuestionnaireBuilderProvider builderProvider;
  final StressQuestion? question;

  bool get isEditing => question != null;

  @override
  State<QuestionFormScreen> createState() => _QuestionFormScreenState();
}

class _OptionRow {
  _OptionRow({required this.localKey, QuestionOption? option})
    : id = option?.id,
      // An option loaded from an already-saved question keeps its own
      // value as-is; a fresh row auto-derives its value from the label
      // until the admin types into the value field themselves.
      valueManuallyEdited = option != null,
      labelController = TextEditingController(text: option?.label ?? ''),
      valueController = TextEditingController(text: option?.value ?? ''),
      scoreController = TextEditingController(text: option?.score?.toString() ?? '') {
    labelController.addListener(_autoFillValue);
  }

  final Key localKey;
  final int? id;
  final TextEditingController labelController;
  final TextEditingController valueController;
  final TextEditingController scoreController;
  bool valueManuallyEdited;
  bool _isAutoFilling = false;

  void _autoFillValue() {
    if (valueManuallyEdited) return;
    _isAutoFilling = true;
    valueController.text = _slugify(labelController.text);
    _isAutoFilling = false;
  }

  void onValueEditedByUser() {
    if (!_isAutoFilling) valueManuallyEdited = true;
  }

  void fillFrom(ScaleOption option) {
    labelController.text = option.label;
    valueManuallyEdited = false;
    valueController.text = option.value;
    scoreController.text = option.score.toString();
  }

  void dispose() {
    labelController.dispose();
    valueController.dispose();
    scoreController.dispose();
  }
}

class _QuestionFormScreenState extends State<QuestionFormScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _textController;
  late final TextEditingController _dimensionController;
  String _questionType = QuestionType.scale;
  bool _isRequired = true;
  bool _isSaving = false;
  bool _dirty = false;
  String? _optionsError;
  final List<_OptionRow> _options = [];
  int _nextLocalKey = 0;
  String? _appliedTemplateId;

  @override
  void initState() {
    super.initState();
    final q = widget.question;
    _textController = TextEditingController(text: q?.questionText ?? '');
    _dimensionController = TextEditingController(text: q?.dimension ?? '');
    _questionType = q?.questionType ?? QuestionType.scale;
    _isRequired = q?.isRequired ?? true;

    _textController.addListener(_markDirty);
    _dimensionController.addListener(_markDirty);

    if (q != null && q.options.isNotEmpty) {
      for (final option in q.options) {
        _options.add(_OptionRow(localKey: ValueKey(_nextLocalKey++), option: option));
      }
    } else {
      // New questions start pre-filled with the standard scale — the admin
      // only has to type the question text unless they want something
      // different, rather than building every option by hand.
      _applyTemplate(ScaleTemplates.defaultTemplate, markDirty: false);
    }
  }

  void _markDirty() {
    if (!_dirty) setState(() => _dirty = true);
  }

  Future<void> _onTemplateSelected(ScaleTemplate template) async {
    final hasContent = _options.any((row) => row.labelController.text.trim().isNotEmpty);
    if (hasContent) {
      final confirmed = await showConfirmDialog(
        context,
        title: 'Replace current options?',
        message: 'Applying "${template.name}" will replace the options below with this scale\'s '
            'labels and scores.',
        confirmLabel: 'Apply',
        destructive: false,
      );
      if (!confirmed) return;
    }
    _applyTemplate(template);
  }

  void _applyTemplate(ScaleTemplate template, {bool markDirty = true}) {
    setState(() {
      for (final row in _options) {
        row.dispose();
      }
      _options.clear();
      for (final option in template.options) {
        final row = _OptionRow(localKey: ValueKey(_nextLocalKey++));
        row.fillFrom(option);
        _options.add(row);
      }
      _appliedTemplateId = template.id;
      _optionsError = null;
      if (markDirty) _dirty = true;
    });
  }

  void _addOption() {
    setState(() {
      _options.add(_OptionRow(localKey: ValueKey(_nextLocalKey++)));
      _appliedTemplateId = null;
      _dirty = true;
    });
  }

  void _removeOption(_OptionRow row) {
    setState(() {
      _options.remove(row);
      row.dispose();
      _appliedTemplateId = null;
      _dirty = true;
    });
  }

  @override
  void dispose() {
    _textController.dispose();
    _dimensionController.dispose();
    for (final row in _options) {
      row.dispose();
    }
    super.dispose();
  }

  List<Map<String, dynamic>>? _collectOptions() {
    if (!_typeRequiresOptions(_questionType)) return [];

    if (_options.length < 2) {
      setState(() => _optionsError = 'Add at least 2 options.');
      return null;
    }

    final values = <String>{};
    final result = <Map<String, dynamic>>[];
    for (final row in _options) {
      final label = row.labelController.text.trim();
      final value = row.valueController.text.trim();
      if (label.isEmpty || value.isEmpty) {
        setState(() => _optionsError = 'Every option needs a label and a value.');
        return null;
      }
      if (!values.add(value)) {
        setState(() => _optionsError = 'Option values must be unique ("$value" is repeated).');
        return null;
      }
      final scoreText = row.scoreController.text.trim();
      result.add({
        if (row.id != null) 'id': row.id,
        'label': label,
        'value': value,
        'score': scoreText.isEmpty ? null : int.tryParse(scoreText),
      });
    }
    setState(() => _optionsError = null);
    return result;
  }

  Future<void> _submit() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    final options = _collectOptions();
    if (options == null) return;

    setState(() => _isSaving = true);

    final provider = widget.builderProvider;
    final bool ok;
    if (widget.isEditing) {
      ok = await provider.updateQuestion(
        questionId: widget.question!.id,
        questionText: _textController.text.trim(),
        dimension: _dimensionController.text.trim().isEmpty ? null : _dimensionController.text.trim(),
        questionType: _questionType,
        isRequired: _isRequired,
        options: options,
      );
    } else {
      ok = await provider.addQuestion(
        questionText: _textController.text.trim(),
        dimension: _dimensionController.text.trim().isEmpty ? null : _dimensionController.text.trim(),
        questionType: _questionType,
        isRequired: _isRequired,
        options: options,
      );
    }

    if (!mounted) return;
    setState(() => _isSaving = false);

    if (ok) {
      _dirty = false;
      Navigator.of(context).pop();
    } else {
      final message = provider.actionError ?? 'Failed to save question.';
      ScaffoldMessenger.of(context).showSnackBar(SnackBar(content: Text(message)));
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
    final showOptions = _typeRequiresOptions(_questionType);

    return PopScope(
      canPop: !_dirty,
      onPopInvokedWithResult: (didPop, result) async {
        if (didPop) return;
        if (await _confirmDiscard() && mounted) {
          Navigator.of(context).pop();
        }
      },
      child: Scaffold(
        appBar: AppBar(title: Text(widget.isEditing ? 'Edit Question' : 'Add Question')),
        body: SingleChildScrollView(
          padding: const EdgeInsets.all(16),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                TextFormField(
                  controller: _textController,
                  maxLength: 2000,
                  maxLines: 3,
                  decoration: const InputDecoration(labelText: 'Question Text *', border: OutlineInputBorder()),
                  validator: (value) =>
                      (value == null || value.trim().isEmpty) ? 'Question text is required.' : null,
                ),
                const SizedBox(height: 16),
                TextFormField(
                  controller: _dimensionController,
                  maxLength: 100,
                  decoration: const InputDecoration(
                    labelText: 'Dimension (optional)',
                    border: OutlineInputBorder(),
                    helperText: 'e.g. sleep, workload — for grouping/reporting.',
                  ),
                ),
                const SizedBox(height: 16),
                DropdownButtonFormField<String>(
                  initialValue: _questionType,
                  decoration: const InputDecoration(labelText: 'Question Type *', border: OutlineInputBorder()),
                  items: QuestionType.values
                      .map((t) => DropdownMenuItem(value: t, child: Text(QuestionType.label(t))))
                      .toList(),
                  onChanged: (value) {
                    if (value == null) return;
                    setState(() {
                      _questionType = value;
                      _dirty = true;
                    });
                  },
                ),
                const SizedBox(height: 8),
                SwitchListTile(
                  contentPadding: EdgeInsets.zero,
                  title: const Text('Required'),
                  value: _isRequired,
                  onChanged: (value) => setState(() {
                    _isRequired = value;
                    _dirty = true;
                  }),
                ),
                if (showOptions) ...[
                  const SizedBox(height: 16),
                  DropdownButtonFormField<String>(
                    initialValue: _appliedTemplateId,
                    decoration: const InputDecoration(
                      labelText: 'Scale',
                      border: OutlineInputBorder(),
                      helperText: 'Pick a ready-made answer scale, or build custom options below.',
                    ),
                    hint: const Text('Custom options'),
                    items: [
                      for (final template in ScaleTemplates.all)
                        DropdownMenuItem(value: template.id, child: Text(template.name)),
                    ],
                    onChanged: (id) {
                      final template = ScaleTemplates.all.where((t) => t.id == id).firstOrNull;
                      if (template != null) _onTemplateSelected(template);
                    },
                  ),
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      Text('Answer options', style: Theme.of(context).textTheme.titleSmall),
                      const Spacer(),
                      TextButton.icon(
                        onPressed: _addOption,
                        icon: const Icon(Icons.add),
                        label: const Text('Add option'),
                      ),
                    ],
                  ),
                  if (_optionsError != null)
                    Padding(
                      padding: const EdgeInsets.only(bottom: 8),
                      child: Text(_optionsError!, style: TextStyle(color: Theme.of(context).colorScheme.error)),
                    ),
                  ..._options.map(
                    (row) => _OptionEditor(
                      key: row.localKey,
                      row: row,
                      onRemove: _options.length > 2 ? () => _removeOption(row) : null,
                      onChanged: () {
                        _appliedTemplateId = null;
                        _markDirty();
                      },
                    ),
                  ),
                ],
                const SizedBox(height: 24),
                FilledButton(
                  onPressed: _isSaving ? null : _submit,
                  child: _isSaving
                      ? const SizedBox.square(dimension: 20, child: CircularProgressIndicator(strokeWidth: 2))
                      : Text(widget.isEditing ? 'Save Changes' : 'Add Question'),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

extension _FirstOrNull<T> on Iterable<T> {
  T? get firstOrNull => isEmpty ? null : first;
}

class _OptionEditor extends StatelessWidget {
  const _OptionEditor({super.key, required this.row, required this.onRemove, required this.onChanged});

  final _OptionRow row;
  final VoidCallback? onRemove;
  final VoidCallback onChanged;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      child: Padding(
        padding: const EdgeInsets.all(8),
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(
              flex: 3,
              child: TextFormField(
                controller: row.labelController,
                decoration: const InputDecoration(labelText: 'Label', isDense: true),
                onChanged: (_) => onChanged(),
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              flex: 2,
              child: TextFormField(
                controller: row.valueController,
                decoration: const InputDecoration(labelText: 'Value', isDense: true, helperText: 'auto'),
                onChanged: (_) {
                  row.onValueEditedByUser();
                  onChanged();
                },
              ),
            ),
            const SizedBox(width: 8),
            Expanded(
              flex: 1,
              child: TextFormField(
                controller: row.scoreController,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(labelText: 'Score', isDense: true),
                onChanged: (_) => onChanged(),
              ),
            ),
            IconButton(
              icon: const Icon(Icons.remove_circle_outline),
              onPressed: onRemove,
              tooltip: onRemove == null ? 'At least 2 options are required' : 'Remove option',
            ),
          ],
        ),
      ),
    );
  }
}
