import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../../core/theme/app_theme.dart';
import '../data/diary.dart';
import '../data/diary_lock.dart';

/// Wrong tries before the unlock sheet admits there is no way back in.
///
/// The offer is held back at first because the honest answer — delete it and
/// start again — is not what a student who simply mistyped needs to read.
const _attemptsBeforeOfferingDelete = 3;

/// Asks for a new PIN, twice, and returns it once both entries agree.
///
/// Returns null if the student backs out, which the caller must treat as
/// "leave this diary as it was".
Future<String?> showDiaryPinSetup(
  BuildContext context, {
  required String heading,
  required String subheading,
}) => showModalBottomSheet<String>(
  context: context,
  isScrollControlled: true,
  backgroundColor: AppColors.surface,
  shape: const RoundedRectangleBorder(
    borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
  ),
  builder: (_) => _PinSetupSheet(heading: heading, subheading: subheading),
);

/// What the student did on the unlock sheet.
enum DiaryUnlockOutcome {
  /// The PIN was right — open the diary.
  unlocked,

  /// Backed out. The diary stays locked and shut.
  cancelled,

  /// Gave up on the PIN and confirmed they want the diary deleted.
  deleteRequested,
}

/// Asks for [diary]'s PIN before it opens.
///
/// After [_attemptsBeforeOfferingDelete] wrong tries this also offers the only
/// exit that exists for a forgotten PIN: deleting the diary. There is no
/// lockout and no attempt limit — a student locked out of their own writing by
/// a timer would gain nothing from it.
Future<DiaryUnlockOutcome> showDiaryUnlock(
  BuildContext context, {
  required Diary diary,
  required DiaryLock lock,
}) async {
  final outcome = await showModalBottomSheet<DiaryUnlockOutcome>(
    context: context,
    isScrollControlled: true,
    backgroundColor: AppColors.surface,
    shape: const RoundedRectangleBorder(
      borderRadius: BorderRadius.vertical(top: Radius.circular(28)),
    ),
    builder: (_) => _PinUnlockSheet(diary: diary, lock: lock),
  );
  return outcome ?? DiaryUnlockOutcome.cancelled;
}

class _PinSetupSheet extends StatefulWidget {
  const _PinSetupSheet({required this.heading, required this.subheading});

  final String heading;
  final String subheading;

  @override
  State<_PinSetupSheet> createState() => _PinSetupSheetState();
}

class _PinSetupSheetState extends State<_PinSetupSheet> {
  String _entry = '';

  /// Set once the first entry is complete; the sheet is then confirming.
  String? _firstEntry;
  String? _error;

  bool get _isConfirming => _firstEntry != null;

  void _onChanged(String value) {
    setState(() {
      _entry = value;
      _error = null;
    });
    if (value.length == diaryPinLength) _onComplete(value);
  }

  void _onComplete(String value) {
    final first = _firstEntry;
    if (first == null) {
      setState(() {
        _firstEntry = value;
        _entry = '';
      });
      return;
    }

    if (value == first) {
      Navigator.of(context).pop(value);
      return;
    }

    // Send them back to the first entry rather than making them guess which of
    // the two they got wrong.
    HapticFeedback.heavyImpact();
    setState(() {
      _firstEntry = null;
      _entry = '';
      _error = 'Those did not match. Pick a PIN and try again.';
    });
  }

  @override
  Widget build(BuildContext context) => _PinSheetLayout(
    icon: Icons.lock_outline_rounded,
    heading: _isConfirming ? 'Type it once more' : widget.heading,
    message: _isConfirming
        ? 'Just to be sure you will remember it.'
        : widget.subheading,
    entry: _entry,
    error: _error,
    onChanged: _onChanged,
  );
}

class _PinUnlockSheet extends StatefulWidget {
  const _PinUnlockSheet({required this.diary, required this.lock});

  final Diary diary;

  /// The student's PIN. The same one opens every diary they have locked.
  final DiaryLock lock;

  @override
  State<_PinUnlockSheet> createState() => _PinUnlockSheetState();
}

class _PinUnlockSheetState extends State<_PinUnlockSheet> {
  String _entry = '';
  String? _error;
  int _wrongAttempts = 0;

  bool get _canOfferDelete => _wrongAttempts >= _attemptsBeforeOfferingDelete;

  void _onChanged(String value) {
    setState(() {
      _entry = value;
      _error = null;
    });
    if (value.length == diaryPinLength) _submit(value);
  }

  void _submit(String value) {
    if (widget.lock.matches(value)) {
      Navigator.of(context).pop(DiaryUnlockOutcome.unlocked);
      return;
    }

    HapticFeedback.heavyImpact();
    setState(() {
      _wrongAttempts++;
      _entry = '';
      _error = 'That PIN did not open this diary.';
    });
  }

  Future<void> _offerDelete() async {
    final navigator = Navigator.of(context);
    final pageCount = widget.diary.pages.length;
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete this diary?'),
        content: Text(
          'A forgotten PIN cannot be recovered — nobody at SheZen can read it '
          'or reset it for you.\n\n'
          'Deleting "${widget.diary.title}" will not unlock your other '
          'locked diaries; they use this same PIN.\n\n'
          '${pageCount == 1 ? 'Its 1 page' : 'Its $pageCount pages'} will be '
          'permanently erased. This cannot be undone.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(false),
            child: const Text('Keep it locked'),
          ),
          FilledButton(
            onPressed: () => Navigator.of(context).pop(true),
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(context).colorScheme.error,
            ),
            child: const Text('Delete'),
          ),
        ],
      ),
    );

    if (confirmed == true) navigator.pop(DiaryUnlockOutcome.deleteRequested);
  }

  @override
  Widget build(BuildContext context) => _PinSheetLayout(
    icon: Icons.lock_rounded,
    heading: widget.diary.title,
    message: 'Enter this diary\'s PIN to open it.',
    entry: _entry,
    error: _error,
    onChanged: _onChanged,
    footer: _canOfferDelete
        ? TextButton(
            onPressed: _offerDelete,
            child: const Text('Forgot your PIN?'),
          )
        : null,
  );
}

