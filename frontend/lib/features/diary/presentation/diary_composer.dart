import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import 'diary_ui.dart';

/// Name-and-cover sheet, shared by "New diary" and "Rename".
///
/// Returns null if the student backs out.
Future<({String title, int coverIndex})?> showDiaryComposer(
  BuildContext context, {
  required String heading,
  required String actionLabel,
  String initialTitle = '',
  int initialCoverIndex = 0,
}) => showModalBottomSheet<({String title, int coverIndex})>(
  context: context,
  isScrollControlled: true,
  backgroundColor: AppColors.surface,
  shape: const RoundedRectangleBorder(
    borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
  ),
  builder: (_) => _DiaryComposerSheet(
    heading: heading,
    actionLabel: actionLabel,
    initialTitle: initialTitle,
    initialCoverIndex: initialCoverIndex,
  ),
);

class _DiaryComposerSheet extends StatefulWidget {
  const _DiaryComposerSheet({
    required this.heading,
    required this.actionLabel,
    required this.initialTitle,
    required this.initialCoverIndex,
  });

  final String heading;
  final String actionLabel;
  final String initialTitle;
  final int initialCoverIndex;

  @override
  State<_DiaryComposerSheet> createState() => _DiaryComposerSheetState();
}

class _DiaryComposerSheetState extends State<_DiaryComposerSheet> {
  late final TextEditingController _controller = TextEditingController(
    text: widget.initialTitle,
  );
  late int _coverIndex = widget.initialCoverIndex;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _submit() {
    final title = _controller.text.trim();
    Navigator.of(
      context,
    ).pop((title: title.isEmpty ? 'My diary' : title, coverIndex: _coverIndex));
  }

  @override
  Widget build(BuildContext context) => Padding(
    // Lifts the sheet clear of the keyboard while typing the name.
    padding: EdgeInsets.only(bottom: MediaQuery.of(context).viewInsets.bottom),
    child: SafeArea(
      child: Padding(
        padding: const EdgeInsets.fromLTRB(
          AppSpacing.xxl,
          AppSpacing.lg,
          AppSpacing.xxl,
          AppSpacing.xxl,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Center(
              child: Container(
                width: 42,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.outline,
                  borderRadius: BorderRadius.circular(AppRadii.pill),
                ),
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            Text(widget.heading, style: Theme.of(context).textTheme.titleLarge),
            const SizedBox(height: AppSpacing.lg),
            TextField(
              controller: _controller,
              autofocus: true,
              textCapitalization: TextCapitalization.sentences,
              textInputAction: TextInputAction.done,
              onSubmitted: (_) => _submit(),
              decoration: const InputDecoration(
                labelText: 'Diary name',
                hintText: 'Quiet thoughts',
              ),
            ),
            const SizedBox(height: AppSpacing.xl),
            const Text(
              'Cover',
              style: TextStyle(
                color: AppColors.muted,
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
                letterSpacing: 0.4,
              ),
            ),
            const SizedBox(height: AppSpacing.md),
            Wrap(
              spacing: AppSpacing.md,
              runSpacing: AppSpacing.md,
              children: [
                for (var index = 0; index < diaryCoverColors.length; index++)
                  _CoverSwatch(
                    index: index,
                    isSelected: index == _coverIndex,
                    onTap: () => setState(() => _coverIndex = index),
                  ),
              ],
            ),
            const SizedBox(height: AppSpacing.xxl),
            FilledButton(onPressed: _submit, child: Text(widget.actionLabel)),
          ],
        ),
      ),
    ),
  );
}

class _CoverSwatch extends StatelessWidget {
  const _CoverSwatch({
    required this.index,
    required this.isSelected,
    required this.onTap,
  });

  final int index;
  final bool isSelected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Semantics(
    selected: isSelected,
    button: true,
    label: 'Cover ${index + 1}',
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 160),
        width: 48,
        height: 48,
        decoration: BoxDecoration(
          color: diaryCoverColor(index),
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: isSelected ? AppColors.primary : AppColors.outline,
            width: isSelected ? 2.4 : 1,
          ),
        ),
        child: isSelected
            ? const Icon(
                Icons.check_rounded,
                color: AppColors.primary,
                size: 20,
              )
            : null,
      ),
    ),
  );
}
