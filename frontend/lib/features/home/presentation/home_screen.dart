import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../../shared/widgets/app_ui.dart';
import '../../assessment/data/assessment_result.dart';
import '../../assessment/presentation/questionnaire_screen.dart';
import '../../auth/application/auth_provider.dart';

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
      const _DashboardPage(),
      const _WellbeingPage(),
      const _ProgressPage(),
      const _ProfilePage(),
    ];
    final titles = ['Home', 'Wellbeing', 'Progress', 'Profile'];

    return Scaffold(
      appBar: AppBar(
        title: Text(
          titles[_selectedIndex],
          style: const TextStyle(fontWeight: FontWeight.w700),
        ),
        actions: _selectedIndex == 0
            ? [
                IconButton(
                  tooltip: 'Notifications',
                  onPressed: () => _showComingSoon(context, 'Notifications'),
                  icon: const Icon(Icons.notifications_none_rounded),
                ),
              ]
            : null,
      ),
      body: SafeArea(
        child: IndexedStack(index: _selectedIndex, children: pages),
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _selectedIndex,
        onDestinationSelected: (index) =>
            setState(() => _selectedIndex = index),
        destinations: const [
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home_rounded),
            label: 'Home',
          ),
          NavigationDestination(
            icon: Icon(Icons.spa_outlined),
            selectedIcon: Icon(Icons.spa_rounded),
            label: 'Wellbeing',
          ),
          NavigationDestination(
            icon: Icon(Icons.insights_outlined),
            selectedIcon: Icon(Icons.insights_rounded),
            label: 'Progress',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline),
            selectedIcon: Icon(Icons.person_rounded),
            label: 'Profile',
          ),
        ],
      ),
    );
  }
}

class _DashboardPage extends StatelessWidget {
  const _DashboardPage();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
      children: [
        Text(
          'Welcome back',
          style: Theme.of(
            context,
          ).textTheme.headlineMedium?.copyWith(fontWeight: FontWeight.w800),
        ),
        const SizedBox(height: 6),
        Text(
          'How are you feeling today?',
          style: Theme.of(
            context,
          ).textTheme.bodyLarge?.copyWith(color: colors.onSurfaceVariant),
        ),
        const SizedBox(height: 24),
        Card(
          color: colors.primaryContainer,
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Icon(Icons.favorite_outline_rounded, color: colors.primary),
                    const SizedBox(width: 10),
                    Text(
                      'Your wellbeing check-in',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 14),
                Text(
                  'Your baseline check-in is complete.',
                  style: Theme.of(context).textTheme.bodyLarge,
                ),
                const SizedBox(height: 6),
                Text(
                  'Check in again whenever you want to reflect on how you\'re doing.',
                  style: TextStyle(color: colors.onPrimaryContainer),
                ),
                const SizedBox(height: 18),
                FilledButton.tonalIcon(
                  onPressed: () => Navigator.of(context).push(
                    MaterialPageRoute(
                      builder: (_) =>
                          const QuestionnaireScreen(mandatory: false),
                    ),
                  ),
                  icon: const Icon(Icons.monitor_heart_outlined),
                  label: const Text('Start a check-in'),
                ),
              ],
            ),
          ),
        ),
        const SizedBox(height: 26),
        const AppSectionHeader(
          title: 'Explore support',
          subtitle: 'Choose what would help you right now.',
        ),
        const SizedBox(height: 12),
        LayoutBuilder(
          builder: (context, constraints) {
            final width = (constraints.maxWidth - 12) / 2;
            return Wrap(
              spacing: 12,
              runSpacing: 12,
              children: [
                SizedBox(
                  width: width,
                  child: AppFeatureCard(
                    icon: Icons.mood_outlined,
                    title: 'Mood tracking',
                    description: 'Notice patterns in how you feel.',
                    badge: 'Soon',
                    onTap: () => _showComingSoon(context, 'Mood tracking'),
                  ),
                ),
                SizedBox(
                  width: width,
                  child: AppFeatureCard(
                    icon: Icons.chat_bubble_outline_rounded,
                    title: 'Chat Buddy',
                    description: 'A gentle space to talk things through.',
                    tint: AppColors.softPlum,
                    badge: 'Soon',
                    onTap: () => _showComingSoon(context, 'Chat Buddy'),
                  ),
                ),
                SizedBox(
                  width: width,
                  child: AppFeatureCard(
                    icon: Icons.self_improvement_rounded,
                    title: 'Positive activities',
                    description: 'Pause, breathe, and reset.',
                    onTap: () =>
                        _showComingSoon(context, 'Positive activities'),
                  ),
                ),
                SizedBox(
                  width: width,
                  child: AppFeatureCard(
                    icon: Icons.school_outlined,
                    title: 'Academic support',
                    description: 'Plan study and manage pressure.',
                    tint: AppColors.softGold,
                    badge: 'Soon',
                    onTap: () => _showComingSoon(context, 'Academic support'),
                  ),
                ),
                SizedBox(
                  width: width,
                  child: AppFeatureCard(
                    icon: Icons.menu_book_outlined,
                    title: 'Resources',
                    description: 'Find practical wellbeing guidance.',
                    tint: AppColors.softPlum,
                    badge: 'Soon',
                    onTap: () => _showComingSoon(context, 'Resources'),
                  ),
                ),
                SizedBox(
                  width: width,
                  child: AppFeatureCard(
                    icon: Icons.air_rounded,
                    title: 'Breathing',
                    description: 'Take a short calming pause.',
                    badge: 'Soon',
                    onTap: () =>
                        _showComingSoon(context, 'Breathing activities'),
                  ),
                ),
              ],
            );
          },
        ),
        const SizedBox(height: 26),
        const _SectionHeading(title: 'Academic wellbeing'),
        const SizedBox(height: 12),
        _InfoCard(
          icon: Icons.event_note_outlined,
          title: 'Study reminders',
          description:
              'Planning and reminder tools are coming in a future update.',
          trailing: const _ComingSoonBadge(),
          onTap: () => _showComingSoon(context, 'Study reminders'),
        ),
      ],
    );
  }
}

