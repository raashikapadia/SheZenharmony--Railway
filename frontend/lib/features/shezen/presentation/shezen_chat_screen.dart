import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../auth/application/auth_provider.dart';
import '../data/chat_buddy_models.dart';
import 'shezen_intro_screen.dart';

class ShezenChatScreen extends StatefulWidget {
  const ShezenChatScreen({super.key, ApiService? apiService})
    : _injectedApiService = apiService;

  final ApiService? _injectedApiService;

  @override
  State<ShezenChatScreen> createState() => _ShezenChatScreenState();
}

class _ChatLine {
  const _ChatLine(this.text, {required this.fromBot, this.reply});

  final String text;
  final bool fromBot;
  final ChatBuddyReply? reply;
}

class _ShezenChatScreenState extends State<ShezenChatScreen> {
  late final ApiService _api;
  final _input = TextEditingController();
  final _scroll = ScrollController();
  late Future<ChatBuddyContent> _content;
  final List<_ChatLine> _lines = [];
  bool _sending = false;
  String? _sendError;

  @override
  void initState() {
    super.initState();
    _api = widget._injectedApiService ?? ApiService();
    _load();
  }

  void _load() {
    final token = context.read<AuthProvider>().session?.token ?? '';
    setState(() {
      _content = _api.getChatBuddyContent(token);
    });
  }

  @override
  void dispose() {
    _input.dispose();
    _scroll.dispose();
    if (widget._injectedApiService == null) _api.close();
    super.dispose();
  }

  Future<void> _send(String value) async {
    final text = value.trim();
    if (text.isEmpty || _sending) return;

    _input.clear();
    FocusScope.of(context).unfocus();
    setState(() {
      _sending = true;
      _sendError = null;
      _lines.add(_ChatLine(text, fromBot: false));
    });
    _scrollToEnd();

    try {
      final token = context.read<AuthProvider>().session?.token ?? '';
      final reply = await _api.sendChatBuddyMessage(token, text);
      if (!mounted) return;
      setState(() {
        _lines.add(_ChatLine(reply.message ?? '', fromBot: true, reply: reply));
      });
      _scrollToEnd();
    } catch (_) {
      if (!mounted) return;
      setState(() {
        _sendError = 'Your message could not be sent. Please try again.';
      });
    } finally {
      if (mounted) setState(() => _sending = false);
    }
  }

  void _scrollToEnd() {
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!_scroll.hasClients) return;
      _scroll.animateTo(
        _scroll.position.maxScrollExtent,
        duration: const Duration(milliseconds: 250),
        curve: Curves.easeOut,
      );
    });
  }

  Future<void> _openLink(ChatBuddyLink link) async {
    final url = link.url;
    if (url == null || url.isEmpty) {
      _showMessage('This linked content is not available right now.');
      return;
    }

    final opened = await launchUrl(Uri.parse(url));
    if (!opened && mounted) {
      _showMessage('This linked content is not available right now.');
    }
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(
      titleSpacing: 0,
      title: const Row(
        children: [
          ShezenAvatar(size: 36),
          SizedBox(width: AppSpacing.sm),
          Text('Chat Buddy'),
        ],
      ),
    ),
    body: SafeArea(
      child: FutureBuilder<ChatBuddyContent>(
        future: _content,
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator());
          }
          if (snapshot.hasError || !snapshot.hasData) {
            return _LoadError(onRetry: _load);
          }

          final content = snapshot.data!;
          if (!content.available) return const _UnavailableState();
          return _buildChatLayout(content);
        },
      ),
    ),
  );

  Widget _buildChatLayout(ChatBuddyContent content) => Column(
    children: [
      const _SafetyNotice(),
      Expanded(
        child: ListView(
          controller: _scroll,
          padding: const EdgeInsets.fromLTRB(
            AppSpacing.page,
            AppSpacing.sm,
            AppSpacing.page,
            AppSpacing.lg,
          ),
          children: [
            if (content.welcomeMessage?.trim().isNotEmpty == true)
              _MessageBubble(text: content.welcomeMessage!, fromBot: true),
            if (_lines.isEmpty)
              _SuggestedPrompts(
                topics: content.suggestedTopics,
                onSelected: _send,
              ),
            ..._lines.map(
              (line) => _MessageBubble(
                text: line.text,
                fromBot: line.fromBot,
                reply: line.reply,
                onPromptSelected: _send,
                onLinkSelected: _openLink,
              ),
            ),
            if (_sending)
              const Align(
                alignment: Alignment.centerLeft,
                child: Padding(
                  padding: EdgeInsets.only(top: AppSpacing.sm),
                  child: _TypingIndicator(),
                ),
              ),
            if (_sendError != null)
              _SendError(
                message: _sendError!,
                onDismiss: () {
                  setState(() => _sendError = null);
                },
              ),
          ],
        ),
      ),
      _Composer(controller: _input, enabled: !_sending, onSubmitted: _send),
    ],
  );
}