/// The shared furniture of both sheets: icon, wording, dots and pad.
class _PinSheetLayout extends StatelessWidget {
  const _PinSheetLayout({
    required this.icon,
    required this.heading,
    required this.message,
    required this.entry,
    required this.onChanged,
    this.error,
    this.footer,
  });

  final IconData icon;
  final String heading;
  final String message;
  final String entry;
  final ValueChanged<String> onChanged;
  final String? error;
  final Widget? footer;

  @override
  Widget build(BuildContext context) => SafeArea(
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
          Center(
            child: Container(
              width: 52,
              height: 52,
              decoration: const BoxDecoration(
                color: AppColors.softLavender,
                shape: BoxShape.circle,
              ),
              child: Icon(icon, color: AppColors.primary, size: 25),
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          Text(
            heading,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: Theme.of(context).textTheme.titleLarge,
          ),
          const SizedBox(height: AppSpacing.xs),
          Text(
            message,
            textAlign: TextAlign.center,
            style: const TextStyle(color: AppColors.muted, height: 1.4),
          ),
          const SizedBox(height: AppSpacing.xl),
          _PinDots(filled: entry.length, hasError: error != null),
          const SizedBox(height: AppSpacing.md),
          // Held at a constant height so the pad does not jump as the message
          // appears and clears.
          SizedBox(
            height: 34,
            child: error == null
                ? null
                : Text(
                    error!,
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.error,
                      fontSize: 12.5,
                      height: 1.35,
                    ),
                  ),
          ),
          _PinPad(entry: entry, onChanged: onChanged),
          if (footer case final footer?) ...[
            const SizedBox(height: AppSpacing.sm),
            Center(child: footer),
          ],
        ],
      ),
    ),
  );
}

class _PinDots extends StatelessWidget {
  const _PinDots({required this.filled, required this.hasError});

  final int filled;
  final bool hasError;

  @override
  Widget build(BuildContext context) {
    final errorColor = Theme.of(context).colorScheme.error;
    return Semantics(
      label: '$filled of $diaryPinLength digits entered',
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          for (var index = 0; index < diaryPinLength; index++)
            AnimatedContainer(
              duration: const Duration(milliseconds: 140),
              margin: const EdgeInsets.symmetric(horizontal: AppSpacing.sm),
              width: 15,
              height: 15,
              decoration: BoxDecoration(
                color: index < filled
                    ? (hasError ? errorColor : AppColors.primary)
                    : Colors.transparent,
                shape: BoxShape.circle,
                border: Border.all(
                  color: hasError ? errorColor : AppColors.outline,
                  width: 1.6,
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// Numeric pad.
///
/// A dedicated pad rather than a `TextField`, so the sheet is not fighting the
/// system keyboard for room and the digits can never be autofilled, suggested
/// or copied out of a text field.
class _PinPad extends StatelessWidget {
  const _PinPad({required this.entry, required this.onChanged});

  final String entry;
  final ValueChanged<String> onChanged;

  void _append(String digit) {
    if (entry.length >= diaryPinLength) return;
    HapticFeedback.selectionClick();
    onChanged('$entry$digit');
  }

  void _backspace() {
    if (entry.isEmpty) return;
    HapticFeedback.selectionClick();
    onChanged(entry.substring(0, entry.length - 1));
  }

  @override
  Widget build(BuildContext context) => Column(
    children: [
      for (final row in const [
        ['1', '2', '3'],
        ['4', '5', '6'],
        ['7', '8', '9'],
      ])
        Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            for (final digit in row)
              _PinKey(label: digit, onTap: () => _append(digit)),
          ],
        ),
      Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          const _PinKey.empty(),
          _PinKey(label: '0', onTap: () => _append('0')),
          _PinKey(
            icon: Icons.backspace_outlined,
            semanticLabel: 'Delete last digit',
            onTap: _backspace,
          ),
        ],
      ),
    ],
  );
}

class _PinKey extends StatelessWidget {
  const _PinKey({this.label, this.icon, this.semanticLabel, this.onTap});

  const _PinKey.empty()
    : label = null,
      icon = null,
      semanticLabel = null,
      onTap = null;

  final String? label;
  final IconData? icon;
  final String? semanticLabel;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) {
    const size = 66.0;
    if (onTap == null) return const SizedBox(width: size, height: size);

    return SizedBox(
      width: size,
      height: size,
      child: Semantics(
        button: true,
        label: semanticLabel ?? label,
        child: InkWell(
          onTap: onTap,
          customBorder: const CircleBorder(),
          child: Center(
            child: icon != null
                ? Icon(icon, color: AppColors.muted, size: 22)
                : Text(
                    label!,
                    style: const TextStyle(
                      fontSize: 23,
                      fontWeight: FontWeight.w600,
                      color: AppColors.ink,
                    ),
                  ),
          ),
        ),
      ),
    );
  }
}