class _WellbeingPage extends StatelessWidget {
  const _WellbeingPage();

  @override
  Widget build(BuildContext context) => ListView(
    padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
    children: [
      const _SectionHeading(
        title: 'Wellbeing tools',
        subtitle: 'Explore gentle ways to pause, reset, and reflect.',
      ),
      const SizedBox(height: 20),
      _InfoCard(
        icon: Icons.air_rounded,
        title: 'Breathing exercises',
        description: 'Guided breathing activities.',
        trailing: const _ComingSoonBadge(),
        onTap: () => _showComingSoon(context, 'Breathing exercises'),
      ),
      const SizedBox(height: 12),
      _InfoCard(
        icon: Icons.self_improvement_rounded,
        title: 'Grounding activities',
        description: 'Simple prompts to help you reconnect with the present.',
        trailing: const _ComingSoonBadge(),
        onTap: () => _showComingSoon(context, 'Grounding activities'),
      ),
      const SizedBox(height: 12),
      _InfoCard(
        icon: Icons.auto_stories_outlined,
        title: 'Reflection journal',
        description: 'A private space for guided reflection.',
        trailing: const _ComingSoonBadge(),
        onTap: () => _showComingSoon(context, 'Reflection journal'),
      ),
    ],
  );
}

class _ProgressPage extends StatefulWidget {
  const _ProgressPage();

  @override
  State<_ProgressPage> createState() => _ProgressPageState();
}

class _ProgressPageState extends State<_ProgressPage> {
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
          title: 'Couldn\'t load your progress',
          message: 'Check your connection and try again.',
          actionLabel: 'Try again',
          onAction: () => setState(_load),
        );
      }
      final history = snapshot.data ?? const [];
      if (history.isEmpty) {
        return const AppStateView(
          icon: Icons.insights_outlined,
          title: 'No check-ins yet',
          message: 'Your completed wellbeing check-ins will appear here.',
        );
      }
      return ListView.separated(
        padding: const EdgeInsets.fromLTRB(20, 8, 20, 28),
        itemCount: history.length + 1,
        separatorBuilder: (_, index) => SizedBox(height: index == 0 ? 18 : 12),
        itemBuilder: (context, index) {
          if (index == 0) {
            return const _SectionHeading(
              title: 'Your check-in history',
              subtitle:
                  'Results shown here come directly from your completed assessments.',
            );
          }
          final item = history[index - 1];
          final date = item.completedAt?.toLocal();
          final dateText = date == null
              ? 'Completed'
              : '${date.day}/${date.month}/${date.year}';
          return _InfoCard(
            icon: Icons.monitor_heart_outlined,
            title: item.bandLabel?.isNotEmpty == true
                ? item.bandLabel!
                : 'Completed check-in',
            description:
                '${item.questionnaireTitle ?? 'Stress check-in'} • $dateText',
            trailing: Text(
              '${item.totalScore}',
              style: Theme.of(
                context,
              ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
            ),
          );
        },
      );
    },
  );
}

