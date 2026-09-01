import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';
import '../data/reference_data.dart';

const _fieldLabelStyle = TextStyle(
  color: AppColors.ink,
  fontSize: 14,
  fontWeight: FontWeight.w600,
);

/// A row of selectable chips backed by [controller]'s text — used for any
/// demographic field with a short, fixed set of options.
class ChoiceField extends StatelessWidget {
  const ChoiceField({
    super.key,
    required this.controller,
    required this.label,
    required this.options,
    this.errorText,
  });

  final TextEditingController controller;
  final String label;
  final List<String> options;
  final String? errorText;

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
/// and is never itself editable.
class DateOfBirthField extends StatelessWidget {
  const DateOfBirthField({
    super.key,
    required this.value,
    required this.onChanged,
    this.errorText,
  });

  final DateTime? value;
  final ValueChanged<DateTime> onChanged;
  final String? errorText;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 20),
    child: FormField<DateTime>(
      initialValue: value,
      validator: (value) => value == null ? 'Select your date of birth.' : null,
      builder: (state) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Date of birth', style: _fieldLabelStyle),
          const SizedBox(height: 8),
          InkWell(
            borderRadius: BorderRadius.circular(13),
            onTap: () async {
              final now = DateTime.now();
              final picked = await showDatePicker(
                context: context,
                initialDate:
                    value ?? DateTime(now.year - 18, now.month, now.day),
                firstDate: DateTime(now.year - 100),
                lastDate: now,
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

/// Country picker backed by the full [ReferenceData.countries] list — not
/// restricted to any single region.
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
      builder: (state) => Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text('Country', style: _fieldLabelStyle),
          const SizedBox(height: 8),
          DropdownMenu<String>(
            initialSelection: controller.text.isEmpty ? null : controller.text,
            hintText: 'Select country',
            width: double.infinity,
            errorText: errorText ?? state.errorText,
            dropdownMenuEntries: [
              for (final country in ReferenceData.countries)
                DropdownMenuEntry(value: country, label: country),
            ],
            onSelected: (value) {
              if (value == null) return;
              controller.text = value;
              state.didChange(value);
            },
          ),
        ],
      ),
    ),
  );
}
