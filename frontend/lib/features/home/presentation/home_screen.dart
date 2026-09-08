import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../../activities/presentation/positive_engagement_screen.dart';
import '../../activities/presentation/wellbeing_hub_screen.dart';
import '../../assessment/data/assessment_result.dart';
import '../../assessment/presentation/assessment_detail_screen.dart';
import '../../assessment/presentation/questionnaire_screen.dart';
import '../../auth/application/auth_provider.dart';
import '../../guidance/presentation/personal_guidance_card.dart';
import '../../guidance/presentation/personal_guidance_screen.dart';
import '../../profile/presentation/profile_view_screen.dart';
import '../../shezen/presentation/shezen_feature_card.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  int _selectedIndex = 0;

  @override
  Widget build(BuildContext context) {
    final pages = [
      _DashboardPage(onNavigate: _selectTab),
      const PersonalGuidanceScreen(),
      const WellbeingHubScreen(embedded: true),
      const PositiveEngagementScreen(embedded: true),
      const _ProfilePage(),
    ];

    const titles = [
      'Home',
      'Personal guidance',
      'Wellbeing activities',
      'Positive engagement',
      'Profile',
    ];

    return Scaffold(
      appBar: AppBar(title: Text(titles[_selectedIndex])),
      body: SafeArea(
        child: IndexedStack(index: _selectedIndex, children: pages),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
        onDestinationSelected: _selectTab,
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home_rounded),
            label: 'Home',
          ),
          NavigationDestination(
            icon: Icon(Icons.favorite_outline_rounded),
            selectedIcon: Icon(Icons.favorite_rounded),
            label: 'Guidance',
          ),
          NavigationDestination(
            icon: Icon(Icons.spa_outlined),
            selectedIcon: Icon(Icons.spa_rounded),
            label: 'Activities',
          ),
          NavigationDestination(
            icon: Icon(Icons.auto_awesome_outlined),
            selectedIcon: Icon(Icons.auto_awesome_rounded),
            label: 'Positive',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline_rounded),
            selectedIcon: Icon(Icons.person_rounded),
            label: 'Profile',
          ),
        ],
      ),
    );
  }

  void _selectTab(int index) {
    setState(() => _selectedIndex = index);
  }
}

// ============================================================
// DASHBOARD PAGE
// ============================================================

class _DashboardPage extends StatelessWidget {
  const _DashboardPage({required this.onNavigate});

  final ValueChanged<int> onNavigate;

  @override
  Widget build(BuildContext context) {
    final shezenId = context.watch<AuthProvider>().session?.shezenId ?? '';

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
      children: [
        Text(
          'Welcome to your space',
          style: Theme.of(context).textTheme.headlineMedium,
        ),

        const SizedBox(height: AppSpacing.sm),

        const Text(
          'A quiet place to check in, reset, and support your wellbeing.',
          style: TextStyle(color: AppColors.muted),
        ),

        const SizedBox(height: AppSpacing.xxl),

        const PersonalGuidanceCard(),

        const SizedBox(height: AppSpacing.xxl),

        const ShezenFeatureCard(),

        const SizedBox(height: AppSpacing.xxl),

        _StressHero(
          onStart: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => const QuestionnaireScreen(mandatory: false),
            ),
          ),
        ),

        const SizedBox(height: AppSpacing.sm),

        // Stress history lives here now that the bottom bar carries the three
        // wellbeing sections.
        TextButton.icon(
          onPressed: () => Navigator.of(
            context,
          ).push(MaterialPageRoute(builder: (_) => const StressHistoryScreen())),
          icon: const Icon(Icons.history_rounded),
          label: const Text('See your past check-ins'),
        ),

        const SizedBox(height: AppSpacing.xxl),

        const AppSectionHeader(
          title: 'What would help right now?',
          subtitle: 'Choose one simple next step.',
        ),

        const SizedBox(height: AppSpacing.md),

