import 'package:flutter/material.dart';

import '../../../core/network/api_service.dart';
import '../../../shared/widgets/app_ui.dart';
import '../data/support_content.dart';
import 'wellbeing_collection_screen.dart';

/// The "Resource" bottom-navigation destination: support the student can reach
/// out to, including talking to a counsellor.
///
/// The content is the admin-published `resource` category — the same records
/// that appear under "Browse by feeling" in the wellbeing hub — so anything
/// published through the Web Admin shows up here without a code change. This
/// screen only chooses the slice; the list, search, and detail behaviour are
/// the existing [WellbeingCollectionScreen].
class ResourceScreen extends StatefulWidget {
  const ResourceScreen({
    super.key,
    ApiService? apiService,
    this.embedded = false,
  }) : _injectedApiService = apiService;

  final ApiService? _injectedApiService;

  /// True when shown inside the bottom-navigation shell, which supplies its
  /// own app bar. Pushed routes keep their own.
  final bool embedded;

  @override
  State<ResourceScreen> createState() => _ResourceScreenState();
}

class _ResourceScreenState extends State<ResourceScreen> {
  late final ApiService _api;
  late Future<List<WellbeingActivity>> _activities;

  @override
  void initState() {
    super.initState();
    _api = widget._injectedApiService ?? ApiService();
    _load();
  }

  void _load() => _activities = _api.wellbeingActivities();

  @override
  void dispose() {
    if (widget._injectedApiService == null) _api.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: widget.embedded ? null : AppBar(title: const Text('Resource')),
    body: SafeArea(
      child: FutureBuilder<List<WellbeingActivity>>(
        future: _activities,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const AppLoadingView(message: 'Finding support for you…');
          }
          if (snapshot.hasError) {
            return AppStateView(
              icon: Icons.cloud_off_outlined,
              title: 'Couldn\'t load resources',
              message: 'Check your connection and try again.',
              actionLabel: 'Try again',
              onAction: () => setState(_load),
            );
          }

          // Same two filters the hub applies before it groups by feeling —
          // withheld titles stay withheld here too, and the category key is
          // the hub's grouping key, so both routes show the same records.
          final resources = (snapshot.data ?? const <WellbeingActivity>[])
              .where(
                (activity) =>
                    !hiddenWellbeingActivityTitles.contains(
                      activity.title.trim().toLowerCase(),
                    ) &&
                    activity.category.trim().toLowerCase() == 'resource',
              )
              .toList();

          if (resources.isEmpty) {
            return const AppStateView(
              icon: Icons.support_agent_outlined,
              title: 'Resources are being prepared',
              message:
                  'Support contacts and counselling resources will appear here '
                  'once the SheZen team publishes them.',
            );
          }

          // Deliberately the same call the wellbeing hub makes for its
          // "Resource" feeling tile, so this tab is a shortcut to that exact
          // collection rather than a second screen that drifts away from it.
          return WellbeingCollectionScreen(
            title: 'Resource',
            activities: resources,
            subtitle:
                'Places to turn and people to talk to, including counselling '
                'support.',
            searchHint: 'Search resource',
            showThumbnails: true,
            embedded: true,
          );
        },
      ),
    ),
  );
}
