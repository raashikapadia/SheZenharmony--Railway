import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../../activities/presentation/positive_engagement_screen.dart';
import '../../activities/presentation/resource_screen.dart';
import '../../activities/presentation/wellbeing_hub_screen.dart';
import '../../assessment/data/assessment_result.dart';
import '../../assessment/presentation/assessment_detail_screen.dart';
import '../../assessment/presentation/questionnaire_screen.dart';
import '../../auth/application/auth_provider.dart';
import '../../guidance/presentation/personal_guidance_card.dart';
import '../../guidance/presentation/personal_guidance_screen.dart';
import '../../profile/presentation/profile_view_screen.dart';
import '../../shezen/presentation/shezen_chat_button.dart';

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
      const ResourceScreen(embedded: true),
      const _ProfilePage(),
    ];

    const titles = ['Home', 'Stress level', 'Resource', 'Profile'];

    return Scaffold(
      appBar: AppBar(title: Text(titles[_selectedIndex])),
      body: SafeArea(
        child: IndexedStack(index: _selectedIndex, children: pages),
      ),
      // Shezen is a floating companion rather than a primary destination, so
      // it rides above the bar next to Profile instead of taking a fifth tab.
      floatingActionButton: const ShezenChatButton(),
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
            label: 'Stress level',
          ),
          NavigationDestination(
            icon: Icon(Icons.support_agent_outlined),
            selectedIcon: Icon(Icons.support_agent_rounded),
            label: 'Resource',
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

        _StressHero(
          onStart: () => Navigator.of(context).push(
            MaterialPageRoute(
              builder: (_) => const QuestionnaireScreen(mandatory: false),
            ),
          ),
        ),

        const SizedBox(height: AppSpacing.sm),

        // The Stress level tab owns the history list, so this is a shortcut to
        // it rather than a second copy of the same screen.
        TextButton.icon(
          onPressed: () => onNavigate(1),
          icon: const Icon(Icons.history_rounded),
          label: const Text('See your past check-ins'),
        ),

        const SizedBox(height: AppSpacing.xxl),

        const AppSectionHeader(
          title: 'What would help right now?',
          subtitle: 'Choose one simple next step.',
        ),

        const SizedBox(height: AppSpacing.md),

        // The three primary content areas, as three equal choices. Personal
        // guidance leads the row so it reads as first-class rather than as a
        // sub-item of the other two.
        _PathwayRow(
          cards: [
            AppPathwayCard(
              icon: Icons.eco_outlined,
              title: 'Personal guidance',
              description: 'Tips, advice and daily affirmations.',
              tint: AppColors.softBlush,
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => const PersonalGuidanceScreen(),
                ),
              ),
            ),

            AppPathwayCard(
              icon: Icons.spa_outlined,
              title: 'Wellbeing activities',
              description: 'Videos, journaling, and browsing by how you feel.',
              tint: AppColors.softSage,
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const WellbeingHubScreen()),
              ),
            ),

            AppPathwayCard(
              icon: Icons.auto_awesome_outlined,
              title: 'Positive engagement',
              description: 'Games, quizzes, and motivational prompts.',
              tint: AppColors.softLavender,
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => const PositiveEngagementScreen(),
                ),
              ),
            ),
          ],
        ),

        const SizedBox(height: AppSpacing.xxl),

        AppIdentityCard(shezenId: shezenId),

        const SizedBox(height: AppSpacing.lg),

        TextButton.icon(
          // Profile is the last tab (index 3) in the four-destination bar.
          onPressed: () => onNavigate(3),
          icon: const Icon(Icons.manage_accounts_outlined),
          label: const Text('Manage profile and account'),
        ),
      ],
    );
  }
}

// ============================================================
// PATHWAY ROW
// ============================================================

/// The three content-area cards: side by side where there is room for them,
/// stacked full width on a phone.
///
/// Three across only earns its place on a wide window — at phone width the
/// columns would be too narrow for the titles to survive, so the same cards
/// run down the page instead. [IntrinsicHeight] keeps them a matched set in
/// the row, since one description wraps to more lines than the others.
class _PathwayRow extends StatelessWidget {
  const _PathwayRow({required this.cards});

  final List<Widget> cards;

  /// Below this, three columns leave too little room per card.
  static const _threeAcrossFrom = 720.0;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    builder: (context, constraints) {
      if (constraints.maxWidth < _threeAcrossFrom) {
        return Column(
          children: [
            for (final (index, card) in cards.indexed) ...[
              if (index > 0) const SizedBox(height: AppSpacing.md),
              card,
            ],
          ],
        );
      }

      return IntrinsicHeight(
        child: Row(
          crossAxisAlignment: CrossAxisAlignment.stretch,
          children: [
            for (final (index, card) in cards.indexed) ...[
              if (index > 0) const SizedBox(width: AppSpacing.lg),
              Expanded(child: card),
            ],
          ],
        ),
      );
    },
  );
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
        colors: [AppColors.primary, AppColors.heroWashEnd],
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
            color: Colors.white.withValues(alpha: .24),
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
          style: TextStyle(color: AppColors.onHeroMuted, height: 1.45),
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

/// The student's past check-ins as a standalone pushed route.
///
/// The same list is the "Stress level" tab; this wrapper stays so anything
/// that wants to open the history over the top of another screen still can.
class StressHistoryScreen extends StatelessWidget {
  const StressHistoryScreen({super.key});

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Your check-ins')),
    body: const SafeArea(child: _StressPage()),
  );
}
