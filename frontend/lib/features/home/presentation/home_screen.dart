import 'package:flutter/material.dart';

import '../../../core/config/api_config.dart';
import '../../../core/network/api_service.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  final ApiService _api = ApiService();

  bool _checkingApi = false;
  String? _apiMessage;
  bool? _apiConnected;

  Future<void> _checkApi() async {
    setState(() {
      _checkingApi = true;
      _apiMessage = null;
      _apiConnected = null;
    });

    try {
      final result = await _api.health();
      if (!mounted) return;

      setState(() {
        _apiConnected = true;
        _apiMessage = result['message']?.toString() ?? 'Backend connected.';
      });
    } catch (error) {
      if (!mounted) return;

      setState(() {
        _apiConnected = false;
        _apiMessage =
            'Connection failed. Make sure Laravel is running on port 8000.\n$error';
      });
    } finally {
      if (mounted) {
        setState(() => _checkingApi = false);
      }
    }
  }

  @override
  void dispose() {
    _api.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final colors = Theme.of(context).colorScheme;

    return Scaffold(
      appBar: AppBar(
        title: const Text('SheZen Harmony'),
        centerTitle: false,
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: [
            Text(
              'A calmer space for your wellbeing.',
              style: Theme.of(context).textTheme.headlineSmall?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: 8),
            Text(
              'Development starter — assessment questions, scoring and '
              'referral rules must be replaced with the client-approved framework.',
              style: Theme.of(context).textTheme.bodyMedium,
            ),
            const SizedBox(height: 24),
            _FeatureCard(
              icon: Icons.monitor_heart_outlined,
              title: 'Check your stress',
              description: 'Stress assessment module placeholder',
              onTap: () => _showPlaceholder(context, 'Stress assessment'),
            ),
            const SizedBox(height: 12),
            _FeatureCard(
              icon: Icons.spa_outlined,
              title: 'Activities',
              description: 'Breathing, reflection and configured interventions',
              onTap: () => _showPlaceholder(context, 'Activities'),
            ),
            const SizedBox(height: 12),
            _FeatureCard(
              icon: Icons.auto_stories_outlined,
              title: 'Journal',
              description: 'Personal reflection module placeholder',
              onTap: () => _showPlaceholder(context, 'Journal'),
            ),
            const SizedBox(height: 28),
            Card(
              color: colors.surfaceContainerHighest,
              child: Padding(
                padding: const EdgeInsets.all(16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.stretch,
                  children: [
                    Text(
                      'Development connection',
                      style: Theme.of(context).textTheme.titleMedium?.copyWith(
                            fontWeight: FontWeight.w700,
                          ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'API: ${ApiConfig.baseUrl}',
                      style: Theme.of(context).textTheme.bodySmall,
                    ),
                    const SizedBox(height: 12),
                    FilledButton.icon(
                      onPressed: _checkingApi ? null : _checkApi,
                      icon: _checkingApi
                          ? const SizedBox.square(
                              dimension: 18,
                              child: CircularProgressIndicator(strokeWidth: 2),
                            )
                          : const Icon(Icons.link),
                      label: Text(
                        _checkingApi ? 'Checking...' : 'Test API connection',
                      ),
                    ),
                    if (_apiMessage != null) ...[
                      const SizedBox(height: 12),
                      Row(
                        crossAxisAlignment: CrossAxisAlignment.start,
                        children: [
                          Icon(
                            _apiConnected == true
                                ? Icons.check_circle_outline
                                : Icons.error_outline,
                            color: _apiConnected == true
                                ? colors.primary
                                : colors.error,
                          ),
                          const SizedBox(width: 8),
                          Expanded(child: Text(_apiMessage!)),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  static void _showPlaceholder(BuildContext context, String feature) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text('$feature is ready for the next development phase.'),
      ),
    );
  }
}

class _FeatureCard extends StatelessWidget {
  const _FeatureCard({
    required this.icon,
    required this.title,
    required this.description,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String description;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(18),
          child: Row(
            children: [
              CircleAvatar(
                child: Icon(icon),
              ),
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
                    const SizedBox(height: 3),
                    Text(description),
                  ],
                ),
              ),
              const Icon(Icons.chevron_right),
            ],
          ),
        ),
      ),
    );
  }
}