class _SafetyNotice extends StatelessWidget {
  const _SafetyNotice();

  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    margin: const EdgeInsets.fromLTRB(
      AppSpacing.page,
      AppSpacing.md,
      AppSpacing.page,
      AppSpacing.xs,
    ),
    padding: const EdgeInsets.symmetric(
      horizontal: AppSpacing.md,
      vertical: AppSpacing.sm,
    ),
    decoration: BoxDecoration(
      color: AppColors.softPeach,
      borderRadius: BorderRadius.circular(AppRadii.input),
    ),
    child: const Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Icon(Icons.info_outline_rounded, size: 18, color: AppColors.primary),
        SizedBox(width: AppSpacing.sm),
        Expanded(
          child: Text(
            'Chat Buddy is not a person or emergency service. '
            'If you are in immediate danger, contact local emergency support.',
            style: TextStyle(
              color: AppColors.muted,
              fontSize: 12,
              height: 1.35,
            ),
          ),
        ),
      ],
    ),
  );
}

class _SuggestedPrompts extends StatelessWidget {
  const _SuggestedPrompts({required this.topics, required this.onSelected});

  final List<String> topics;
  final ValueChanged<String> onSelected;

  @override
  Widget build(BuildContext context) {
    final visibleTopics = topics
        .where((topic) => topic.trim().isNotEmpty)
        .toList();
    if (visibleTopics.isEmpty) return const SizedBox.shrink();

    return Padding(
      padding: const EdgeInsets.only(top: AppSpacing.sm, bottom: AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'You could talk about',
            style: TextStyle(
              color: AppColors.muted,
              fontSize: 12,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: AppSpacing.sm),
          Wrap(
            spacing: AppSpacing.sm,
            runSpacing: AppSpacing.sm,
            children: visibleTopics
                .map(
                  (topic) => ActionChip(
                    label: Text(topic),
                    onPressed: () => onSelected(topic),
                  ),
                )
                .toList(),
          ),
        ],
      ),
    );
  }
}

class _MessageBubble extends StatelessWidget {
  const _MessageBubble({
    required this.text,
    required this.fromBot,
    this.reply,
    this.onPromptSelected,
    this.onLinkSelected,
  });

  final String text;
  final bool fromBot;
  final ChatBuddyReply? reply;
  final ValueChanged<String>? onPromptSelected;
  final ValueChanged<ChatBuddyLink>? onLinkSelected;