class _ProfilePage extends StatelessWidget {
  const _ProfilePage();

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;
    final shezenId = context.watch<AuthProvider>().session?.shezenId;
    return ListView(
      padding: const EdgeInsets.fromLTRB(20, 16, 20, 28),
      children: [
        Center(
          child: CircleAvatar(
            radius: 42,
            backgroundColor: colors.primaryContainer,
            child: Text(
              'S',
              style: Theme.of(context).textTheme.headlineMedium?.copyWith(
                color: colors.primary,
                fontWeight: FontWeight.w800,
              ),
            ),
          ),
        ),
        const SizedBox(height: 14),
        Text(
          'Student account',
          textAlign: TextAlign.center,
          style: Theme.of(
            context,
          ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
        ),
        if (shezenId != null) ...[
          const SizedBox(height: 8),
          SelectableText(
            shezenId,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: colors.primary,
              fontFamily: 'monospace',
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
        const SizedBox(height: 28),
        _InfoCard(
          icon: Icons.shield_outlined,
          title: 'Privacy',
          description: 'Your wellbeing information is kept with your account.',
          onTap: () => _showComingSoon(context, 'Privacy information'),
        ),
        const SizedBox(height: 12),
        _InfoCard(
          icon: Icons.help_outline_rounded,
          title: 'Help and support',
          description: 'Support information will be available here.',
          trailing: const _ComingSoonBadge(),
          onTap: () => _showComingSoon(context, 'Help and support'),
        ),
        const SizedBox(height: 24),
        OutlinedButton.icon(
          onPressed: () => context.read<AuthProvider>().logout(),
          icon: const Icon(Icons.logout_rounded),
          label: const Text('Sign out'),
        ),
      ],
    );
  }
}

class _SectionHeading extends StatelessWidget {
  const _SectionHeading({required this.title, this.subtitle});
  final String title;
  final String? subtitle;

  @override
  Widget build(BuildContext context) => Column(
    crossAxisAlignment: CrossAxisAlignment.start,
    children: [
      Text(
        title,
        style: Theme.of(
          context,
        ).textTheme.titleLarge?.copyWith(fontWeight: FontWeight.w800),
      ),
      if (subtitle != null) ...[
        const SizedBox(height: 4),
        Text(
          subtitle!,
          style: TextStyle(
            color: Theme.of(context).colorScheme.onSurfaceVariant,
          ),
        ),
      ],
    ],
  );
}

class _InfoCard extends StatelessWidget {
  const _InfoCard({
    required this.icon,
    required this.title,
    required this.description,
    this.trailing,
    this.onTap,
  });
  final IconData icon;
  final String title;
  final String description;
  final Widget? trailing;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => Card(
    child: InkWell(
      borderRadius: BorderRadius.circular(20),
      onTap: onTap,
      child: Padding(
        padding: const EdgeInsets.all(18),
        child: Row(
          children: [
            CircleAvatar(child: Icon(icon)),
            const SizedBox(width: 14),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: Theme.of(context).textTheme.titleMedium?.copyWith(
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    description,
                    style: TextStyle(
                      color: Theme.of(context).colorScheme.onSurfaceVariant,
                    ),
                  ),
                ],
              ),
            ),
            if (trailing != null) ...[const SizedBox(width: 10), trailing!],
          ],
        ),
      ),
    ),
  );
}

class _ComingSoonBadge extends StatelessWidget {
  const _ComingSoonBadge();
  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 5),
    decoration: BoxDecoration(
      color: Theme.of(context).colorScheme.secondaryContainer,
      borderRadius: BorderRadius.circular(20),
    ),
    child: const Text(
      'Soon',
      style: TextStyle(fontSize: 12, fontWeight: FontWeight.w700),
    ),
  );
}

void _showComingSoon(BuildContext context, String feature) {
  ScaffoldMessenger.of(context).showSnackBar(
    SnackBar(content: Text('$feature is coming in a future update.')),
  );
}
