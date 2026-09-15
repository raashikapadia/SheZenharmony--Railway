class GratitudeEntry {
  const GratitudeEntry({
    required this.id,
    required this.text,
    required this.symbol,
  });

  final int id;
  final String text;
  final String symbol;

  factory GratitudeEntry.fromJson(Map<String, dynamic> json) {
    return GratitudeEntry(
      id: json['id'] as int,
      text: json['text'] as String? ?? '',
      symbol: json['symbol'] as String? ?? '✨',
    );
  }
}
