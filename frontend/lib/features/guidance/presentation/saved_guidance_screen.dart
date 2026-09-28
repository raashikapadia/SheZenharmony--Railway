import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../../auth/application/auth_provider.dart';
import '../data/personal_guidance.dart';

/// The guidance a student has kept. Read-only, personal to the signed-in
/// student — the backend scopes it to their identity.
class SavedGuidanceScreen extends StatefulWidget {
  const SavedGuidanceScreen({super.key, ApiService? apiService})
    : _injectedApiService = apiService;

  final ApiService? _injectedApiService;

  @override
  State<SavedGuidanceScreen> createState() => _SavedGuidanceScreenState();
}

class _SavedGuidanceScreenState extends State<SavedGuidanceScreen> {
  late final ApiService _api;
  late Future<List<PersonalGuidance>> _saved;

  @override
  void initState() {
    super.initState();
    _api = widget._injectedApiService ?? ApiService();
    _load();
  }

  void _load() {
    final token = context.read<AuthProvider>().session!.token;
    _saved = _api.favouriteGuidance(token);
  }

  @override
  void dispose() {
    if (widget._injectedApiService == null) _api.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Saved guidance')),
      body: SafeArea(
        child: FutureBuilder<List<PersonalGuidance>>(
          future: _saved,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const AppLoadingView(message: 'Loading your saved notes…');
            }
            if (snapshot.hasError) {
              return AppStateView(
                icon: Icons.favorite_border_rounded,
                title: 'Couldn\'t load saved guidance',
                message: 'Please try again in a moment.',
                actionLabel: 'Try again',
                onAction: () => setState(_load),
              );
            }
            final items = snapshot.data ?? const [];
            if (items.isEmpty) {
              return const AppStateView(
                icon: Icons.favorite_border_rounded,
                title: 'Nothing saved yet',
                message:
                    'Tap the heart on a Personal Guidance card to keep it here.',
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.fromLTRB(20, 16, 20, 32),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
              itemBuilder: (context, index) =>
                  _SavedCard(guidance: items[index]),
            );
          },
        ),
      ),
    );
  }
}

class _SavedCard extends StatelessWidget {
  const _SavedCard({required this.guidance});

  final PersonalGuidance guidance;

  @override
  Widget build(BuildContext context) {
    final attribution = switch (guidance.type) {
      GuidanceType.quote => guidance.author,
      GuidanceType.guidance => guidance.author ?? 'SheZen',
      GuidanceType.affirmation => null,
      GuidanceType.tip => null,
    };
    final isQuoteStyle =
        guidance.type == GuidanceType.affirmation ||
        guidance.type == GuidanceType.quote;
    return Card(
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.lg),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              isQuoteStyle ? '“${guidance.content}”' : guidance.content,
              style: Theme.of(
                context,
              ).textTheme.titleMedium?.copyWith(height: 1.35),
            ),
            if (attribution != null) ...[
              const SizedBox(height: AppSpacing.sm),
              Text(
                '— $attribution',
                style: const TextStyle(
                  color: AppColors.primary,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
            if (guidance.category != null && guidance.category!.isNotEmpty) ...[
              const SizedBox(height: AppSpacing.md),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 4,
                ),
                decoration: BoxDecoration(
                  color: AppColors.softLavender,
                  borderRadius: BorderRadius.circular(AppRadii.pill),
                ),
                child: Text(
                  guidance.category!,
                  style: const TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: AppColors.primary,
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}
