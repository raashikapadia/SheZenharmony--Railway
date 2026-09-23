class ChatBuddyContent {
  const ChatBuddyContent({required this.available, this.welcomeMessage, this.suggestedTopics = const []});
  final bool available; final String? welcomeMessage; final List<String> suggestedTopics;
  factory ChatBuddyContent.fromJson(Map<String, dynamic> json) => ChatBuddyContent(available: json['available'] == true, welcomeMessage: json['welcome_message'] as String?, suggestedTopics: (json['suggested_topics'] as List? ?? const []).map((e) => e.toString()).toList());
}

class ChatBuddyLink { const ChatBuddyLink({required this.label, this.url, this.type}); final String label; final String? url; final String? type; factory ChatBuddyLink.fromJson(Map<String, dynamic> json) => ChatBuddyLink(label: json['label']?.toString() ?? '', url: json['url']?.toString(), type: json['type']?.toString()); }
class ChatBuddyReply {
  const ChatBuddyReply({required this.available, this.message, this.isSafety = false, this.isFallback = false, this.links = const [], this.followUpPrompts = const []});
  final bool available; final String? message; final bool isSafety; final bool isFallback; final List<ChatBuddyLink> links; final List<String> followUpPrompts;
  factory ChatBuddyReply.fromJson(Map<String, dynamic> json) => ChatBuddyReply(available: json['available'] == true, message: json['message'] as String?, isSafety: json['is_safety'] == true, isFallback: json['is_fallback'] == true, links: (json['links'] as List? ?? const []).whereType<Map>().map((e) => ChatBuddyLink.fromJson(Map<String, dynamic>.from(e))).toList(), followUpPrompts: (json['follow_up_prompts'] as List? ?? const []).map((e) => e.toString()).toList());
}
