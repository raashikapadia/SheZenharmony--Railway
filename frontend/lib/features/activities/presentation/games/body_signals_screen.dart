import 'package:flutter/material.dart';

import '../../../../core/theme/app_theme.dart';
import 'learning_game_chrome.dart';

/// Teaches where stress shows up physically, by asking the player to place a
/// described sensation on a simple body map.
///
/// Students routinely read these signals as something else — a stomach bug
/// before an exam, a headache blamed on screens — and miss that their body
/// flagged the stress before they noticed it. Naming the signal early is what
/// makes the calming activities elsewhere in this section usable in time.
///
/// The figure is assembled from plain rounded shapes rather than painted or
/// drawn from an asset: every zone stays a real tappable widget with its own
/// semantics label, so the game works with a screen reader.
class BodySignalsScreen extends StatefulWidget {
  const BodySignalsScreen({super.key});

  @override
  State<BodySignalsScreen> createState() => _BodySignalsScreenState();
}

enum _Zone { head, jaw, shoulders, chest, stomach, hands, legs }

class _BodySignalsScreenState extends State<BodySignalsScreen> {
  static const _zoneLabels = <_Zone, String>{
    _Zone.head: 'Head',
    _Zone.jaw: 'Jaw',
    _Zone.shoulders: 'Shoulders',
    _Zone.chest: 'Chest',
    _Zone.stomach: 'Stomach',
    _Zone.hands: 'Hands',
    _Zone.legs: 'Legs',
  };

  static const _signals = <_Signal>[
    _Signal(
      sensation:
          'Your thoughts race and you cannot hold on to any one of them.',
      zone: _Zone.head,
      why:
          'Stress hormones speed up thinking so you can react to danger '
          'quickly. With an essay rather than a threat in front of you, that '
          'speed reads as a mind that will not settle.',
    ),
    _Signal(
      sensation: 'You wake with an aching face and have been grinding away.',
      zone: _Zone.jaw,
      why:
          'The jaw is one of the first places the body holds tension, and it '
          'keeps holding it overnight. Morning jaw ache is often the clearest '
          'sign of a stretch of stress you slept straight through.',
    ),
    _Signal(
      sensation: 'They creep up towards your ears and stay tight all day.',
      zone: _Zone.shoulders,
      why:
          'Bracing your shoulders is a protective reflex meant to last '
          'seconds. Held for hours it turns into the stiffness and headaches '
          'people usually blame on posture.',
    ),
    _Signal(
      sensation: 'Your heart thumps and your breathing turns shallow.',
      zone: _Zone.chest,
      why:
          'Your body is moving oxygen for an emergency that is not coming. '
          'This is the signal that responds fastest to a slow out-breath, '
          'which is why breathing exercises start here.',
    ),
    _Signal(
      sensation: 'You feel queasy and cannot face breakfast before an exam.',
      zone: _Zone.stomach,
      why:
          'Digestion is paused when the body prepares for action, so nerves '
          'genuinely do turn your stomach. It is a stress signal, not a bug, '
          'and it passes once the pressure does.',
    ),
    _Signal(
      sensation: 'They shake or go clammy the moment you are put on the spot.',
      zone: _Zone.hands,
      why:
          'Blood moves to the large muscles and the sweat response switches '
          'on. Shaky hands are a sign your body is ready to act, which is '
          'worth knowing when they show up mid-presentation.',
    ),
    _Signal(
      sensation: 'Restless and jumpy, they will not keep still under the desk.',
      zone: _Zone.legs,
      why:
          'The urge to move is the body finishing what the stress response '
          'started. Letting it out with a short walk does more than sitting '
          'on it does.',
    ),
  ];

  int _index = 0;
  int _correct = 0;
  _Zone? _chosen;

  _Signal get _current => _signals[_index];
  bool get _hasAnswered => _chosen != null;
  bool get _isLast => _index == _signals.length - 1;

  void _choose(_Zone zone) {
    if (_hasAnswered) return;
    setState(() {
      _chosen = zone;
      if (zone == _current.zone) _correct++;
    });
  }

  void _next() {
    if (!_isLast) {
      setState(() {
        _index++;
        _chosen = null;
      });
      return;
    }

    showLearningSummary(
      context,
      correct: _correct,
      total: _signals.length,
      takeaway:
          'Your body usually notices stress before you do. Catching a signal '
          'early is what gives you the choice to do something about it.',
      onPlayAgain: _restart,
    );
  }

  void _restart() {
    setState(() {
      _index = 0;
      _correct = 0;
      _chosen = null;
    });
  }

