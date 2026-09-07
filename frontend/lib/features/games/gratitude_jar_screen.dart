import 'dart:math';

import 'package:flutter/material.dart';

import '../../core/theme/app_theme.dart';

class GratitudeJarScreen extends StatefulWidget {
  const GratitudeJarScreen({super.key});

  @override
  State<GratitudeJarScreen> createState() => _GratitudeJarScreenState();
}

class _GratitudeJarScreenState extends State<GratitudeJarScreen> {
  final TextEditingController _controller = TextEditingController();

  final List<_GratitudeEntry> _entries = [];

  final List<String> _symbols = ['🌸', '🌿', '⭐', '🌙', '☁️', '🦋'];

  final List<String> _prompts = [
    'What made you smile today?',
    'What is one small thing you enjoyed today?',
    'Who are you thankful for?',
    'What is something you are looking forward to?',
    'What is one positive moment from today?',
    'What is something that made your day easier?',
  ];

  String _currentPrompt = 'What made you smile today?';

  void _addGratitude() {
    final text = _controller.text.trim();

    if (text.isEmpty) {
      _showMessage('Please write something before adding it to your jar.');
      return;
    }

    if (text.length < 3) {
      _showMessage('Please enter a little more detail.');
      return;
    }

    final symbol = _symbols[_entries.length % _symbols.length];

    setState(() {
      _entries.insert(0, _GratitudeEntry(text: text, symbol: symbol));

      _controller.clear();
    });

    _showMessage('Your moment has been added to the jar.');
  }

  void _getPrompt() {
    final random = Random();

    setState(() {
      _currentPrompt = _prompts[random.nextInt(_prompts.length)];
    });
  }

