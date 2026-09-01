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
    if (updated == true) setState(_load);
  }

  Future<void> _confirmDeletion() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (dialogContext) => AlertDialog(
        icon: Icon(
          Icons.warning_amber_rounded,
          color: Theme.of(dialogContext).colorScheme.error,
        ),
        title: const Text('Permanently delete account?'),
        content: const Text(
          'This permanently removes your SheZen account and associated profile, assessment, progress, and chat data. This action cannot be undone.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(false),
            child: const Text('Keep account'),
          ),
          FilledButton(
            style: FilledButton.styleFrom(
              backgroundColor: Theme.of(dialogContext).colorScheme.error,
            ),
            onPressed: () => Navigator.of(dialogContext).pop(true),
            child: const Text('Delete permanently'),
          ),
        ],
      ),
    );
    if (confirmed != true || !mounted) return;

    final auth = context.read<AuthProvider>();
    final deleted = await auth.deleteAccount();
    if (!mounted) return;
    if (deleted) {
      final navigator = Navigator.of(context);
      final messenger = ScaffoldMessenger.of(context);
      navigator.popUntil((route) => route.isFirst);
      messenger.showSnackBar(
        const SnackBar(content: Text('Your SheZen account has been deleted.')),
      );
      return;
    }
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(
          auth.error ?? 'Your account could not be deleted. Please try again.',
        ),
      ),
    );
  }

  Future<void> _signOut() async {
    await context.read<AuthProvider>().logout();
    if (!mounted) return;
    Navigator.of(context).popUntil((route) => route.isFirst);
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Profile & account')),
    body: SafeArea(
      child: FutureBuilder<StudentProfile>(
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
          final sessionId =
              context.read<AuthProvider>().session?.shezenId ?? '';
          final shezenId = profile.shezenId.isNotEmpty
              ? profile.shezenId
              : sessionId;
          final deleting = context.watch<AuthProvider>().isDeletingAccount;

          return ListView(
            padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
            children: [
              AppIdentityCard(shezenId: shezenId),
              const SizedBox(height: AppSpacing.xxl),
              const AppSectionHeader(
                title: 'About you',
                subtitle:
                    'Only the profile information you are allowed to manage is shown here.',
              ),
              const SizedBox(height: AppSpacing.md),
              Card(
                child: Padding(
                  padding: const EdgeInsets.all(AppSpacing.xl),
                  child: Column(
                    children: [
                      _ProfileRow(
                        label: 'Date of birth',
                        value: _formatDate(profile.dateOfBirth),
                      ),
                      _ProfileRow(
                        label: 'Age',
                        value: profile.age?.toString() ?? 'Not provided',
                      ),
                      _ProfileRow(
                        label: 'Country',
                        value: profile.country ?? 'Not provided',
                      ),
                      _ProfileRow(
                        label: 'Year of study',
                        value: profile.yearOfStudy ?? 'Not provided',
                      ),
                      _ProfileRow(
                        label: 'Employment',
                        value: profile.employmentStatus ?? 'Not provided',
                      ),
                      _ProfileRow(
                        label: 'Relationship status',
                        value: profile.relationshipStatus ?? 'Not provided',
                      ),
                      _ProfileRow(
                        label: 'Children',
                        value: profile.hasChildren == null
                            ? 'Not provided'
                            : (profile.hasChildren! ? 'Yes' : 'No'),
                      ),
                      _ProfileRow(
                        label: 'Living situation',
                        value: profile.livingSituation ?? 'Not provided',
                        showDivider: false,
                      ),
                    ],
                  ),
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              FilledButton.icon(
                onPressed: () => _openEdit(profile),
                icon: const Icon(Icons.edit_outlined),
                label: const Text('Edit profile'),
              ),
              const SizedBox(height: AppSpacing.xxxl),
              const AppSectionHeader(
                title: 'Account',
                subtitle:
                    'Your sign-in email is kept in the authentication layer and is not displayed here.',
              ),
              const SizedBox(height: AppSpacing.md),
              OutlinedButton.icon(
                onPressed: deleting ? null : _signOut,
                icon: const Icon(Icons.logout_rounded),
                label: const Text('Sign out'),
              ),
              const SizedBox(height: AppSpacing.md),
              TextButton.icon(
                style: TextButton.styleFrom(
                  foregroundColor: Theme.of(context).colorScheme.error,
                  minimumSize: const Size.fromHeight(52),
                ),
                onPressed: deleting ? null : _confirmDeletion,
                icon: deleting
                    ? const SizedBox.square(
                        dimension: 18,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.delete_outline_rounded),
                label: Text(deleting ? 'Deleting account…' : 'Delete account'),
              ),
            ],
          );
        },
      ),
    ),
  );
}

class _ProfileRow extends StatelessWidget {
  const _ProfileRow({
    required this.label,
    required this.value,
    this.showDivider = true,
  });

  final String label;
  final String value;
  final bool showDivider;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Expanded(
            child: Text(label, style: const TextStyle(color: AppColors.muted)),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Text(
              value,
              textAlign: TextAlign.end,
              style: const TextStyle(fontWeight: FontWeight.w600),
            ),
          ),
        ],
      ),
      if (showDivider)
        const Padding(
          padding: EdgeInsets.symmetric(vertical: AppSpacing.md),
          child: Divider(height: 1),
        ),
    ],
  );
}

String _formatDate(String? isoDate) {
  if (isoDate == null) return 'Not provided';
  final parsed = DateTime.tryParse(isoDate);
  if (parsed == null) return isoDate;
  const months = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
  ];
  return '${parsed.day} ${months[parsed.month - 1]} ${parsed.year}';
}
