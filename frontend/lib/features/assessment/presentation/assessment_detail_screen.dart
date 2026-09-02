import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../../auth/application/auth_provider.dart';
import '../data/assessment_detail.dart';
import 'widgets/recommended_support.dart';

/// Read-only view of one of the student's own completed assessments. The
/// backend returns 404 for any id that is not theirs, so this screen shows a
/// neutral "not found" state rather than confirming another student's data.
class AssessmentDetailScreen extends StatefulWidget {
  const AssessmentDetailScreen({
    super.key,
    required this.assessmentId,
    ApiService? apiService,
  }) : _injectedApiService = apiService;

  final int assessmentId;
  final ApiService? _injectedApiService;

  @override
  State<AssessmentDetailScreen> createState() => _AssessmentDetailScreenState();
}

class _AssessmentDetailScreenState extends State<AssessmentDetailScreen> {
  late final ApiService _api;
  late Future<AssessmentDetail> _detail;

  @override
  void initState() {
    super.initState();
    _api = widget._injectedApiService ?? ApiService();
    _load();
  }

  void _load() {
    final token = context.read<AuthProvider>().session!.token;
    _detail = _api.assessmentDetail(token, widget.assessmentId);
  }

  @override
  void dispose() {
    if (widget._injectedApiService == null) _api.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Assessment detail')),
      body: SafeArea(
        child: FutureBuilder<AssessmentDetail>(
          future: _detail,
          builder: (context, snapshot) {
            if (snapshot.connectionState == ConnectionState.waiting) {
              return const AppLoadingView(message: 'Loading your result…');
            }
            if (snapshot.hasError) {
              final message = snapshot.error is ApiException
                  ? (snapshot.error as ApiException).message
                  : 'We couldn\'t load this assessment.';
              return AppStateView(
                icon: Icons.help_outline_rounded,
                title: 'Result unavailable',
                message: message,
                actionLabel: 'Try again',
                onAction: () => setState(_load),
              );
            }
            final detail = snapshot.data!;
            return ListView(
              padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
              children: [
                _ScoreCard(detail: detail),
                const SizedBox(height: AppSpacing.xl),
                if (detail.recommendedInterventions.isNotEmpty) ...[
                  RecommendedSupportSection(
                    items: detail.recommendedInterventions,
                  ),
                  const SizedBox(height: AppSpacing.xl),
                ],
                if (detail.responses.isNotEmpty) ...[
                  Text(
                    'Your answers',
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w800,
                    ),
                  ),
                  const SizedBox(height: AppSpacing.sm),
                  for (final response in detail.responses)
                    Card(
                      child: ListTile(
                        title: Text(response.question),
                        subtitle: Text(response.answer),
                        trailing: Text(
                          '${response.score}',
                          style: const TextStyle(fontWeight: FontWeight.w700),
                        ),
                      ),
                    ),
                ],
                const SizedBox(height: AppSpacing.lg),
                const Text(
                  'This result is not a medical diagnosis. Your wellbeing can change over time.',
                  style: TextStyle(color: AppColors.muted),
                ),
              ],
            );
          },
        ),
      ),
    );
  }
}

class _ScoreCard extends StatelessWidget {
  const _ScoreCard({required this.detail});

  final AssessmentDetail detail;

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    return Card(
      color: colors.primaryContainer,
      child: Padding(
        padding: const EdgeInsets.all(24),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            Text(
              'Your Stress Score',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                color: colors.onPrimaryContainer,
              ),
            ),
            const SizedBox(height: 8),
            Text(
              '${detail.totalScore} / ${detail.scoreOutOf}',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.displaySmall?.copyWith(
                fontWeight: FontWeight.w800,
                color: colors.primary,
              ),
            ),
            if (detail.bandLabel != null && detail.bandLabel!.isNotEmpty) ...[
              const SizedBox(height: 14),
              Text(
                'Stress Level',
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  color: colors.onPrimaryContainer,
                ),
              ),
              const SizedBox(height: 4),
              Text(
                detail.bandLabel!,
                textAlign: TextAlign.center,
                style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                  color: colors.primary,
                ),
              ),
            ],
            const SizedBox(height: 14),
            Text(
              'Assessment Date',
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.titleSmall?.copyWith(
                color: colors.onPrimaryContainer,
              ),
            ),
            const SizedBox(height: 4),
            Text(
              formatLongDate(detail.completedAt),
              textAlign: TextAlign.center,
              style: const TextStyle(fontWeight: FontWeight.w700),
            ),
          ],
        ),
      ),
    );
  }
}
