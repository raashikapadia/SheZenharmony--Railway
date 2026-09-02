import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../../activities/presentation/positive_engagement_screen.dart';
import '../../activities/presentation/wellbeing_activities_screen.dart';
import '../../assessment/data/assessment_result.dart';
import '../../assessment/presentation/assessment_detail_screen.dart';
import '../../assessment/presentation/questionnaire_screen.dart';
import '../../auth/application/auth_provider.dart';
import '../../guidance/presentation/personal_guidance_card.dart';
import '../../profile/presentation/profile_view_screen.dart';

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
      const _StressPage(),
      const _ActivitiesPage(),
      const _ProfilePage(),
    ];
    const titles = ['Home', 'Stress', 'Activities', 'Profile'];

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
            icon: Icon(Icons.monitor_heart_outlined),
            selectedIcon: Icon(Icons.monitor_heart_rounded),
            label: 'Stress',
          ),
          NavigationDestination(
            icon: Icon(Icons.spa_outlined),
            selectedIcon: Icon(Icons.spa_rounded),
            label: 'Activities',
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

  void _selectTab(int index) => setState(() => _selectedIndex = index);
}

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
        _StressHero(
          onStart: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => const QuestionnaireScreen(mandatory: false),
            ),
          ),
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
                        builder: (_) => const WellbeingActivitiesScreen(),
                      ),
                    ),
                  ),
                ),
                SizedBox(
                  width: width,
                  child: AppFeatureCard(
                    icon: Icons.auto_awesome_outlined,
                    title: 'Positive engagement',
                    description: 'Affirmations and light positive activities.',
                    tint: AppColors.softBlush,
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
          onPressed: () => onNavigate(3),
          icon: const Icon(Icons.manage_accounts_outlined),
          label: const Text('Manage profile and account'),
        ),
      ],
    );
  }
}

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
    if (mounted) setState(_load);
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

class _AssessmentCard extends StatelessWidget {
  const _AssessmentCard({required this.item});
  final AssessmentSummary item;

  @override
  Widget build(BuildContext context) {
    final date = item.completedAt?.toLocal();
    final dateText = date == null
        ? 'Completed'
        : '${date.day.toString().padLeft(2, '0')}/${date.month.toString().padLeft(2, '0')}/${date.year}';
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

class _ActivitiesPage extends StatelessWidget {
  const _ActivitiesPage();

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.fromLTRB(20, 8, 20, 32),
    children: [
      const AppSectionHeader(
        title: 'Activities',
        subtitle: 'Explore content published by the SheZen wellbeing team.',
      ),
      const SizedBox(height: AppSpacing.xl),
      _LargeNavigationCard(
        icon: Icons.spa_outlined,
        title: 'Wellbeing activities',
        description:
            'Breathing, mindfulness, grounding, relaxation, and healthy breaks.',
        color: AppColors.softSage,
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const WellbeingActivitiesScreen()),
        ),
      ),
      const SizedBox(height: AppSpacing.md),
      _LargeNavigationCard(
        icon: Icons.auto_awesome_outlined,
        title: 'Positive engagement',
        description:
            'Friendly affirmations, quizzes, motivational prompts, and light activities.',
        color: AppColors.softBlush,
        onTap: () => Navigator.of(context).push(
          MaterialPageRoute(builder: (_) => const PositiveEngagementScreen()),
        ),
      ),
    ],
  );
}

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
