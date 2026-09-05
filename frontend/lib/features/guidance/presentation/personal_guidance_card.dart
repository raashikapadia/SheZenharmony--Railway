import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../auth/application/auth_provider.dart';
import '../data/personal_guidance.dart';
import 'saved_guidance_screen.dart';

/// The signature Home Page moment: a soft, encouraging card that greets the
/// student with an affirmation, a motivational quote, or a short note from the
/// SheZen team. Content is fully admin-managed and fetched from the backend.
///
/// It never blocks the rest of Home — every failure resolves to a gentle
/// in-card state.
class PersonalGuidanceCard extends StatefulWidget {
  const PersonalGuidanceCard({super.key, ApiService? apiService})
    : _injectedApiService = apiService;

  final ApiService? _injectedApiService;

  @override
  State<PersonalGuidanceCard> createState() => _PersonalGuidanceCardState();
}

class _PersonalGuidanceCardState extends State<PersonalGuidanceCard> {
  late final ApiService _api;

  PersonalGuidance? _guidance;
  bool _loading = true;
  bool _changing = false;
  bool _favouriteBusy = false;
  String? _error;
  int _revision = 0;

  @override
  void initState() {
    super.initState();
    _api = widget._injectedApiService ?? ApiService();
    _load();
  }

  @override
  void dispose() {
    if (widget._injectedApiService == null) _api.close();
    super.dispose();
  }