  @override
  Widget build(BuildContext context) {
    final hasReplyContent =
        reply != null &&
        (reply!.followUpPrompts.isNotEmpty || reply!.links.isNotEmpty);
    return Align(
      alignment: fromBot ? Alignment.centerLeft : Alignment.centerRight,
      child: Container(
        constraints: const BoxConstraints(maxWidth: 360),
        margin: const EdgeInsets.only(bottom: AppSpacing.md),
        padding: const EdgeInsets.symmetric(
          horizontal: AppSpacing.lg,
          vertical: AppSpacing.md,
        ),
        decoration: BoxDecoration(
          color: fromBot ? AppColors.softLavender : AppColors.softSage,
          borderRadius: BorderRadius.only(
            topLeft: const Radius.circular(18),
            topRight: const Radius.circular(18),
            bottomLeft: Radius.circular(fromBot ? 4 : 18),
            bottomRight: Radius.circular(fromBot ? 18 : 4),
          ),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (text.trim().isNotEmpty)
              Text(text, style: const TextStyle(height: 1.45)),
            if (reply?.isSafety == true) ...[
              const SizedBox(height: AppSpacing.md),
              const Divider(height: 1),
              const SizedBox(height: AppSpacing.sm),
              const Text(
                'Please reach out to someone you trust or local emergency support if you may be in immediate danger.',
                style: TextStyle(
                  color: AppColors.muted,
                  fontSize: 12,
                  fontWeight: FontWeight.w700,
                  height: 1.4,
                ),
              ),
            ],
            if (hasReplyContent) ...[
              const SizedBox(height: AppSpacing.sm),
              ...reply!.followUpPrompts.map(
                (prompt) => _ReplyAction(
                  icon: Icons.arrow_forward_rounded,
                  label: prompt,
                  onPressed: () => onPromptSelected?.call(prompt),
                ),
              ),
              ...reply!.links.map(
                (link) => _ReplyAction(
                  icon: Icons.open_in_new_rounded,
                  label: link.label,
                  onPressed: () => onLinkSelected?.call(link),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }
}

class _ReplyAction extends StatelessWidget {
  const _ReplyAction({
    required this.icon,
    required this.label,
    required this.onPressed,
  });

  final IconData icon;
  final String label;
  final VoidCallback onPressed;

  @override
  Widget build(BuildContext context) => Align(
    alignment: Alignment.centerLeft,
    child: TextButton.icon(
      onPressed: onPressed,
      icon: Icon(icon, size: 16),
      label: Flexible(child: Text(label, softWrap: true)),
      style: TextButton.styleFrom(
        padding: const EdgeInsets.symmetric(vertical: AppSpacing.xs),
        alignment: Alignment.centerLeft,
      ),
    ),
  );
}

class _Composer extends StatelessWidget {
  const _Composer({
    required this.controller,
    required this.enabled,
    required this.onSubmitted,
  });

  final TextEditingController controller;
  final bool enabled;
  final ValueChanged<String> onSubmitted;

  @override
  Widget build(BuildContext context) => Material(
    color: AppColors.surface,
    child: Padding(
      padding: const EdgeInsets.fromLTRB(
        AppSpacing.page,
        AppSpacing.sm,
        AppSpacing.page,
        AppSpacing.md,
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.end,
        children: [
          Expanded(
            child: TextField(
              controller: controller,
              enabled: enabled,
              minLines: 1,
              maxLines: 4,
              textInputAction: TextInputAction.send,
              onSubmitted: enabled ? onSubmitted : null,
              decoration: const InputDecoration(
                hintText: 'Write a message',
                prefixIcon: Icon(Icons.chat_bubble_outline_rounded),
              ),
            ),
          ),
          const SizedBox(width: AppSpacing.sm),
          IconButton.filled(
            tooltip: 'Send message',
            onPressed: enabled ? () => onSubmitted(controller.text) : null,
            icon: const Icon(Icons.send_rounded),
          ),
        ],
      ),
    ),
  );
}

class _TypingIndicator extends StatelessWidget {
  const _TypingIndicator();

  @override
  Widget build(BuildContext context) => Container(
    padding: const EdgeInsets.symmetric(
      horizontal: AppSpacing.md,
      vertical: AppSpacing.sm,
    ),
    decoration: BoxDecoration(
      color: AppColors.softLavender,
      borderRadius: BorderRadius.circular(AppRadii.input),
    ),
    child: const SizedBox(
      width: 18,
      height: 18,
      child: CircularProgressIndicator(strokeWidth: 2),
    ),
  );
}

class _SendError extends StatelessWidget {
  const _SendError({required this.message, required this.onDismiss});

  final String message;
  final VoidCallback onDismiss;

  @override
  Widget build(BuildContext context) => Padding(
    padding: const EdgeInsets.only(bottom: AppSpacing.md),
    child: MaterialBanner(
      content: Text(message),
      actions: [TextButton(onPressed: onDismiss, child: const Text('Dismiss'))],
    ),
  );
}

class _LoadError extends StatelessWidget {
  const _LoadError({required this.onRetry});

  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(AppSpacing.page),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.cloud_off_rounded, size: 42, color: AppColors.muted),
          const SizedBox(height: AppSpacing.md),
          const Text(
            'Chat Buddy could not be loaded.',
            textAlign: TextAlign.center,
            style: TextStyle(fontWeight: FontWeight.w700),
          ),
          const SizedBox(height: AppSpacing.sm),
          TextButton.icon(
            onPressed: onRetry,
            icon: const Icon(Icons.refresh_rounded),
            label: const Text('Try again'),
          ),
        ],
      ),
    ),
  );
}

class _UnavailableState extends StatelessWidget {
  const _UnavailableState();

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: const EdgeInsets.all(AppSpacing.page),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const ShezenAvatar(size: 72),
          const SizedBox(height: AppSpacing.lg),
          Text(
            'Chat Buddy is not available yet.',
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: AppSpacing.sm),
          const Text(
            'Please check back later.',
            textAlign: TextAlign.center,
            style: TextStyle(color: AppColors.muted),
          ),
        ],
      ),
    ),
  );
}
