import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../../shared/data/reference_data.dart';
import '../../../shared/widgets/form_fields.dart';
import '../../auth/application/auth_provider.dart';
import '../data/student_profile.dart';

class EditProfileScreen extends StatefulWidget {
  const EditProfileScreen({
    super.key,
    required this.profile,
    ApiService? apiService,
  }) : _injectedApiService = apiService;

  final StudentProfile profile;
  final ApiService? _injectedApiService;

  @override
  State<EditProfileScreen> createState() => _EditProfileScreenState();
}

class _EditProfileScreenState extends State<EditProfileScreen> {
  final _formKey = GlobalKey<FormState>();
  late final TextEditingController _countryController;
  late final TextEditingController _yearOfStudyController;
  late final TextEditingController _yearOfStudyDetailController;
  late final TextEditingController _employmentController;
  late final TextEditingController _relationshipController;
  late final TextEditingController _livingSituationController;
  DateTime? _dateOfBirth;
  bool? _hasChildren;
  bool _isSaving = false;
  Map<String, List<String>>? _fieldErrors;

  @override
  void initState() {
    super.initState();
    final profile = widget.profile;
    _countryController = TextEditingController(text: profile.country ?? '');
    _yearOfStudyController = TextEditingController(
      text: profile.yearOfStudy ?? '',
    );
    _yearOfStudyDetailController = TextEditingController(
      text: profile.yearOfStudyDetail ?? '',
    );
    _employmentController = TextEditingController(
      text: profile.employmentStatus ?? '',
    );
    _relationshipController = TextEditingController(
      text: profile.relationshipStatus ?? '',
    );
    _livingSituationController = TextEditingController(
      text: profile.livingSituation ?? '',
    );
    _dateOfBirth = profile.dateOfBirth == null
        ? null
        : DateTime.tryParse(profile.dateOfBirth!);
    _hasChildren = profile.hasChildren;
  }

  @override
  void dispose() {
    _countryController.dispose();
    _yearOfStudyController.dispose();
    _yearOfStudyDetailController.dispose();
    _employmentController.dispose();
    _relationshipController.dispose();
    _livingSituationController.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;
    setState(() {
      _isSaving = true;
      _fieldErrors = null;
    });

    final token = context.read<AuthProvider>().session!.token;
    final api = widget._injectedApiService ?? ApiService();
    try {
      await api.updateProfile(token, {
        'date_of_birth': _dateOfBirth == null
            ? null
            : '${_dateOfBirth!.year.toString().padLeft(4, '0')}-'
                  '${_dateOfBirth!.month.toString().padLeft(2, '0')}-'
                  '${_dateOfBirth!.day.toString().padLeft(2, '0')}',
        'country': _countryController.text,
        'year_of_study': _yearOfStudyController.text,
        'year_of_study_detail':
            _yearOfStudyController.text == ReferenceData.yearOfStudyOther
            ? _yearOfStudyDetailController.text.trim()
            : null,
        'employment_status': _employmentController.text,
        'relationship_status': _relationshipController.text,
        'has_children': _hasChildren,
        'living_situation': _livingSituationController.text,
      });
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Your profile has been updated successfully.'),
        ),
      );
      Navigator.of(context).pop(true);
    } on ApiException catch (e) {
      if (!mounted) return;
      setState(() => _fieldErrors = e.fieldErrors);
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text(e.message)));
    } finally {
      if (widget._injectedApiService == null) api.close();
      if (mounted) setState(() => _isSaving = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final errors = _fieldErrors;
    return Scaffold(
      appBar: AppBar(title: const Text('Edit Profile')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                Text(
                  'Update the profile details linked to your SheZen ID. Your sign-in email is kept separate.',
                  style: Theme.of(context).textTheme.bodyMedium,
                ),
                const SizedBox(height: 20),
                DateOfBirthField(
                  value: _dateOfBirth,
                  errorText: errors?['date_of_birth']?.first,
                  onChanged: (value) => setState(() => _dateOfBirth = value),
                ),
                Padding(
                  padding: const EdgeInsets.only(bottom: 20),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Age',
                        style: Theme.of(context).textTheme.labelMedium,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        widget.profile.age?.toString() ?? '—',
                        style: Theme.of(context).textTheme.bodyLarge,
                      ),
                      Text(
                        '(Read only — calculated automatically)',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
                CountryField(
                  controller: _countryController,
                  errorText: errors?['country']?.first,
                ),
                YearOfStudyField(
                  controller: _yearOfStudyController,
                  detailController: _yearOfStudyDetailController,
                  errorText: errors?['year_of_study']?.first,
                  detailErrorText: errors?['year_of_study_detail']?.first,
                ),
                ChoiceField(
                  controller: _employmentController,
                  label: 'Working',
                  options: const [
                    'Not employed',
                    'Part-time',
                    'Full-time',
                    'Prefer not to say',
                  ],
                  errorText: errors?['employment_status']?.first,
                ),
                ChoiceField(
                  controller: _relationshipController,
                  label: 'Relationship status',
                  options: const [
                    'Single',
                    'Partnered',
                    'Married',
                    'Prefer not to say',
                  ],
                  errorText: errors?['relationship_status']?.first,
                ),
                BooleanChoiceField(
                  label: 'Do you have children?',
                  value: _hasChildren,
                  errorText: errors?['has_children']?.first,
                  onChanged: (value) => setState(() => _hasChildren = value),
                ),
                ChoiceField(
                  controller: _livingSituationController,
                  label: 'Living situation',
                  options: const [
                    'With family',
                    'Campus housing',
                    'Shared housing',
                    'Living alone',
                    'Other',
                  ],
                  errorText: errors?['living_situation']?.first,
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    Expanded(
                      child: OutlinedButton(
                        onPressed: _isSaving
                            ? null
                            : () => Navigator.of(context).pop(false),
                        child: const Text('Cancel'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: FilledButton(
                        onPressed: _isSaving ? null : _save,
                        child: _isSaving
                            ? const SizedBox.square(
                                dimension: 20,
                                child: CircularProgressIndicator(
                                  strokeWidth: 2.5,
                                ),
                              )
                            : const Text('Save Changes'),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}