  String get _token => context.read<AuthProvider>().session!.token;

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _error = null;
    });
    try {
      final next = await _api.currentGuidance(_token);
      if (!mounted) return;
      setState(() {
        _guidance = next;
        _loading = false;
        _revision++;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _loading = false;
        _error = 'We couldn\'t load your Personal Guidance right now.';
      });
    }
  }

  Future<void> _another() async {
    if (_changing) return;
    setState(() => _changing = true);
    try {
      final next = await _api.anotherGuidance(_token, excludeId: _guidance?.id);
      if (!mounted) return;
      setState(() {
        if (next != null) _guidance = next;
        _changing = false;
        _revision++;
      });
    } catch (_) {
      if (!mounted) return;
      setState(() => _changing = false);
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Couldn\'t find another one just now.')),
      );
    }
  }

  Future<void> _toggleFavourite() async {
    final current = _guidance;
    if (current == null || _favouriteBusy) return;
    final next = !current.isFavourite;
    setState(() {
      _favouriteBusy = true;
      _guidance = current.copyWith(isFavourite: next);
    });
    try {
      await _api.setGuidanceFavourite(_token, current.id, favourite: next);
    } catch (_) {
      if (!mounted) return;
      setState(() => _guidance = current); // roll back
    } finally {
      if (mounted) setState(() => _favouriteBusy = false);
    }
  }

  void _openSaved() {
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => const SavedGuidanceScreen()),
    );
  }

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [
            AppColors.softLavender,
            AppColors.softBlush,
            AppColors.softGold,
          ],
        ),
        borderRadius: BorderRadius.circular(28),
        border: Border.all(color: Colors.white.withValues(alpha: 0.6)),
        boxShadow: const [
          BoxShadow(
            color: Color(0x1F76517B),
            blurRadius: 28,
            offset: Offset(0, 14),
          ),
        ],
      ),
      child: ClipRRect(
        borderRadius: BorderRadius.circular(28),
        child: Stack(
          children: [
            const Positioned(
              top: -6,
              right: 10,
              child: Opacity(
                opacity: 0.16,
                child: Text('🌷', style: TextStyle(fontSize: 72)),
              ),
            ),
            const Positioned(
              bottom: -10,
              left: -6,
              child: Opacity(
                opacity: 0.12,
                child: Text('✨', style: TextStyle(fontSize: 64)),
              ),
            ),
            Padding(
              padding: const EdgeInsets.fromLTRB(
                AppSpacing.xl,
                AppSpacing.lg,
                AppSpacing.xl,
                AppSpacing.lg,
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.stretch,
                children: [
                  _Header(label: _headerLabel, onViewSaved: _openSaved),
                  const SizedBox(height: AppSpacing.xs),
                  const Text(
                    'A little something to carry with you today.',
                    style: TextStyle(color: AppColors.muted, height: 1.3),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  AnimatedSwitcher(
                    duration: const Duration(milliseconds: 320),
                    switchInCurve: Curves.easeOut,
                    switchOutCurve: Curves.easeIn,
                    transitionBuilder: (child, animation) => FadeTransition(
                      opacity: animation,
                      child: ScaleTransition(
                        scale: Tween<double>(
                          begin: 0.96,
                          end: 1,
                        ).animate(animation),
                        child: child,
                      ),
                    ),
                    child: KeyedSubtree(
                      key: ValueKey<int>(_revision),
                      child: _buildContent(context),
                    ),
                  ),
                  const SizedBox(height: AppSpacing.lg),
                  _Actions(
                    showFavourite: _guidance != null,
                    isFavourite: _guidance?.isFavourite ?? false,
                    favouriteBusy: _favouriteBusy,
                    changing: _changing,
                    onFavourite: _toggleFavourite,
                    onAnother: _guidance == null && _error == null
                        ? null
                        : (_error != null ? _load : _another),
                    anotherLabel: _error != null ? 'Try again' : 'Another one',
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  String get _headerLabel {
    if (_loading || _error != null || _guidance == null) {
      return '🌷  PERSONAL GUIDANCE';
    }
    return switch (_guidance!.type) {
      GuidanceType.quote => '✨  TODAY\'S INSPIRATION',
      GuidanceType.affirmation => '💗  A LITTLE REMINDER',
      GuidanceType.guidance => '🌷  PERSONAL GUIDANCE',
    };
  }

  Widget _buildContent(BuildContext context) {
    if (_loading) {
      return const _MessageBlock(
        emoji: '✨',
        lines: ['Finding a little something', 'for you…'],
        showSpinner: true,
      );
    }
    if (_error != null) {
      return const _MessageBlock(
        emoji: 'Oops 🌷',
        lines: [
          'We couldn\'t load your Personal Guidance right now.',
          'Please try again in a moment.',
        ],
      );
    }
    final guidance = _guidance;
    if (guidance == null) {
      return const _MessageBlock(
        emoji: '🌷',
        lines: [
          'Your little space is quiet for now.',
          'Check back soon for something encouraging to carry with you. 🤍',
        ],
      );
    }
    return _GuidanceBody(guidance: guidance);
  }
}

class _Header extends StatelessWidget {
  const _Header({required this.label, required this.onViewSaved});

  final String label;
  final VoidCallback onViewSaved;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      Expanded(
        child: Text(
          label,
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
          style: const TextStyle(
            color: AppColors.primary,
            fontSize: 12,
            fontWeight: FontWeight.w900,
            letterSpacing: 1.2,
          ),
        ),
      ),
      InkWell(
        onTap: onViewSaved,
        borderRadius: BorderRadius.circular(AppRadii.pill),
        child: const Padding(
          padding: EdgeInsets.symmetric(horizontal: 8, vertical: 4),
          child: Row(
            children: [
              Icon(
                Icons.bookmark_border_rounded,
                size: 16,
                color: AppColors.primary,
              ),
              SizedBox(width: 4),
              Text(
                'Saved',
                style: TextStyle(
                  color: AppColors.primary,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ],
          ),
        ),
      ),
    ],
  );
}

class _GuidanceBody extends StatelessWidget {
  const _GuidanceBody({required this.guidance});

  final PersonalGuidance guidance;

  @override
  Widget build(BuildContext context) {
    final isQuote = guidance.type == GuidanceType.quote;
    final wrapped = guidance.type == GuidanceType.guidance
        ? guidance.content
        : '“${guidance.content}”';
    final attribution = switch (guidance.type) {
      GuidanceType.quote => guidance.author,
      GuidanceType.guidance => guidance.author ?? 'SheZen',
      GuidanceType.affirmation => null,
    };

    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: [
        Text(
          wrapped,
          textAlign: TextAlign.center,
          style: Theme.of(context).textTheme.titleLarge?.copyWith(
            height: 1.4,
            fontWeight: FontWeight.w700,
            fontStyle: isQuote ? FontStyle.italic : FontStyle.normal,
            color: AppColors.ink,
          ),
        ),
        if (attribution != null) ...[
          const SizedBox(height: AppSpacing.md),
          Text(
            '— $attribution',
            textAlign: TextAlign.center,
            style: const TextStyle(
              color: AppColors.primary,
              fontWeight: FontWeight.w700,
            ),
          ),
        ],
        const SizedBox(height: AppSpacing.md),
        const Text(
          '✨   ♡   ✨',
          textAlign: TextAlign.center,
          style: TextStyle(color: AppColors.secondary, letterSpacing: 2),
        ),
      ],
    );
  }
}

class _MessageBlock extends StatelessWidget {
  const _MessageBlock({
    required this.emoji,
    required this.lines,
    this.showSpinner = false,
  });

  final String emoji;
  final List<String> lines;
  final bool showSpinner;

  @override
  Widget build(BuildContext context) => Column(
    children: [
      Text(emoji, style: const TextStyle(fontSize: 26)),
      const SizedBox(height: AppSpacing.sm),
      for (final line in lines)
        Padding(
          padding: const EdgeInsets.only(bottom: 2),
          child: Text(
            line,
            textAlign: TextAlign.center,
            style: const TextStyle(color: AppColors.muted, height: 1.35),
          ),
        ),
      if (showSpinner) ...[
        const SizedBox(height: AppSpacing.md),
        const SizedBox(
          width: 18,
          height: 18,
          child: CircularProgressIndicator(strokeWidth: 2),
        ),
      ],
    ],
  );
}

class _Actions extends StatelessWidget {
  const _Actions({
    required this.showFavourite,
    required this.isFavourite,
    required this.favouriteBusy,
    required this.changing,
    required this.onFavourite,
    required this.onAnother,
    required this.anotherLabel,
  });

  final bool showFavourite;
  final bool isFavourite;
  final bool favouriteBusy;
  final bool changing;
  final VoidCallback onFavourite;
  final VoidCallback? onAnother;
  final String anotherLabel;

  @override
  Widget build(BuildContext context) => Row(
    children: [
      if (showFavourite)
        _HeartButton(
          isFavourite: isFavourite,
          onTap: favouriteBusy ? null : onFavourite,
        ),
      Expanded(
        child: Align(
          alignment: Alignment.centerRight,
          child: FittedBox(
            fit: BoxFit.scaleDown,
            child: FilledButton.tonalIcon(
              onPressed: changing ? null : onAnother,
              icon: changing
                  ? const SizedBox(
                      width: 16,
                      height: 16,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Icon(Icons.auto_awesome_rounded, size: 18),
              label: Text('$anotherLabel ✨'),
              style: FilledButton.styleFrom(
                backgroundColor: Colors.white,
                foregroundColor: AppColors.primary,
              ),
            ),
          ),
        ),
      ),
    ],
  );
}

class _HeartButton extends StatelessWidget {
  const _HeartButton({required this.isFavourite, required this.onTap});

  final bool isFavourite;
  final VoidCallback? onTap;

  @override
  Widget build(BuildContext context) => IconButton(
    onPressed: onTap,
    tooltip: isFavourite ? 'Remove from saved' : 'Save this',
    icon: AnimatedScale(
      scale: isFavourite ? 1.15 : 1,
      duration: const Duration(milliseconds: 180),
      curve: Curves.easeOutBack,
      child: Icon(
        isFavourite ? Icons.favorite_rounded : Icons.favorite_border_rounded,
        color: AppColors.secondary,
      ),
    ),
  );
}
