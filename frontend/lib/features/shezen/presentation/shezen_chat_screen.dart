import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../core/network/api_service.dart';
import '../../../core/theme/app_theme.dart';
import '../../auth/application/auth_provider.dart';
import '../data/chat_buddy_models.dart';

class ShezenChatScreen extends StatefulWidget {
  const ShezenChatScreen({super.key, ApiService? apiService}) : _injectedApiService = apiService;
  final ApiService? _injectedApiService;
  @override State<ShezenChatScreen> createState() => _ShezenChatScreenState();
}

class _ChatLine { const _ChatLine(this.text, {required this.fromBot, this.reply}); final String text; final bool fromBot; final ChatBuddyReply? reply; }

class _ShezenChatScreenState extends State<ShezenChatScreen> {
  late final ApiService _api; final _input = TextEditingController(); final _scroll = ScrollController();
  late Future<ChatBuddyContent> _content; final List<_ChatLine> _lines = []; bool _sending = false;
  @override void initState() { super.initState(); _api = widget._injectedApiService ?? ApiService(); _load(); }
  void _load() { final token = context.read<AuthProvider>().session?.token ?? ''; _content = _api.getChatBuddyContent(token); }
  @override void dispose() { _input.dispose(); _scroll.dispose(); if (widget._injectedApiService == null) _api.close(); super.dispose(); }
  Future<void> _send(String value) async {
    final text = value.trim(); if (text.isEmpty || _sending) return; _input.clear(); setState(() { _sending = true; _lines.add(_ChatLine(text, fromBot: false)); });
    try { final token = context.read<AuthProvider>().session?.token ?? ''; final reply = await _api.sendChatBuddyMessage(token, text); if (mounted) setState(() => _lines.add(_ChatLine(reply.message ?? 'Chat Buddy is not available yet.', fromBot: true, reply: reply))); }
    catch (_) { if (mounted) ScaffoldMessenger.of(context).showSnackBar(const SnackBar(content: Text('Couldn\'t send that. Check your connection and try again.'))); }
    finally { if (mounted) setState(() => _sending = false); }
  }
  @override Widget build(BuildContext context) => Scaffold(appBar: AppBar(title: const Text('Chat Buddy')), body: SafeArea(child: FutureBuilder<ChatBuddyContent>(future: _content, builder: (context, snapshot) { if (snapshot.connectionState == ConnectionState.waiting) return const Center(child: CircularProgressIndicator()); if (snapshot.hasError) return Center(child: TextButton(onPressed: () => setState(_load), child: const Text('Try again'))); final content = snapshot.data!; if (!content.available) return const Center(child: Padding(padding: EdgeInsets.all(24), child: Text('Chat Buddy is being prepared. Please check back later.', textAlign: TextAlign.center))); return Column(children: [const Padding(padding: EdgeInsets.fromLTRB(20, 16, 20, 4), child: Text('Shezen is a rule-based companion, not a person, counsellor, or emergency service.', textAlign: TextAlign.center, style: TextStyle(color: AppColors.muted))), Expanded(child: ListView(padding: const EdgeInsets.all(16), controller: _scroll, children: [if (content.welcomeMessage != null) _bubble(content.welcomeMessage!, true), ..._lines.map((line) => _bubble(line.text, line.fromBot, line.reply)), if (_lines.isEmpty) Wrap(spacing: 8, children: content.suggestedTopics.map((topic) => ActionChip(label: Text(topic), onPressed: () => _send(topic))).toList())])), Padding(padding: const EdgeInsets.all(12), child: Row(children: [Expanded(child: TextField(controller: _input, textInputAction: TextInputAction.send, onSubmitted: _send, decoration: const InputDecoration(hintText: 'Type a message'))), IconButton(onPressed: _sending ? null : () => _send(_input.text), icon: const Icon(Icons.send))]))]); }))); 
  Widget _bubble(String text, bool fromBot, [ChatBuddyReply? reply]) => Align(alignment: fromBot ? Alignment.centerLeft : Alignment.centerRight, child: Container(margin: const EdgeInsets.only(bottom: 10), padding: const EdgeInsets.all(14), constraints: const BoxConstraints(maxWidth: 310), decoration: BoxDecoration(color: fromBot ? AppColors.softLavender : AppColors.softSage, borderRadius: BorderRadius.circular(16)), child: Column(crossAxisAlignment: CrossAxisAlignment.start, children: [Text(text), if (reply?.isSafety == true) const Padding(padding: EdgeInsets.only(top: 8), child: Text('If you may be in immediate danger, contact local emergency support or someone you trust.', style: TextStyle(fontWeight: FontWeight.w600))), if (reply != null) ...reply.followUpPrompts.map((p) => TextButton(onPressed: () => _send(p), child: Text(p))), if (reply != null) ...reply.links.where((l) => l.url != null).map((l) => TextButton(onPressed: () => launchUrl(Uri.parse(l.url!)), child: Text(l.label)))])));
}
