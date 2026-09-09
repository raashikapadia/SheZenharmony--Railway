import 'package:flutter/material.dart';

import '../../../core/network/api_service.dart';
import '../../../shared/widgets/app_ui.dart';
import '../data/support_content.dart';
import 'helpline_section.dart';
import 'wellbeing_collection_screen.dart';

/// The "Resource" bottom-navigation destination: support the student can reach
/// out to, including talking to a counsellor.
///
/// Two admin-published sources, both live without a code change:
///  * the Resource section of the Web Admin, which publishes helplines and
///    other support contacts — shown first, since a phone number is the
///    fastest route to a person;
///  * the `resource` category of the wellbeing library — the same records that
///    appear under "Browse by feeling" in the wellbeing hub.
///
/// The list, search, and detail behaviour for the second source stays the
/// existing [WellbeingCollectionScreen]; this screen only chooses the slice and
/// hands it the helplines to lead with.
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

/// The two admin-published sources this tab merges.
class _ResourceContent {
  const _ResourceContent({required this.helplines, required this.activities});

  final List<HelplineResource> helplines;
  final List<WellbeingActivity> activities;
}

class _ResourceScreenState extends State<ResourceScreen> {
  late final ApiService _api;
  late Future<_ResourceContent> _content;

  @override
  void initState() {
    super.initState();
    _api = widget._injectedApiService ?? ApiService();
    _load();
  }

  void _load() => _content = _fetch();

  Future<_ResourceContent> _fetch() async {
    // Fetched together so the tab paints once rather than twice.
    final results = await Future.wait([
      _api.helplines(),
      _api.wellbeingActivities(),
    ]);

    // Same two filters the hub applies before it groups by feeling — withheld
    // titles stay withheld here too, and the category key is the hub's
    // grouping key, so both routes show the same records.
    final activities = (results[1] as List<WellbeingActivity>)
        .where(
          (activity) =>
              !hiddenWellbeingActivityTitles.contains(
                activity.title.trim().toLowerCase(),
              ) &&
              activity.category.trim().toLowerCase() == 'resource',
        )
        .toList();

    return _ResourceContent(
      helplines: results[0] as List<HelplineResource>,
      activities: activities,
    );
  }

  @override
  void dispose() {
    if (widget._injectedApiService == null) _api.close();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: widget.embedded ? null : AppBar(title: const Text('Resource')),
    body: SafeArea(
      child: FutureBuilder<_ResourceContent>(
        future: _content,
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

          final helplines = snapshot.data?.helplines ?? const [];
          final activities = snapshot.data?.activities ?? const [];

          if (helplines.isEmpty && activities.isEmpty) {
            return const AppStateView(
              icon: Icons.support_agent_outlined,
              title: 'Resources are being prepared',
              message:
                  'Support contacts and counselling resources will appear here '
                  'once the SheZen team publishes them.',
            );
          }

          final helplineSection = helplines.isEmpty
              ? null
              : HelplineSection(helplines: helplines);

          // Nothing published in the wellbeing library yet, so there is no
          // collection to search — show the helplines on their own.
          if (activities.isEmpty) {
            return ListView(
              padding: const EdgeInsets.fromLTRB(20, 4, 20, 32),
              children: [helplineSection!],
            );
          }

          // Deliberately the same call the wellbeing hub makes for its
          // "Resource" feeling tile, so this tab is a shortcut to that exact
          // collection rather than a second screen that drifts away from it.
          return WellbeingCollectionScreen(
            title: 'Resource',
            activities: activities,
            subtitle:
                'Places to turn and people to talk to, including counselling '
                'support.',
            searchHint: 'Search resource',
            showThumbnails: true,
            embedded: true,
            header: helplineSection,
          );
        },
      ),
    ),
  );
}
