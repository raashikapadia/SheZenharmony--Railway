import 'package:flutter/material.dart';

import '../../../../core/theme/app_theme.dart';

class GratitudeJarScreen extends StatefulWidget {
  const GratitudeJarScreen({super.key});

  @override
  State<GratitudeJarScreen> createState() => _GratitudeJarScreenState();
}

class _GratitudeJarScreenState extends State<GratitudeJarScreen>
    with SingleTickerProviderStateMixin {
  final TextEditingController _controller = TextEditingController();

  final List<_GratitudeItem> _entries = [];

  late AnimationController _jarAnimationController;

  bool _showInput = false;
  bool _animateNewStone = false;
  String? _newStoneId;

  @override
  void initState() {
    super.initState();

    _jarAnimationController = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 900),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    _jarAnimationController.dispose();
    super.dispose();
  }

  void _addEntry() {
    final text = _controller.text.trim();

    if (text.isEmpty) {
      return;
    }

    final newEntry = _GratitudeItem(
      text: text,
      symbol: _getSymbol(_entries.length),
      id: DateTime.now().microsecondsSinceEpoch.toString(),
    );

    setState(() {
      _entries.insert(0, newEntry);
      _newStoneId = newEntry.id;
      _animateNewStone = true;

      _controller.clear();
      _showInput = false;
    });

    _jarAnimationController.forward(from: 0);

    Future.delayed(const Duration(milliseconds: 1100), () {
      if (!mounted) return;

      setState(() {
        _animateNewStone = false;
      });
    });

    if (_containsEmotionalWords(text)) {
      Future.delayed(const Duration(milliseconds: 1200), () {
        if (mounted) {
          _showSupportPrompt();
        }
      });
    }
  }

  String _getSymbol(int index) {
    const symbols = ['🌸', '🌿', '🌙', '🌊', '☁️', '🕊️', '✨', '🌼', '🍃', '⭐'];

    return symbols[index % symbols.length];
  }

  bool _containsEmotionalWords(String text) {
    final lower = text.toLowerCase();

    const emotionalWords = [
      'sad',
      'sadness',
      'lonely',
      'loneliness',
      'upset',
      'stressed',
      'stress',
      'anxious',
      'anxiety',
      'worried',
      'overwhelmed',
      'crying',
      'cry',
      'hurt',
      'angry',
      'frustrated',
      'hopeless',
      'not okay',
      'not ok',
      'bad day',
      'feeling down',
      'feel down',
      'feeling low',
      'feel low',
      'struggling',
      'struggle',
      'difficult day',
    ];

    return emotionalWords.any((word) => lower.contains(word));
  }

  void _showSupportPrompt() {
    showDialog<void>(
      context: context,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
          ),
          title: const Text(
            'You do not have to handle everything alone',
            style: TextStyle(
              color: AppColors.primary,
              fontWeight: FontWeight.w800,
            ),
          ),
          content: const Text(
            'It sounds like you may be having a difficult moment. '
            'Would you like to reach out to someone you trust or connect '
            'with a counsellor or student support service?',
            style: TextStyle(color: AppColors.muted, height: 1.5),
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(context);
              },
              child: const Text('Maybe later'),
            ),
            FilledButton(
              onPressed: () {
                Navigator.pop(context);
                _showSupportInfo();
              },
              child: const Text('Get support'),
            ),
          ],
        );
      },
    );
  }

  void _showSupportInfo() {
    showDialog<void>(
      context: context,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
          ),
          title: const Row(
            children: [
              Text('🌷', style: TextStyle(fontSize: 28)),
              SizedBox(width: 10),
              Expanded(child: Text('Student Support')),
            ],
          ),
          content: const Text(
            'You can talk to someone you trust, a counsellor, '
            'or your university student support service.\n\n'
            'This section can later be connected to the official '
            'USP counselling and student-support contact details.',
            style: TextStyle(color: AppColors.muted, height: 1.5),
          ),
          actions: [
            FilledButton(
              onPressed: () {
                Navigator.pop(context);
              },
              child: const Text('Okay'),
            ),
          ],
        );
      },
    );
  }

  void _openInput() {
    setState(() {
      _showInput = true;
    });
  }

  void _closeInput() {
    setState(() {
      _showInput = false;
      _controller.clear();
    });
  }

  void _showEntry(_GratitudeItem entry) {
    showDialog<void>(
      context: context,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
          ),
          title: Row(
            children: [
              Text(entry.symbol, style: const TextStyle(fontSize: 30)),
              const SizedBox(width: 12),
              const Expanded(child: Text('A little moment')),
            ],
          ),
          content: Text(
            entry.text,
            style: const TextStyle(
              fontSize: 16,
              height: 1.5,
              color: AppColors.primary,
            ),
          ),
          actions: [
            FilledButton(
              onPressed: () {
                Navigator.pop(context);
              },
              child: const Text('Close'),
            ),
          ],
        );
      },
    );
  }

  void _clearEntries() {
    if (_entries.isEmpty) {
      return;
    }

    showDialog<void>(
      context: context,
      builder: (context) {
        return AlertDialog(
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(24),
          ),
          title: const Text('Empty the jar?'),
          content: const Text(
            'This will remove the moments currently in your jar.',
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.pop(context);
              },
              child: const Text('Cancel'),
            ),
            FilledButton(
              onPressed: () {
                setState(() {
                  _entries.clear();
                  _newStoneId = null;
                  _animateNewStone = false;
                });

                Navigator.pop(context);
              },
              child: const Text('Clear'),
            ),
          ],
        );
      },
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Gratitude Jar'), centerTitle: true),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(20, 20, 20, 32),
          child: Column(
            children: [
              Container(
                width: double.infinity,
                padding: const EdgeInsets.all(22),
                decoration: BoxDecoration(
                  color: AppColors.softLavender,
                  borderRadius: BorderRadius.circular(26),
                  border: Border.all(
                    color: AppColors.primary.withValues(alpha: 0.08),
                  ),
                ),
                child: Column(
                  children: [
                    const Text('🌷', style: TextStyle(fontSize: 42)),
                    const SizedBox(height: 10),
                    Text(
                      'Your little jar of good moments',
                      textAlign: TextAlign.center,
                      style: Theme.of(context).textTheme.titleLarge?.copyWith(
                        color: AppColors.primary,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'Add something that made your day a little brighter. '
                      'It can be something big, small, or simply something '
                      'you would like to remember.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AppColors.muted, height: 1.45),
                    ),
                  ],
                ),
              ),

              const SizedBox(height: 24),

              AnimatedBuilder(
                animation: _jarAnimationController,
                builder: (context, child) {
                  final scale = 1.0 + (_jarAnimationController.value * 0.035);

                  return Transform.scale(scale: scale, child: child);
                },
                child: _buildJar(),
              ),

              const SizedBox(height: 18),

              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 18,
                  vertical: 10,
                ),
                decoration: BoxDecoration(
                  color: AppColors.softSage,
                  borderRadius: BorderRadius.circular(30),
                ),
                child: Text(
                  _entries.isEmpty
                      ? 'Your jar is waiting for its first moment'
                      : '${_entries.length} moment${_entries.length == 1 ? '' : 's'} in your jar',
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),

              const SizedBox(height: 22),

              if (_showInput)
                _buildInputBox()
              else
                SizedBox(
                  width: double.infinity,
                  child: FilledButton.icon(
                    onPressed: _openInput,
                    icon: const Icon(Icons.add_rounded),
                    label: const Padding(
                      padding: EdgeInsets.symmetric(vertical: 5),
                      child: Text('Add a moment'),
                    ),
                  ),
                ),

              const SizedBox(height: 24),

              if (_entries.isNotEmpty) ...[
                Row(
                  children: [
                    const Expanded(
                      child: Text(
                        'Your moments',
                        style: TextStyle(
                          color: AppColors.primary,
                          fontSize: 18,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                    ),
                    TextButton(
                      onPressed: _clearEntries,
                      child: const Text('Clear'),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                ..._entries.map(
                  (entry) => Padding(
                    padding: const EdgeInsets.only(bottom: 10),
                    child: _buildEntryCard(entry),
                  ),
                ),
              ] else
                _buildEmptyState(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildJar() {
    return SizedBox(
      height: 310,
      width: 250,
      child: Stack(
        alignment: Alignment.center,
        children: [
          Positioned(
            bottom: 5,
            child: Container(
              width: 170,
              height: 22,
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.10),
                borderRadius: BorderRadius.circular(100),
              ),
            ),
          ),

          Positioned(
            top: 52,
            child: Container(
              width: 190,
              height: 225,
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Colors.white.withValues(alpha: 0.88),
                    AppColors.softLavender.withValues(alpha: 0.90),
                  ],
                ),
                borderRadius: const BorderRadius.only(
                  bottomLeft: Radius.circular(48),
                  bottomRight: Radius.circular(48),
                  topLeft: Radius.circular(30),
                  topRight: Radius.circular(30),
                ),
                border: Border.all(
                  color: AppColors.primary.withValues(alpha: 0.18),
                  width: 2,
                ),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.10),
                    blurRadius: 20,
                    offset: const Offset(0, 10),
                  ),
                ],
              ),
              child: ClipRRect(
                borderRadius: const BorderRadius.only(
                  bottomLeft: Radius.circular(46),
                  bottomRight: Radius.circular(46),
                  topLeft: Radius.circular(28),
                  topRight: Radius.circular(28),
                ),
                child: Stack(
                  children: [
                    Positioned(
                      left: 22,
                      top: 25,
                      child: Container(
                        width: 18,
                        height: 120,
                        decoration: BoxDecoration(
                          color: Colors.white.withValues(alpha: 0.50),
                          borderRadius: BorderRadius.circular(20),
                        ),
                      ),
                    ),

                    if (_entries.isEmpty)
                      const Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text('🌷', style: TextStyle(fontSize: 38)),
                            SizedBox(height: 8),
                            Text(
                              'Add your\nfirst moment',
                              textAlign: TextAlign.center,
                              style: TextStyle(
                                color: AppColors.primary,
                                fontWeight: FontWeight.w700,
                                height: 1.3,
                              ),
                            ),
                          ],
                        ),
                      )
                    else
                      Padding(
                        padding: const EdgeInsets.fromLTRB(22, 38, 22, 18),
                        child: Align(
                          alignment: Alignment.bottomCenter,
                          child: Wrap(
                            alignment: WrapAlignment.center,
                            spacing: 7,
                            runSpacing: 6,
                            children: _entries
                                .take(18)
                                .map(
                                  (entry) => _buildGratitudeStone(
                                    entry,
                                    animate:
                                        _animateNewStone &&
                                        entry.id == _newStoneId,
                                  ),
                                )
                                .toList(),
                          ),
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ),

          // Jar neck
          Positioned(
            top: 32,
            child: Container(
              width: 120,
              height: 38,
              decoration: BoxDecoration(
                color: AppColors.softLavender,
                borderRadius: BorderRadius.circular(10),
                border: Border.all(
                  color: AppColors.primary.withValues(alpha: 0.18),
                  width: 2,
                ),
              ),
            ),
          ),

          // Jar lid
          Positioned(
            top: 20,
            child: Container(
              width: 140,
              height: 28,
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.85),
                borderRadius: BorderRadius.circular(12),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.primary.withValues(alpha: 0.15),
                    blurRadius: 8,
                    offset: const Offset(0, 4),
                  ),
                ],
              ),
              child: Center(
                child: Container(
                  width: 70,
                  height: 5,
                  decoration: BoxDecoration(
                    color: Colors.white.withValues(alpha: 0.45),
                    borderRadius: BorderRadius.circular(10),
                  ),
                ),
              ),
            ),
          ),

          const Positioned(
            left: 5,
            top: 75,
            child: Text(
              '✦',
              style: TextStyle(fontSize: 24, color: AppColors.primary),
            ),
          ),

          const Positioned(
            right: 5,
            top: 100,
            child: Text(
              '✧',
              style: TextStyle(fontSize: 25, color: AppColors.primary),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGratitudeStone(_GratitudeItem entry, {bool animate = false}) {
    return TweenAnimationBuilder<double>(
      key: ValueKey(entry.id),

      // Starts above the jar.
      tween: Tween<double>(begin: animate ? -120.0 : 0.0, end: 0.0),

      duration: const Duration(milliseconds: 1000),

      // This creates the drop + bounce effect.
      curve: Curves.bounceOut,

      builder: (context, verticalOffset, child) {
        return Transform.translate(
          offset: Offset(0, verticalOffset),
          child: child,
        );
      },

      child: Material(
        color: Colors.transparent,
        child: InkWell(
          onTap: () => _showEntry(entry),
          customBorder: const CircleBorder(),
          child: Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: Colors.white.withValues(alpha: 0.88),
              shape: BoxShape.circle,
              border: Border.all(
                color: AppColors.primary.withValues(alpha: 0.12),
                width: 1.5,
              ),
              boxShadow: [
                BoxShadow(
                  color: AppColors.primary.withValues(alpha: 0.12),
                  blurRadius: 6,
                  offset: const Offset(0, 3),
                ),
              ],
            ),
            child: Center(
              child: Text(entry.symbol, style: const TextStyle(fontSize: 21)),
            ),
          ),
        ),
      ),
    );
  }

  Widget _buildInputBox() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: AppColors.softBlush,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: AppColors.primary.withValues(alpha: 0.08)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Row(
            children: [
              Text('🌸', style: TextStyle(fontSize: 24)),
              SizedBox(width: 8),
              Expanded(
                child: Text(
                  'What would you like to remember?',
                  style: TextStyle(
                    color: AppColors.primary,
                    fontWeight: FontWeight.w800,
                    fontSize: 16,
                  ),
                ),
              ),
            ],
          ),

          const SizedBox(height: 12),

          TextField(
            controller: _controller,
            maxLines: 3,
            textInputAction: TextInputAction.done,
            decoration: InputDecoration(
              hintText: 'For example: I enjoyed talking with my friend today.',
              hintStyle: const TextStyle(color: AppColors.muted, fontSize: 13),
              filled: true,
              fillColor: Colors.white.withValues(alpha: 0.75),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(16),
                borderSide: BorderSide.none,
              ),
              contentPadding: const EdgeInsets.all(15),
            ),
          ),

          const SizedBox(height: 12),

          Row(
            children: [
              Expanded(
                child: OutlinedButton(
                  onPressed: _closeInput,
                  child: const Text('Cancel'),
                ),
              ),
              const SizedBox(width: 10),
              Expanded(
                child: FilledButton(
                  onPressed: _addEntry,
                  child: const Text('Add to jar'),
                ),
              ),
            ],
          ),

          const SizedBox(height: 8),

          const Text(
            'It is okay if today was difficult. You can also write '
            'about something small that helped you through the day.',
            style: TextStyle(color: AppColors.muted, fontSize: 12, height: 1.4),
          ),
        ],
      ),
    );
  }

  Widget _buildEntryCard(_GratitudeItem entry) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: () => _showEntry(entry),
        borderRadius: BorderRadius.circular(18),
        child: Ink(
          padding: const EdgeInsets.all(15),
          decoration: BoxDecoration(
            color: AppColors.surface,
            borderRadius: BorderRadius.circular(18),
            border: Border.all(
              color: AppColors.primary.withValues(alpha: 0.10),
            ),
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withValues(alpha: 0.04),
                blurRadius: 8,
                offset: const Offset(0, 3),
              ),
            ],
          ),
          child: Row(
            children: [
              Container(
                width: 46,
                height: 46,
                decoration: BoxDecoration(
                  color: AppColors.softLavender,
                  shape: BoxShape.circle,
                ),
                child: Center(
                  child: Text(
                    entry.symbol,
                    style: const TextStyle(fontSize: 24),
                  ),
                ),
              ),

              const SizedBox(width: 13),

              Expanded(
                child: Text(
                  entry.text,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(
                    color: AppColors.primary,
                    fontSize: 14,
                    height: 1.4,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),

              const SizedBox(width: 8),

              const Icon(Icons.chevron_right_rounded, color: AppColors.muted),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildEmptyState() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(22),
      decoration: BoxDecoration(
        color: AppColors.softSage.withValues(alpha: 0.55),
        borderRadius: BorderRadius.circular(22),
      ),
      child: const Column(
        children: [
          Text('🌿', style: TextStyle(fontSize: 32)),
          SizedBox(height: 8),
          Text(
            'Your jar is ready',
            style: TextStyle(
              color: AppColors.primary,
              fontWeight: FontWeight.w800,
              fontSize: 16,
            ),
          ),
          SizedBox(height: 5),
          Text(
            'There is no right or wrong thing to add. '
            'Start with something small.',
            textAlign: TextAlign.center,
            style: TextStyle(color: AppColors.muted, height: 1.4, fontSize: 13),
          ),
        ],
      ),
    );
  }
}

class _GratitudeItem {
  const _GratitudeItem({
    required this.text,
    required this.symbol,
    required this.id,
  });

  final String text;
  final String symbol;
  final String id;
}