  void _showGratitude(_GratitudeEntry entry) {
    showDialog<void>(
      context: context,
      builder: (dialogContext) {
        return AlertDialog(
          title: Row(
            children: [
              Text(entry.symbol, style: const TextStyle(fontSize: 30)),
              const SizedBox(width: 10),
              const Expanded(child: Text('Gratitude Moment')),
            ],
          ),
          content: Text(
            entry.text,
            style: const TextStyle(fontSize: 16, height: 1.5),
          ),
          actions: [
            TextButton(
              onPressed: () {
                Navigator.of(dialogContext).pop();
              },
              child: const Text('Close'),
            ),
            TextButton(
              onPressed: () {
                Navigator.of(dialogContext).pop();

                setState(() {
                  _entries.remove(entry);
                });

                _showMessage('Gratitude moment removed.');
              },
              child: const Text('Delete'),
            ),
          ],
        );
      },
    );
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context).hideCurrentSnackBar();

    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(content: Text(message), behavior: SnackBarBehavior.floating),
    );
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Gratitude Jar')),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(20, 12, 20, 32),
        children: [
          Text(
            'Your Gratitude Jar',
            style: Theme.of(context).textTheme.headlineSmall,
            textAlign: TextAlign.center,
          ),

          const SizedBox(height: 8),

          const Text(
            'Turn your thoughts and positive moments into little symbols inside your jar.',
            style: TextStyle(color: AppColors.muted, height: 1.4),
            textAlign: TextAlign.center,
          ),

          const SizedBox(height: 24),

          // JAR
          _GratitudeJarVisual(entries: _entries, onSymbolTap: _showGratitude),

          const SizedBox(height: 12),

          Center(
            child: Text(
              '${_entries.length} ${_entries.length == 1 ? 'moment' : 'moments'} in your jar',
              style: const TextStyle(
                color: AppColors.muted,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),

          const SizedBox(height: 28),

          // PROMPT
          Card(
            color: AppColors.softLavender,
            child: Padding(
              padding: const EdgeInsets.all(AppSpacing.lg),
              child: Column(
                children: [
                  const Icon(
                    Icons.lightbulb_outline_rounded,
                    color: AppColors.primary,
                    size: 28,
                  ),

                  const SizedBox(height: 10),

                  const Text(
                    'Need some inspiration?',
                    style: TextStyle(fontWeight: FontWeight.w700),
                  ),

                  const SizedBox(height: 8),

                  Text(
                    _currentPrompt,
                    textAlign: TextAlign.center,
                    style: const TextStyle(color: AppColors.muted, height: 1.4),
                  ),

                  const SizedBox(height: 12),

                  OutlinedButton.icon(
                    onPressed: _getPrompt,
                    icon: const Icon(Icons.refresh_rounded),
                    label: const Text('Give me another prompt'),
                  ),
                ],
              ),
            ),
          ),

          const SizedBox(height: 24),

          // INPUT
          const Text(
            'Add a moment',
            style: TextStyle(fontSize: 18, fontWeight: FontWeight.w700),
          ),

          const SizedBox(height: 8),

          const Text(
            'It can be something small that made your day better.',
            style: TextStyle(color: AppColors.muted),
          ),

          const SizedBox(height: 10),

          TextField(
            controller: _controller,
            maxLines: 4,
            maxLength: 250,
            textInputAction: TextInputAction.newline,
            decoration: const InputDecoration(
              hintText: 'Write a thought or positive moment...',
              border: OutlineInputBorder(),
              alignLabelWithHint: true,
            ),
          ),

          const SizedBox(height: 8),

          SizedBox(
            width: double.infinity,
            child: FilledButton.icon(
              onPressed: _addGratitude,
              icon: const Icon(Icons.add_rounded),
              label: const Text('Add to my jar'),
            ),
          ),

          const SizedBox(height: 30),

          // EXPLANATION
          if (_entries.isNotEmpty) ...[
            Text('Your Symbols', style: Theme.of(context).textTheme.titleLarge),

            const SizedBox(height: 6),

            const Text(
              'Tap any symbol to see the thought behind it.',
              style: TextStyle(color: AppColors.muted),
            ),

            const SizedBox(height: 12),

            for (final entry in _entries)
              _GratitudeEntryCard(
                entry: entry,
                onTap: () => _showGratitude(entry),
              ),
          ] else
            const Card(
              child: Padding(
                padding: EdgeInsets.all(AppSpacing.xl),
                child: Column(
                  children: [
                    Text('🌱', style: TextStyle(fontSize: 42)),
                    SizedBox(height: 12),
                    Text(
                      'Your jar is waiting',
                      style: TextStyle(fontWeight: FontWeight.w700),
                    ),
                    SizedBox(height: 6),
                    Text(
                      'Add your first moment and watch your jar fill with symbols.',
                      textAlign: TextAlign.center,
                      style: TextStyle(color: AppColors.muted),
                    ),
                  ],
                ),
              ),
            ),
        ],
      ),
    );
  }
}

// ------------------------------------------------------------
// GRATITUDE ENTRY MODEL
// ------------------------------------------------------------

class _GratitudeEntry {
  final String text;
  final String symbol;

  const _GratitudeEntry({required this.text, required this.symbol});
}

// ------------------------------------------------------------
// JAR VISUAL
// ------------------------------------------------------------

class _GratitudeJarVisual extends StatelessWidget {
  final List<_GratitudeEntry> entries;
  final void Function(_GratitudeEntry entry) onSymbolTap;

  const _GratitudeJarVisual({required this.entries, required this.onSymbolTap});

