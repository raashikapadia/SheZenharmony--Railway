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
      // Home draws its own branded header, so the bar would only repeat it.
      // The other tabs keep theirs — they are destinations and need naming.
      appBar: _selectedIndex == 0
          ? null
          : AppBar(title: Text(titles[_selectedIndex])),
      // The bar floats clear of the bottom, so content scrolls beneath it.
      extendBody: true,
      body: SafeArea(
        bottom: false,
        child: IndexedStack(index: _selectedIndex, children: pages),
      ),
      // Shezen is a floating companion rather than a primary destination, so
      // it rides above the bar next to Profile instead of taking a fifth tab.
      floatingActionButton: const ShezenChatButton(),
      bottomNavigationBar: _FloatingNavBar(
        selectedIndex: _selectedIndex,
        onSelect: _selectTab,
      ),
    );
  }

  void _selectTab(int index) {
    setState(() => _selectedIndex = index);
  }
}

// ============================================================
// NAVIGATION
// ============================================================

/// The four destinations as a floating pill rather than a bar welded to the
/// bottom edge.
///
/// It keeps Material's [NavigationBar] underneath — the destinations, the
/// selected-state semantics and the 48px touch targets are all still its work,
/// which is what keeps this readable to a screen reader and comfortable to
/// tap. The pill is only the surface it sits on.
class _FloatingNavBar extends StatelessWidget {
  const _FloatingNavBar({required this.selectedIndex, required this.onSelect});

  final int selectedIndex;
  final ValueChanged<int> onSelect;

