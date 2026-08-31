import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../../auth/application/auth_provider.dart';
import '../data/student_profile.dart';
import 'edit_profile_screen.dart';

class ProfileViewScreen extends StatefulWidget {
  const ProfileViewScreen({super.key, ApiService? apiService})
    : _injectedApiService = apiService;

  final ApiService? _injectedApiService;

  @override
  State<ProfileViewScreen> createState() => _ProfileViewScreenState();
}

class _ProfileViewScreenState extends State<ProfileViewScreen> {
  late final ApiService _api;
  late Future<StudentProfile> _profile;

  @override
  void initState() {
    super.initState();
    _api = widget._injectedApiService ?? ApiService();
    _load();
  }

  void _load() {
    final token = context.read<AuthProvider>().session!.token;
    _profile = _api.getProfile(token).then(StudentProfile.fromJson);
  }

  @override
  void dispose() {
    if (widget._injectedApiService == null) _api.close();
    super.dispose();
  }

  Future<void> _openEdit(StudentProfile profile) async {
    final updated = await Navigator.of(context).push<bool>(
      MaterialPageRoute(
        builder: (_) => EditProfileScreen(profile: profile, apiService: _api),
      ),
    );
    if (updated == true) {
      setState(_load);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('My Profile')),
    body: FutureBuilder<StudentProfile>(
      future: _profile,
      builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(child: CircularProgressIndicator());
        }
        if (snapshot.hasError) {
          return AppStateView(
            icon: Icons.cloud_off_outlined,
            title: 'Couldn\'t load your profile',
            message: 'Check your connection and try again.',
            actionLabel: 'Try again',
            onAction: () => setState(_load),
          );
        }
        final profile = snapshot.data!;
        return ListView(
          padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
          children: [
            _ProfileField(label: 'Email', value: profile.email),
            _ProfileField(
              label: 'Date of Birth',
              value: _formatDate(profile.dateOfBirth),
            ),
            _ProfileField(
              label: 'Age',
              value: profile.age?.toString() ?? '—',
            ),
            _ProfileField(label: 'Country', value: profile.country ?? '—'),
            _ProfileField(
              label: 'Year of Study',
              value: profile.yearOfStudy ?? '—',
            ),
            _ProfileField(
              label: 'Working',
              value: profile.employmentStatus ?? '—',
            ),
            _ProfileField(
              label: 'Relationship Status',
              value: profile.relationshipStatus ?? '—',
            ),
            _ProfileField(
              label: 'Children',
              value: profile.hasChildren == null
                  ? '—'
                  : (profile.hasChildren! ? 'Yes' : 'No'),
            ),
            _ProfileField(
              label: 'Living Arrangement',
              value: profile.livingSituation ?? '—',
            ),
            const SizedBox(height: 12),
            FilledButton(
              onPressed: () => _openEdit(profile),
              child: const Text('Edit Profile'),
            ),
          ],
        );
      },
    ),
  );

  String _formatDate(String? isoDate) {
    if (isoDate == null) return '—';
    final parsed = DateTime.tryParse(isoDate);
    if (parsed == null) return isoDate;
    const months = [
      'January', 'February', 'March', 'April', 'May', 'June',
      'July', 'August', 'September', 'October', 'November', 'December',
    ];
    return '${parsed.day} ${months[parsed.month - 1]} ${parsed.year}';
  }
}

class _ProfileField extends StatelessWidget {
  const _ProfileField({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: 18),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: Theme.of(context).textTheme.labelMedium?.copyWith(
            color: AppColors.muted,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 2),
        Text(value, style: Theme.of(context).textTheme.bodyLarge),
      ],
    ),
  );
}