        LayoutBuilder(
          builder: (context, constraints) {
            final isNarrow = constraints.maxWidth < 350;

            final width = isNarrow
                ? constraints.maxWidth
                : (constraints.maxWidth - AppSpacing.md) / 2;

            return Wrap(
              spacing: AppSpacing.md,
              runSpacing: AppSpacing.md,
              children: [
                SizedBox(
                  width: width,
                  child: AppFeatureCard(
                    icon: Icons.spa_outlined,
                    title: 'Wellbeing activities',
                    description: 'Breathing, grounding, and mindful breaks.',
                    tint: AppColors.softSage,
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const WellbeingHubScreen(),
                      ),
                    ),
                  ),
                ),

                SizedBox(
                  width: width,
                  child: AppFeatureCard(
                    icon: Icons.auto_awesome_outlined,
                    title: 'Positive engagement',
                    description:
                        'Games, quizzes, motivational prompts, and light activities.',
                    tint: AppColors.softLavender,
                    onTap: () => Navigator.of(context).push(
                      MaterialPageRoute(
                        builder: (_) => const PositiveEngagementScreen(),
                      ),
                    ),
                  ),
                ),
              ],
            );
          },
        ),

        const SizedBox(height: AppSpacing.xxl),

        AppIdentityCard(shezenId: shezenId),

        const SizedBox(height: AppSpacing.lg),

        TextButton.icon(
          // Profile is the last tab (index 4) since Guidance joined the bar.
          onPressed: () => onNavigate(4),
          icon: const Icon(Icons.manage_accounts_outlined),
          label: const Text('Manage profile and account'),
        ),
      ],
    );
  }
}

// ============================================================
// STRESS HERO
// ============================================================

class _StressHero extends StatelessWidget {
  const _StressHero({required this.onStart});

  final VoidCallback onStart;

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.all(AppSpacing.xl),
    decoration: BoxDecoration(
      gradient: const LinearGradient(
        begin: Alignment.topLeft,
        end: Alignment.bottomRight,
        colors: [AppColors.primary, Color(0xFF9A718F)],
      ),
      borderRadius: BorderRadius.circular(28),
      boxShadow: const [
        BoxShadow(
          color: Color(0x2876517B),
          blurRadius: 24,
          offset: Offset(0, 12),
        ),
      ],
    ),
    child: Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 6),
          decoration: BoxDecoration(
            color: Colors.white.withValues(alpha: .16),
            borderRadius: BorderRadius.circular(AppRadii.pill),
          ),
          child: const Text(
            'STRESS CHECK',
            style: TextStyle(
              color: Colors.white,
              fontSize: 11,
              fontWeight: FontWeight.w800,
              letterSpacing: 1,
            ),
          ),
        ),

        const SizedBox(height: AppSpacing.lg),

        Text(
          'How are you feeling today?',
          style: Theme.of(
            context,
          ).textTheme.headlineSmall?.copyWith(color: Colors.white),
        ),

        const SizedBox(height: AppSpacing.sm),

        const Text(
          'Take a short check-in and receive a supportive, non-diagnostic result.',
          style: TextStyle(color: Color(0xFFF9EEF8), height: 1.45),
        ),

        const SizedBox(height: AppSpacing.xl),

        FilledButton.icon(
          style: FilledButton.styleFrom(
            backgroundColor: Colors.white,
            foregroundColor: AppColors.primary,
          ),
          onPressed: onStart,
          icon: const Icon(Icons.arrow_forward_rounded),
          label: const Text('Start stress check'),
        ),
      ],
    ),
  );
}

// ============================================================
// STRESS PAGE
// ============================================================

class _StressPage extends StatefulWidget {
  const _StressPage();

  @override
  State<_StressPage> createState() => _StressPageState();
}

class _StressPageState extends State<_StressPage> {
  late final ApiService _api;
  late Future<List<AssessmentSummary>> _history;

  @override
  void initState() {
    super.initState();

    _api = ApiService();

    _load();
  }

  void _load() {
    final token = context.read<AuthProvider>().session!.token;

    _history = _api.myAssessments(token);
  }

  Future<void> _startCheckIn() async {
    await Navigator.of(context).push(
      MaterialPageRoute(
        builder: (_) => const QuestionnaireScreen(mandatory: false),
      ),
    );

    if (mounted) {
      setState(_load);
    }
  }

  @override
  void dispose() {
    _api.close();

    super.dispose();
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<List<AssessmentSummary>>(
    future: _history,
    builder: (context, snapshot) {
      if (snapshot.connectionState == ConnectionState.waiting) {
        return const Center(child: CircularProgressIndicator());
      }

      if (snapshot.hasError) {
        return AppStateView(
          icon: Icons.cloud_off_outlined,
          title: 'Couldn\'t load your stress checks',
          message: 'Check your connection and try again.',
          actionLabel: 'Try again',
          onAction: () => setState(_load),
        );
      }

      final history = snapshot.data ?? const [];

      return RefreshIndicator(
        onRefresh: () async {
          setState(_load);

          await _history;
        },
        child: ListView(
          physics: const AlwaysScrollableScrollPhysics(),
          padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
          children: [
            const AppSectionHeader(
              title: 'Your stress check-ins',
              subtitle:
                  'Private results from questionnaires completed with SheZen.',
            ),

            const SizedBox(height: AppSpacing.lg),

            FilledButton.icon(
              onPressed: _startCheckIn,
              icon: const Icon(Icons.add_rounded),
              label: const Text('Start a new stress check'),
            ),

            const SizedBox(height: AppSpacing.xxl),

            if (history.isEmpty)
              const Card(
                child: Padding(
                  padding: EdgeInsets.all(AppSpacing.xl),
                  child: Text(
                    'No completed check-ins yet. Your results will appear here after submission.',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: AppColors.muted),
                  ),
                ),
              )
            else
              for (final item in history) ...[
                _AssessmentCard(item: item),
                const SizedBox(height: AppSpacing.md),
              ],
          ],
        ),
      );
    },
  );
}

