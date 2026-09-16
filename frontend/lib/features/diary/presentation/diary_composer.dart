import 'package:flutter/material.dart';

import '../../../core/theme/app_theme.dart';
import '../data/diary_lock.dart';
import 'diary_ui.dart';

/// Name-and-cover sheet, shared by "New diary" and "Rename".
///
/// Returns null if the student backs out. [lockWithPin] is only ever true when
/// [offerPin] was set; the caller is responsible for actually asking for the
/// PIN, because choosing one needs a screen of its own.
Future<({String title, int coverIndex, bool lockWithPin})?> showDiaryComposer(
  BuildContext context, {
  required String heading,
  required String actionLabel,
  String initialTitle = '',
  int initialCoverIndex = 0,
  bool offerPin = false,
}) => showModalBottomSheet<({String title, int coverIndex, bool lockWithPin})>(
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
    offerPin: offerPin,
  ),
);

class _DiaryComposerSheet extends StatefulWidget {
  const _DiaryComposerSheet({
    required this.heading,
    required this.actionLabel,
    required this.initialTitle,
    required this.initialCoverIndex,
    required this.offerPin,
  });

  final String heading;
  final String actionLabel;
  final String initialTitle;
  final int initialCoverIndex;

  /// Renaming an existing diary does not show the toggle — that diary's PIN is
  /// managed from its own screen, where adding, changing and removing one all
  /// live together.
  final bool offerPin;

  @override
  State<_DiaryComposerSheet> createState() => _DiaryComposerSheetState();
}

class _DiaryComposerSheetState extends State<_DiaryComposerSheet> {
  late final TextEditingController _controller = TextEditingController(
    text: widget.initialTitle,
  );
  late int _coverIndex = widget.initialCoverIndex;
  bool _lockWithPin = false;

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  void _submit() {
    final title = _controller.text.trim();
    Navigator.of(context).pop((
      title: title.isEmpty ? 'My diary' : title,
      coverIndex: _coverIndex,
      lockWithPin: widget.offerPin && _lockWithPin,
    ));
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
            if (widget.offerPin) ...[
              const SizedBox(height: AppSpacing.xl),
              _LockToggle(
                value: _lockWithPin,
                onChanged: (value) => setState(() => _lockWithPin = value),
              ),
            ],
            const SizedBox(height: AppSpacing.xxl),
            FilledButton(onPressed: _submit, child: Text(widget.actionLabel)),
          ],
        ),
      ),
    ),
  );
}

/// Opt-in row for putting a PIN on a diary as it is created.
///
/// States the cost up front rather than after the fact: a diary that only
/// exists on this phone has nothing behind it to reset a forgotten PIN from.
class _LockToggle extends StatelessWidget {
  const _LockToggle({required this.value, required this.onChanged});

  final bool value;
  final ValueChanged<bool> onChanged;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.fromLTRB(
      AppSpacing.lg,
      AppSpacing.sm,
      AppSpacing.sm,
      AppSpacing.sm,
    ),
    decoration: BoxDecoration(
      color: value ? AppColors.softLavender : AppColors.surface,
      borderRadius: BorderRadius.circular(AppRadii.card),
      border: Border.all(
        color: value
            ? AppColors.primary.withValues(alpha: 0.25)
            : AppColors.outline,
      ),
    ),
    child: Row(
      children: [
        Expanded(
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                'Lock with a PIN',
                style: Theme.of(
                  context,
                ).textTheme.titleSmall?.copyWith(fontWeight: FontWeight.w800),
              ),
              const SizedBox(height: 2),
              Text(
                value
                    ? 'You will pick a $diaryPinLength-digit PIN next. Keep it '
                          'somewhere safe — a forgotten PIN cannot be reset.'
                    : 'Ask for a $diaryPinLength-digit PIN before this diary '
                          'opens.',
                style: const TextStyle(
                  color: AppColors.muted,
                  fontSize: 12.5,
                  height: 1.35,
                ),
              ),
            ],
          ),
        ),
        Switch(value: value, onChanged: onChanged),
      ],
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