  /// How a zone should render given where the round has got to.
  _ZoneState _stateOf(_Zone zone) {
    if (!_hasAnswered) return _ZoneState.open;
    if (zone == _current.zone) return _ZoneState.answer;
    if (zone == _chosen) return _ZoneState.chosenInstead;
    return _ZoneState.open;
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Body Signals')),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(AppSpacing.xl),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              LearningProgressBar(
                index: _index,
                total: _signals.length,
                correct: _correct,
                tint: AppColors.softBlush,
              ),

              const SizedBox(height: AppSpacing.xl),

              const Text(
                'WHERE DOES THIS SHOW UP?',
                style: TextStyle(
                  fontWeight: FontWeight.w800,
                  color: AppColors.primary,
                  letterSpacing: 1.2,
                  fontSize: 12,
                ),
              ),

              const SizedBox(height: AppSpacing.sm),

              Text(
                _current.sensation,
                style: const TextStyle(
                  fontSize: 19,
                  fontWeight: FontWeight.w700,
                  color: AppColors.ink,
                  height: 1.35,
                ),
              ),

              const SizedBox(height: AppSpacing.xl),

              _BodyMap(labels: _zoneLabels, stateOf: _stateOf, onTap: _choose),

              if (_hasAnswered) ...[
                const SizedBox(height: AppSpacing.xl),
                LearningExplanation(
                  wasCorrect: _chosen == _current.zone,
                  heading: '${_zoneLabels[_current.zone]}.',
                  body: _current.why,
                  onNext: _next,
                  isLast: _isLast,
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

enum _ZoneState { open, answer, chosenInstead }

/// A stylised figure with one tappable pill per body zone.
///
/// The figure is laid out on a fixed 260x340 canvas and scaled to whatever
/// width it is given, so the zones keep their positions on any screen size
/// without needing a media query.
class _BodyMap extends StatelessWidget {
  const _BodyMap({
    required this.labels,
    required this.stateOf,
    required this.onTap,
  });

  static const _canvas = Size(260, 340);

  /// Where each zone sits on the canvas, and how big its target is.
  static const _placement = <_Zone, Rect>{
    _Zone.head: Rect.fromLTWH(96, 0, 68, 46),
    _Zone.jaw: Rect.fromLTWH(96, 50, 68, 34),
    _Zone.shoulders: Rect.fromLTWH(46, 88, 168, 38),
    _Zone.chest: Rect.fromLTWH(76, 130, 108, 46),
    _Zone.stomach: Rect.fromLTWH(76, 180, 108, 46),
    _Zone.hands: Rect.fromLTWH(0, 180, 68, 38),
    _Zone.legs: Rect.fromLTWH(76, 240, 108, 90),
  };

  final Map<_Zone, String> labels;
  final _ZoneState Function(_Zone zone) stateOf;
  final void Function(_Zone zone) onTap;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final scale = constraints.maxWidth / _canvas.width;

        return SizedBox(
          width: constraints.maxWidth,
          height: _canvas.height * scale,
          child: Stack(
            children: [
              for (final entry in _placement.entries)
                Positioned(
                  left: entry.value.left * scale,
                  top: entry.value.top * scale,
                  width: entry.value.width * scale,
                  height: entry.value.height * scale,
                  child: _ZoneTarget(
                    label: labels[entry.key]!,
                    state: stateOf(entry.key),
                    onTap: () => onTap(entry.key),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }
}

class _ZoneTarget extends StatelessWidget {
  const _ZoneTarget({
    required this.label,
    required this.state,
    required this.onTap,
  });

  final String label;
  final _ZoneState state;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final (background, border, text) = switch (state) {
      _ZoneState.open => (
        AppColors.softLavender,
        AppColors.outline,
        AppColors.primary,
      ),
      _ZoneState.answer => (
        AppColors.softSage,
        AppColors.primary,
        AppColors.primary,
      ),
      _ZoneState.chosenInstead => (
        AppColors.softCoral,
        AppColors.secondary,
        AppColors.secondary,
      ),
    };

    return Semantics(
      button: true,
      label: label,
      child: Material(
        color: background,
        borderRadius: BorderRadius.circular(AppRadii.input),
        child: InkWell(
          onTap: onTap,
          borderRadius: BorderRadius.circular(AppRadii.input),
          child: Container(
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(AppRadii.input),
              border: Border.all(color: border, width: 1.5),
            ),
            alignment: Alignment.center,
            child: FittedBox(
              fit: BoxFit.scaleDown,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: AppSpacing.sm),
                child: Text(
                  label,
                  style: TextStyle(
                    fontWeight: FontWeight.w700,
                    color: text,
                    fontSize: 13,
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }
}

class _Signal {
  const _Signal({
    required this.sensation,
    required this.zone,
    required this.why,
  });

  final String sensation;
  final _Zone zone;
  final String why;
}
