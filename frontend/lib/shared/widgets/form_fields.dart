import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import '../data/reference_data.dart';

const _fieldLabelStyle = TextStyle(
  color: AppColors.ink,
  fontSize: 14,
  fontWeight: FontWeight.w600,
);

/// The youngest a participant may be when they register.
const int minimumParticipantAge = 18;

/// Why [value] is not an acceptable date of birth, or null if it is.
///
/// The whole date counts, not just the year: someone born in the cut-off
/// year whose birthday is still to come this year is not yet old enough.
/// [today] is injectable so the boundary can be tested on a fixed day.
String? validateDateOfBirth(
  DateTime? value, {
  int minimumAge = minimumParticipantAge,
  DateTime? today,
}) {
  if (value == null) return 'Select your date of birth.';
  final now = today ?? DateTime.now();
  final endOfToday = DateTime(now.year, now.month, now.day + 1);
  if (!value.isBefore(endOfToday)) {
    return 'Date of birth cannot be in the future.';
  }
  // The latest birthday that has already had its [minimumAge]th anniversary.
  final latestEligible = DateTime(now.year - minimumAge, now.month, now.day);
  if (value.isAfter(latestEligible)) {
    return 'You must be $minimumAge years or older to participate.';
  }
  return null;
}

/// A row of selectable chips backed by [controller]'s text — used for any
/// demographic field with a short, fixed set of options.
class ChoiceField extends StatelessWidget {
  const ChoiceField({
    super.key,
    required this.controller,
    required this.label,
    required this.options,
    this.errorText,
    this.onChanged,
  });

  final TextEditingController controller;
  final String label;
  final List<String> options;
  final String? errorText;

  /// Fires after the controller has taken the new option, for parents that
  /// show or hide something based on the choice.
  final ValueChanged<String>? onChanged;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 20),
    child: FormField<String>(
      initialValue: controller.text.isEmpty ? null : controller.text,
      validator: (value) => value == null || value.isEmpty
          ? 'Select your ${label.toLowerCase()}.'
          : null,
      builder: (state) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: _fieldLabelStyle),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              for (final option in options)
                ChoiceChip(
                  label: Text(option),
                  selected: controller.text == option,
                  selectedColor: AppColors.primary,
                  backgroundColor: Colors.white,
                  side: const BorderSide(color: Color(0xFFD2DDDA)),
                  labelStyle: TextStyle(
                    color: controller.text == option
                        ? Colors.white
                        : AppColors.ink,
                    fontSize: 12,
                  ),
                  onSelected: (_) {
                    controller.text = option;
                    state.didChange(option);
                    onChanged?.call(option);
                  },
                ),
            ],
          ),
          if (errorText ?? state.errorText case final String message) ...[
            const SizedBox(height: 6),
            Text(
              message,
              style: TextStyle(
                color: Theme.of(context).colorScheme.error,
                fontSize: 12,
              ),
            ),
          ],
        ],
      ),
    ),
  );
}

/// A Yes/No chip pair for a nullable boolean demographic field.
class BooleanChoiceField extends StatelessWidget {
  const BooleanChoiceField({
    super.key,
    required this.label,
    required this.value,
    required this.onChanged,
    this.errorText,
  });

  final String label;
  final bool? value;
  final ValueChanged<bool> onChanged;
  final String? errorText;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 20),
    child: FormField<bool>(
      initialValue: value,
      validator: (value) => value == null ? 'Select an answer.' : null,
      builder: (state) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(label, style: _fieldLabelStyle),
          const SizedBox(height: 8),
          Wrap(
            spacing: 8,
            children: [
              for (final option in const [(false, 'No'), (true, 'Yes')])
                ChoiceChip(
                  label: Text(option.$2),
                  selected: value == option.$1,
                  selectedColor: AppColors.primary,
                  backgroundColor: Colors.white,
                  side: const BorderSide(color: Color(0xFFD2DDDA)),
                  labelStyle: TextStyle(
                    color: value == option.$1 ? Colors.white : AppColors.ink,
                    fontSize: 12,
                  ),
                  onSelected: (_) {
                    onChanged(option.$1);
                    state.didChange(option.$1);
                  },
                ),
            ],
          ),
          if (errorText ?? state.errorText case final String message) ...[
            const SizedBox(height: 6),
            Text(
              message,
              style: TextStyle(
                color: Theme.of(context).colorScheme.error,
                fontSize: 12,
              ),
            ),
          ],
        ],
      ),
    ),
  );
}

/// Date-of-birth picker. Age is always derived server-side from this value
/// and is never itself editable. Rejects future dates and anyone under
/// [minimumParticipantAge] as soon as a date is picked, so the form cannot
/// move on until the date is acceptable.
class DateOfBirthField extends StatelessWidget {
  const DateOfBirthField({
    super.key,
    required this.value,
    required this.onChanged,
    this.errorText,
    this.today,
  });

  final DateTime? value;
  final ValueChanged<DateTime> onChanged;
  final String? errorText;