// ============================================================
// ASSESSMENT CARD
// ============================================================

class _AssessmentCard extends StatelessWidget {
  const _AssessmentCard({required this.item});

  final AssessmentSummary item;

  @override
  Widget build(BuildContext context) {
    final date = item.completedAt?.toLocal();

    final dateText = date == null
        ? 'Completed'
        : '${date.day.toString().padLeft(2, '0')}/'
              '${date.month.toString().padLeft(2, '0')}/'
              '${date.year}';

    return Card(
      clipBehavior: Clip.antiAlias,
      child: InkWell(
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(
            builder: (_) => AssessmentDetailScreen(assessmentId: item.id),
          ),
        ),
        child: Padding(
          padding: const EdgeInsets.all(AppSpacing.lg),
          child: Row(
            children: [
              const CircleAvatar(
                backgroundColor: AppColors.softLavender,
                child: Icon(
                  Icons.monitor_heart_outlined,
                  color: AppColors.primary,
                ),
              ),

              const SizedBox(width: AppSpacing.md),

              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      item.bandLabel?.isNotEmpty == true
                          ? item.bandLabel!
                          : 'Completed stress check',
                      style: Theme.of(context).textTheme.titleMedium,
                    ),

                    Text(
                      dateText,
                      style: const TextStyle(color: AppColors.muted),
                    ),
                  ],
                ),
              ),

              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 12,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: AppColors.softSage,
                  borderRadius: BorderRadius.circular(AppRadii.pill),
                ),
                child: Text(
                  '${item.totalScore}',
                  style: const TextStyle(fontWeight: FontWeight.w800),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

// ============================================================
// LARGE NAVIGATION CARD
// ============================================================

class _LargeNavigationCard extends StatelessWidget {
  const _LargeNavigationCard({
    required this.icon,
    required this.title,
    required this.description,
    required this.color,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String description;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) => Card(
    color: color,
    child: InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(AppRadii.card),
      child: Padding(
        padding: const EdgeInsets.all(AppSpacing.xl),
        child: Row(
          children: [
            CircleAvatar(
              radius: 26,
              backgroundColor: AppColors.surface,
              child: Icon(icon, color: AppColors.primary),
            ),

            const SizedBox(width: AppSpacing.lg),

            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(title, style: Theme.of(context).textTheme.titleLarge),

                  const SizedBox(height: AppSpacing.xs),

                  Text(
                    description,
                    style: const TextStyle(color: AppColors.muted),
                  ),
                ],
              ),
            ),

            const Icon(Icons.chevron_right_rounded),
          ],
        ),
      ),
    ),
  );
}

// ============================================================
// PROFILE PAGE
// ============================================================

class _ProfilePage extends StatelessWidget {
  const _ProfilePage();

  @override
  Widget build(BuildContext context) {
    final auth = context.watch<AuthProvider>();

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
      children: [
        AppIdentityCard(shezenId: auth.session?.shezenId ?? ''),

        const SizedBox(height: AppSpacing.xxl),

        _LargeNavigationCard(
          icon: Icons.manage_accounts_outlined,
          title: 'Profile & account',
          description:
              'View or edit permitted details, sign out, or delete your account.',
          color: AppColors.softLavender,
          onTap: () => Navigator.of(
            context,
          ).push(MaterialPageRoute(builder: (_) => const ProfileViewScreen())),
        ),

        const SizedBox(height: AppSpacing.xxl),

        OutlinedButton.icon(
          onPressed: auth.isLoading ? null : auth.logout,
          icon: const Icon(Icons.logout_rounded),
          label: const Text('Sign out'),
        ),
      ],
    );
  }
}

// ============================================================
// STRESS HISTORY
// ============================================================

/// The student's past check-ins. This is the screen that used to be the
/// "Stress" tab — the list itself is unchanged, it is now reached from the
/// Home dashboard so the bottom bar can carry the three wellbeing sections.
class StressHistoryScreen extends StatelessWidget {
  const StressHistoryScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Your check-ins')),
    body: const SafeArea(child: _StressPage()),
  );
}