  @override
  Widget build(BuildContext context) => SafeArea(
    top: false,
    child: Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.lg,
        0,
        AppSpacing.lg,
        AppSpacing.md,
      ),
      child: DecoratedBox(
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(AppRadii.pill),
          boxShadow: AppShadows.lifted,
        ),
        child: ClipRRect(
          borderRadius: BorderRadius.circular(AppRadii.pill),
          child: ColoredBox(
            // Opaque, not frosted: the destinations have to stay legible over
            // whatever has scrolled underneath them.
            color: AppColors.surface,
            child: NavigationBar(
              selectedIndex: selectedIndex,
              onDestinationSelected: onSelect,
              labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
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
                  icon: Icon(Icons.menu_book_outlined),
                  selectedIcon: Icon(Icons.menu_book_rounded),
                  label: 'Resource',
                ),
                NavigationDestination(
                  icon: Icon(Icons.person_outline_rounded),
                  selectedIcon: Icon(Icons.person_rounded),
                  label: 'Profile',
                ),
              ],
            ),
          ),
        ),
      ),
    ),
  );
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
      // Deep bottom inset: the nav pill and the Shezen button both float over
      // the content, so the last card needs room to scroll clear of them.
      padding: const EdgeInsets.fromLTRB(20, 4, 20, 152),
      children: [
        _HomeHeader(onOpenProfile: () => onNavigate(3)),

        const SizedBox(height: AppSpacing.lg),

        const _WelcomeHero(),

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

        const SizedBox(height: AppSpacing.md),

        // The Stress level tab owns the history list, so this is a shortcut to
        // it rather than a second copy of the same screen. A quiet tonal pill:
        // clearly tappable, but never louder than the CTA in the card above.
        Align(
          alignment: Alignment.centerLeft,
          child: TextButton.icon(
            onPressed: () => onNavigate(1),
            style: TextButton.styleFrom(
              backgroundColor: AppColors.softLavender,
              shape: const StadiumBorder(
                side: BorderSide(color: AppColors.outline),
              ),
              padding: const EdgeInsets.symmetric(
                horizontal: AppSpacing.lg,
                vertical: AppSpacing.md,
              ),
            ),
            icon: const Icon(Icons.history_rounded, size: 18),
            label: const Text('See your past check-ins'),
          ),
        ),

        const SizedBox(height: 32),

        const AppSectionHeader(
          title: 'What would help right now?',
          subtitle: 'One small step is enough — pick whatever sounds kind.',
        ),

        const SizedBox(height: AppSpacing.md),

        // The three primary content areas, as three equal choices. Personal
        // guidance leads the row so it reads as first-class rather than as a
        // sub-item of the other two.
        // Each area keeps its own tint so the three read as a set of distinct
        // places rather than three copies of one card: blush for guidance,
        // lilac for activities, sage for engagement.
        _PathwayRow(
          cards: [
            AppPathwayCard(
              icon: Icons.eco_outlined,
              title: 'Personal guidance',
              description:
                  'Practical tips and guidance for everyday wellbeing.',
              mood: AppMoods.guidance,
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(
                  builder: (_) => const PersonalGuidanceScreen(),
                ),
              ),
            ),

            AppPathwayCard(
              icon: Icons.self_improvement_outlined,
              title: 'Wellbeing activities',
              description:
                  'Supportive activities and resources to help you feel better.',
              mood: AppMoods.activities,
              onTap: () => Navigator.of(context).push(
                MaterialPageRoute(builder: (_) => const WellbeingHubScreen()),
              ),
            ),

            AppPathwayCard(
              icon: Icons.videogame_asset_outlined,
              title: 'Positive engagement',
              description:
                  'Games and quizzes to lift your mood and keep you engaged.',
              mood: AppMoods.engagement,
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
// HOME HEADER AND HERO
// ============================================================

/// A soft radial glow used inside coloured cards, where the page background's
/// blooms cannot reach.
class _HeroGlow extends StatelessWidget {
  const _HeroGlow({required this.size, required this.color});

  final double size;
  final Color color;

  @override
  Widget build(BuildContext context) => IgnorePointer(
    child: Container(
      width: size,
      height: size,
      decoration: BoxDecoration(
        shape: BoxShape.circle,
        gradient: RadialGradient(colors: [color, color.withAlpha(0)]),
      ),
    ),
  );
}

/// Logo on the left, a single quiet action on the right, and the brand line
/// between them where there is room for it.
class _HomeHeader extends StatelessWidget {
  const _HomeHeader({required this.onOpenProfile});

  final VoidCallback onOpenProfile;

  /// Below this the logo and the action fill the row on their own.
  static const _taglineFrom = 420.0;

  @override
  Widget build(BuildContext context) => LayoutBuilder(
    // The row's own width, not the window's: this header also renders inside
    // a narrow column on a wide screen, where the window size would lie.
    builder: (context, constraints) => Row(
      children: [
        // Flexible, because the fallback lockup draws real text and a long
        // logo must shrink rather than push the action off the edge.
        const Flexible(child: SheZenLogo(height: 46)),

        const Spacer(),

        // The tagline is the first thing to go when the row gets tight: it is
        // atmosphere, and the logo and the action are not.
        if (constraints.maxWidth >= _taglineFrom)
          const Flexible(
            child: Padding(
              padding: EdgeInsets.only(right: AppSpacing.md),
              child: AppScriptAccent(
                'A kinder you, everyday',
                fontSize: 13.5,
                textAlign: TextAlign.right,
              ),
            ),
          ),

        Container(
          decoration: const BoxDecoration(
            color: AppColors.surface,
            shape: BoxShape.circle,
            boxShadow: AppShadows.soft,
          ),
          child: IconButton(
            onPressed: onOpenProfile,
            tooltip: 'Your profile',
            icon: const Icon(Icons.person_outline_rounded),
            color: AppColors.primary,
          ),
        ),
      ],
    ),
  );
}

/// The greeting block: eyebrow, the serif welcome, and one line of support,
/// with a sprig of line art holding the right-hand space.
class _WelcomeHero extends StatelessWidget {
  const _WelcomeHero();

  @override
  Widget build(BuildContext context) {
    final text = Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const AppEyebrow('GOOD TO SEE YOU', icon: Icons.auto_awesome),

        const SizedBox(height: AppSpacing.md),

        // Two tones on one line: the plain half in ink for legibility, the
        // emotional half in mauve italic so it carries the feeling.
        Text.rich(
          TextSpan(
            children: [
              const TextSpan(text: 'Welcome to your\n'),
              TextSpan(
                text: 'safe space',
                style: TextStyle(
                  color: AppColors.primary,
                  fontStyle: FontStyle.italic,
                ),
              ),
              TextSpan(
                text: '  ♡',
                style: TextStyle(color: AppColors.blush, fontSize: 20),
              ),
            ],
          ),
          style: Theme.of(context).textTheme.displaySmall,
        ),

        // The display face already carries generous line height, so the
        // supporting line sits closer than the token gap would put it.
        const SizedBox(height: AppSpacing.sm),

        const Text(
          'A quiet place to check in, reset, and support your wellbeing.',
          style: TextStyle(color: AppColors.muted, height: 1.5),
        ),
      ],
    );

    return LayoutBuilder(
      builder: (context, constraints) {
        // Below this the sprig would squeeze the heading into hard wraps, and
        // the words matter more than the decoration.
        if (constraints.maxWidth < 400) return text;

        return Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Expanded(child: text),
            const SizedBox(width: AppSpacing.lg),
            // A small posy rather than a single sprig: two blossoms in
            // different hues, the leaf line art behind them, and a sparkle.
            SizedBox(
              width: 116,
              height: 138,
              child: Stack(
                children: [
                  const Positioned.fill(
                    child: IgnorePointer(
                      child: CustomPaint(
                        painter: BotanicalSprigPainter(
                          color: AppColors.sage,
                          opacity: 0.75,
                        ),
                      ),
                    ),
                  ),
                  const Positioned(
                    left: 4,
                    top: 18,
                    width: 52,
                    height: 52,
                    child: IgnorePointer(
                      child: CustomPaint(
                        painter: BlossomPainter(color: AppColors.blushPink),
                      ),
                    ),
                  ),
                  const Positioned(
                    right: 2,
                    top: 62,
                    width: 38,
                    height: 38,
                    child: IgnorePointer(
                      child: CustomPaint(
                        painter: BlossomPainter(
                          color: AppColors.peach,
                          rotation: 0.6,
                        ),
                      ),
                    ),
                  ),
                  const Positioned(
                    left: 40,
                    top: 74,
                    width: 26,
                    height: 26,
                    child: IgnorePointer(
                      child: CustomPaint(
                        painter: BlossomPainter(
                          color: AppColors.orchid,
                          opacity: 0.85,
                          rotation: 1.1,
                        ),
                      ),
                    ),
                  ),
                  const Positioned(
                    right: 6,
                    top: 0,
                    child: AppSparkleBurst(color: AppColors.peach, size: 52),
                  ),
                ],
              ),
            ),
          ],
        );
      },
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
  Widget build(BuildContext context) => DecoratedBox(
    decoration: BoxDecoration(
      borderRadius: BorderRadius.circular(AppRadii.hero),
      boxShadow: const [
        BoxShadow(
          color: Color(0x3A5F4363),
          blurRadius: 30,
          offset: Offset(0, 14),
          spreadRadius: -8,
        ),
      ],
    ),
    child: ClipRRect(
      borderRadius: BorderRadius.circular(AppRadii.hero),
      child: DecoratedBox(
        decoration: const BoxDecoration(
          gradient: LinearGradient(
            begin: Alignment.topLeft,
            end: Alignment.bottomRight,
            colors: [AppColors.primary, AppColors.heroWashEnd],
          ),
        ),
        child: Stack(
          children: [
            // A night sky rather than a dark one. Everything here is
            // decoration behind the copy and is clipped by the card; none of
            // it passes 24%, so white body text keeps its full contrast
            // wherever the shapes happen to fall — and the heading, copy and
            // button stay the loudest things in the card.

            // Moonlight, warm rather than cold, glowing from the top corner.
            const Positioned(
              top: -70,
              right: -50,
              child: _HeroGlow(size: 210, color: Color(0x3DF6C58B)),
            ),
            const Positioned(
              bottom: -80,
              left: -40,
              child: _HeroGlow(size: 190, color: Color(0x2EE8A1B5)),
            ),

            Positioned(
              right: -18,
              top: -10,
              bottom: -10,
              width: 168,
              child: IgnorePointer(
                child: CustomPaint(
                  painter: BotanicalSprigPainter(
                    color: Colors.white,
                    opacity: 0.12,
                  ),
                ),
              ),
            ),

            // Blossoms in the mood's lilac, so the card has flowers in it and
            // not just a moon.
            const Positioned(
              right: 96,
              bottom: 18,
              width: 46,
              height: 46,
              child: IgnorePointer(
                child: CustomPaint(
                  painter: BlossomPainter(
                    color: AppColors.lilac,
                    opacity: 0.22,
                  ),
                ),
              ),
            ),
            const Positioned(
              right: 22,
              bottom: 54,
              width: 28,
              height: 28,
              child: IgnorePointer(
                child: CustomPaint(
                  painter: BlossomPainter(
                    color: AppColors.blushPink,
                    opacity: 0.2,
                    rotation: 0.7,
                  ),
                ),
              ),
            ),

            const Positioned(
              right: 104,
              top: 22,
              child: Icon(
                Icons.nightlight_round,
                size: 19,
                color: Color(0x40FFF2D8),
              ),
            ),
            const Positioned(
              right: 56,
              top: 30,
              // Still shimmers, just further back.
              child: Opacity(
                opacity: 0.6,
                child: AppSparkleBurst(color: Color(0xFFFFF0D6), size: 62),
              ),
            ),

            Padding(
              padding: const EdgeInsets.all(AppSpacing.xxl),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 6,
                    ),
                    decoration: BoxDecoration(
                      color: Colors.white.withValues(alpha: .22),
                      borderRadius: BorderRadius.circular(AppRadii.pill),
                    ),
                    child: const Text(
                      'STRESS CHECK',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 11,
                        fontWeight: FontWeight.w800,
                        letterSpacing: 1.2,
                      ),
                    ),
                  ),

                  const SizedBox(height: AppSpacing.lg),

                  // Constrained so the heading wraps before it reaches the
                  // line art rather than running across it.
                  ConstrainedBox(
                    constraints: const BoxConstraints(maxWidth: 320),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          'How are you feeling today?',
                          style: Theme.of(context).textTheme.headlineSmall
                              ?.copyWith(color: Colors.white),
                        ),

                        const SizedBox(height: AppSpacing.sm),

                        const Text(
                          'Take a short check-in and receive a supportive, '
                          'non-diagnostic result.',
                          style: TextStyle(
                            color: AppColors.onHeroMuted,
                            height: 1.5,
                          ),
                        ),
                      ],
                    ),
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

                  const SizedBox(height: AppSpacing.md),

                  // Says what the check-in is for, in the card's own voice:
                  // an invitation to notice how you are, not a warning.
                  const AppScriptAccent(
                    "Let's check in with yourself  ♡",
                    color: Color(0xE6FFF2E4),
                    fontSize: 13.5,
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
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