  /// Overrides the clock, for tests that sit on the 18th-birthday boundary.
  final DateTime? today;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 20),
    child: FormField<DateTime>(
      initialValue: value,
      autovalidateMode: AutovalidateMode.onUserInteraction,
      validator: (value) => validateDateOfBirth(value, today: today),
      builder: (state) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Date of birth', style: _fieldLabelStyle),
          const SizedBox(height: 8),
          InkWell(
            borderRadius: BorderRadius.circular(13),
            onTap: () async {
              final now = today ?? DateTime.now();
              final picked = await showDatePicker(
                context: context,
                initialDate:
                    value ??
                    DateTime(
                      now.year - minimumParticipantAge,
                      now.month,
                      now.day,
                    ),
                firstDate: DateTime(now.year - 100),
                lastDate: now,
                helpText: 'You must be $minimumParticipantAge or older',
              );
              if (picked != null) {
                onChanged(picked);
                state.didChange(picked);
              }
            },
            child: InputDecorator(
              decoration: InputDecoration(
                hintText: 'Select date of birth',
                errorText: errorText ?? state.errorText,
                isDense: true,
                filled: true,
                fillColor: Colors.white,
                suffixIcon: const Icon(Icons.calendar_today_outlined, size: 18),
                contentPadding: const EdgeInsets.symmetric(
                  horizontal: 14,
                  vertical: 13,
                ),
              ),
              child: Text(
                value == null
                    ? ''
                    : '${value!.day.toString().padLeft(2, '0')}/'
                          '${value!.month.toString().padLeft(2, '0')}/'
                          '${value!.year}',
              ),
            ),
          ),
        ],
      ),
    ),
  );
}

/// Year of study as chips, plus a "Please specify" box that exists only
/// while "Other" is chosen. Picking any other option hides the box and
/// discards what was typed, so a stale explanation is never sent.
class YearOfStudyField extends StatefulWidget {
  const YearOfStudyField({
    super.key,
    required this.controller,
    required this.detailController,
    this.errorText,
    this.detailErrorText,
  });

  final TextEditingController controller;
  final TextEditingController detailController;
  final String? errorText;
  final String? detailErrorText;

  @override
  State<YearOfStudyField> createState() => _YearOfStudyFieldState();
}

class _YearOfStudyFieldState extends State<YearOfStudyField> {
  bool get _isOther => widget.controller.text == ReferenceData.yearOfStudyOther;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.stretch,
    children: [
      ChoiceField(
        controller: widget.controller,
        label: 'Year of study',
        options: ReferenceData.yearOfStudy,
        errorText: widget.errorText,
        onChanged: (value) {
          if (value != ReferenceData.yearOfStudyOther) {
            widget.detailController.clear();
          }
          setState(() {});
        },
      ),
      AnimatedSize(
        duration: const Duration(milliseconds: 180),
        alignment: Alignment.topCenter,
        child: _isOther
            ? Padding(
                padding: const EdgeInsets.only(bottom: 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text('Please specify', style: _fieldLabelStyle),
                    const SizedBox(height: 8),
                    TextFormField(
                      key: const Key('year-of-study-detail'),
                      controller: widget.detailController,
                      maxLength: 100,
                      textCapitalization: TextCapitalization.sentences,
                      validator: (value) => (value ?? '').trim().isEmpty
                          ? 'Please specify your year of study.'
                          : null,
                      decoration: InputDecoration(
                        hintText: 'Enter your year/status...',
                        errorText: widget.detailErrorText,
                        counterText: '',
                        isDense: true,
                        filled: true,
                        fillColor: Colors.white,
                        contentPadding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 13,
                        ),
                      ),
                    ),
                  ],
                ),
              )
            : const SizedBox.shrink(),
      ),
    ],
  );
}

/// Country picker backed by the full [ReferenceData.countries] list. The
/// form itself only shows the chosen country; tapping opens a searchable
/// sheet so ~250 entries never crowd the screen.
class CountryField extends StatelessWidget {
  const CountryField({super.key, required this.controller, this.errorText});

  final TextEditingController controller;
  final String? errorText;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 20),
    child: FormField<String>(
      initialValue: controller.text.isEmpty ? null : controller.text,
      validator: (value) =>
          value == null || value.isEmpty ? 'Select your country.' : null,
      builder: (state) {
        final selected = controller.text.isEmpty ? null : controller.text;
        return Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text('Country', style: _fieldLabelStyle),
            const SizedBox(height: 8),
            InkWell(
              key: const Key('country-field'),
              borderRadius: BorderRadius.circular(13),
              onTap: () async {
                final picked = await showCountryPicker(
                  context,
                  selected: selected,
                );
                if (picked == null) return;
                controller.text = picked;
                state.didChange(picked);
              },
              child: InputDecorator(
                isEmpty: selected == null,
                decoration: InputDecoration(
                  hintText: 'Search country...',
                  errorText: errorText ?? state.errorText,
                  isDense: true,
                  filled: true,
                  fillColor: Colors.white,
                  suffixIcon: Icon(
                    selected == null
                        ? Icons.search_rounded
                        : Icons.check_circle_rounded,
                    size: 18,
                    color: selected == null
                        ? AppColors.muted
                        : AppColors.primary,
                  ),
                  contentPadding: const EdgeInsets.symmetric(
                    horizontal: 14,
                    vertical: 13,
                  ),
                ),
                child: Text(
                  selected ?? '',
                  style: const TextStyle(
                    color: AppColors.ink,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ),
          ],
        );
      },
    ),
  );
}