  @override
  Widget build(BuildContext context) {
    return Container(
      height: 330,
      width: double.infinity,
      decoration: BoxDecoration(
        borderRadius: BorderRadius.circular(32),
        color: AppColors.softSage.withValues(alpha: 0.25),
        border: Border.all(color: AppColors.softSage, width: 2),
      ),
      child: Stack(
        alignment: Alignment.center,
        children: [
          // Glass jar body
          Positioned(
            left: 28,
            right: 28,
            top: 45,
            bottom: 20,
            child: Container(
              decoration: BoxDecoration(
                borderRadius: const BorderRadius.only(
                  bottomLeft: Radius.circular(70),
                  bottomRight: Radius.circular(70),
                  topLeft: Radius.circular(25),
                  topRight: Radius.circular(25),
                ),
                color: Colors.white.withValues(alpha: 0.75),
                border: Border.all(
                  color: AppColors.primary.withValues(alpha: 0.25),
                  width: 2,
                ),
              ),
              child: Padding(
                padding: const EdgeInsets.fromLTRB(20, 30, 20, 20),
                child: entries.isEmpty
                    ? const Center(
                        child: Column(
                          mainAxisSize: MainAxisSize.min,
                          children: [
                            Text(
                              'Your jar is empty',
                              style: TextStyle(
                                color: AppColors.muted,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            SizedBox(height: 6),
                            Text(
                              'Add a moment below',
                              style: TextStyle(
                                color: AppColors.muted,
                                fontSize: 13,
                              ),
                            ),
                          ],
                        ),
                      )
                    : SingleChildScrollView(
                        child: Wrap(
                          alignment: WrapAlignment.center,
                          spacing: 12,
                          runSpacing: 12,
                          children: [
                            for (final entry in entries)
                              _JarSymbol(
                                entry: entry,
                                onTap: () => onSymbolTap(entry),
                              ),
                          ],
                        ),
                      ),
              ),
            ),
          ),

          // Jar lid
          Positioned(
            top: 20,
            left: 75,
            right: 75,
            child: Container(
              height: 35,
              decoration: BoxDecoration(
                color: AppColors.primary.withValues(alpha: 0.85),
                borderRadius: BorderRadius.circular(14),
              ),
            ),
          ),

          // Jar highlight
          Positioned(
            left: 50,
            top: 75,
            bottom: 45,
            child: Container(
              width: 8,
              decoration: BoxDecoration(
                borderRadius: BorderRadius.circular(10),
                color: Colors.white.withValues(alpha: 0.6),
              ),
            ),
          ),
        ],
      ),
    );
  }
}

// ------------------------------------------------------------
// SYMBOL INSIDE JAR
// ------------------------------------------------------------

class _JarSymbol extends StatelessWidget {
  final _GratitudeEntry entry;
  final VoidCallback onTap;

  const _JarSymbol({required this.entry, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Material(
      color: Colors.transparent,
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(30),
        child: Container(
          width: 55,
          height: 55,
          decoration: BoxDecoration(
            shape: BoxShape.circle,
            color: Colors.white.withValues(alpha: 0.95),
            boxShadow: [
              BoxShadow(
                blurRadius: 8,
                spreadRadius: 1,
                color: Colors.black.withValues(alpha: 0.08),
              ),
            ],
          ),
          alignment: Alignment.center,
          child: Text(entry.symbol, style: const TextStyle(fontSize: 27)),
        ),
      ),
    );
  }
}

// ------------------------------------------------------------
// ENTRY CARD
// ------------------------------------------------------------

class _GratitudeEntryCard extends StatelessWidget {
  final _GratitudeEntry entry;
  final VoidCallback onTap;

  const _GratitudeEntryCard({required this.entry, required this.onTap});

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 10),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Padding(
          padding: const EdgeInsets.all(14),
          child: Row(
            children: [
              Container(
                width: 50,
                height: 50,
                decoration: BoxDecoration(
                  color: AppColors.softLavender,
                  borderRadius: BorderRadius.circular(15),
                ),
                alignment: Alignment.center,
                child: Text(entry.symbol, style: const TextStyle(fontSize: 25)),
              ),

              const SizedBox(width: 14),

              Expanded(
                child: Text(
                  entry.text,
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                  style: const TextStyle(height: 1.4),
                ),
              ),

              const SizedBox(width: 8),

              const Icon(
                Icons.arrow_forward_ios_rounded,
                size: 16,
                color: AppColors.muted,
              ),
            ],
          ),
        ),
      ),
    );
  }
}