/// Opens the searchable country sheet; resolves to the chosen country, or
/// null if the sheet was dismissed.
Future<String?> showCountryPicker(BuildContext context, {String? selected}) =>
    showModalBottomSheet<String>(
      context: context,
      isScrollControlled: true,
      showDragHandle: true,
      useSafeArea: true,
      builder: (sheetContext) => DraggableScrollableSheet(
        expand: false,
        initialChildSize: 0.85,
        maxChildSize: 0.95,
        builder: (_, scrollController) => _CountryPickerSheet(
          selected: selected,
          scrollController: scrollController,
        ),
      ),
    );

class _CountryPickerSheet extends StatefulWidget {
  const _CountryPickerSheet({
    required this.selected,
    required this.scrollController,
  });

  final String? selected;
  final ScrollController scrollController;

  @override
  State<_CountryPickerSheet> createState() => _CountryPickerSheetState();
}

class _CountryPickerSheetState extends State<_CountryPickerSheet> {
  final _searchController = TextEditingController();
  List<String> _matches = ReferenceData.countries;

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  void _filter(String query) {
    final needle = _searchKey(query);
    setState(() {
      _matches = needle.isEmpty
          ? ReferenceData.countries
          : [
              for (final country in ReferenceData.countries)
                if (_searchKey(country).contains(needle)) country,
            ];
    });
  }

  @override
  Widget build(BuildContext context) => Padding(
    // Keeps the list above the keyboard while the student is typing.
    padding: EdgeInsets.only(bottom: MediaQuery.viewInsetsOf(context).bottom),
    child: Column(
      children: [
        Padding(
          padding: const EdgeInsets.fromLTRB(20, 0, 20, 12),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text('Country', style: Theme.of(context).textTheme.titleLarge),
              const SizedBox(height: 10),
              TextField(
                key: const Key('country-search'),
                controller: _searchController,
                autofocus: true,
                textInputAction: TextInputAction.search,
                onChanged: _filter,
                decoration: InputDecoration(
                  hintText: 'Search country...',
                  prefixIcon: const Icon(Icons.search_rounded, size: 20),
                  suffixIcon: _searchController.text.isEmpty
                      ? null
                      : IconButton(
                          icon: const Icon(Icons.close_rounded, size: 18),
                          tooltip: 'Clear search',
                          onPressed: () {
                            _searchController.clear();
                            _filter('');
                          },
                        ),
                  isDense: true,
                  filled: true,
                  fillColor: Colors.white,
                ),
              ),
            ],
          ),
        ),
        const Divider(height: 1),
        Expanded(
          child: _matches.isEmpty
              ? const Center(
                  child: Text(
                    'No countries match your search.',
                    style: TextStyle(color: AppColors.muted),
                  ),
                )
              : ListView.builder(
                  controller: widget.scrollController,
                  itemCount: _matches.length,
                  itemBuilder: (context, index) {
                    final country = _matches[index];
                    final isSelected = country == widget.selected;
                    return ListTile(
                      dense: true,
                      selected: isSelected,
                      selectedColor: AppColors.primary,
                      selectedTileColor: AppColors.softLavender,
                      title: Text(
                        country,
                        style: TextStyle(
                          fontWeight: isSelected
                              ? FontWeight.w700
                              : FontWeight.w500,
                        ),
                      ),
                      trailing: isSelected
                          ? const Icon(Icons.check_rounded, size: 20)
                          : null,
                      onTap: () => Navigator.of(context).pop(country),
                    );
                  },
                ),
        ),
      ],
    ),
  );
}

/// Lower-cased and stripped of accents, so "cote" finds Côte d'Ivoire and
/// "turkiye" finds Türkiye.
String _searchKey(String text) {
  const accents = {
    'á': 'a', 'à': 'a', 'â': 'a', 'ä': 'a', 'ã': 'a', 'å': 'a',
    'é': 'e', 'è': 'e', 'ê': 'e', 'ë': 'e',
    'í': 'i', 'ì': 'i', 'î': 'i', 'ï': 'i',
    'ó': 'o', 'ò': 'o', 'ô': 'o', 'ö': 'o', 'õ': 'o',
    'ú': 'u', 'ù': 'u', 'û': 'u', 'ü': 'u',
    'ç': 'c', 'ñ': 'n',
  };
  final buffer = StringBuffer();
  for (final rune in text.trim().toLowerCase().runes) {
    final char = String.fromCharCode(rune);
    buffer.write(accents[char] ?? char);
  }
  return buffer.toString();
}
