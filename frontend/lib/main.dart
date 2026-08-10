import 'package:flutter/material.dart';

import 'features/home/presentation/home_screen.dart';

void main() {
  runApp(const SheZenApp());
}

class SheZenApp extends StatelessWidget {
  const SheZenApp({super.key});

  @override
  Widget build(BuildContext context) {
    final colorScheme = ColorScheme.fromSeed(
      seedColor: const Color(0xFF6E5A8A),
      brightness: Brightness.light,
    );

    return MaterialApp(
      title: 'SheZen Harmony',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        useMaterial3: true,
        colorScheme: colorScheme,
        scaffoldBackgroundColor: const Color(0xFFF8F5FA),
        cardTheme: const CardThemeData(
          elevation: 0,
          margin: EdgeInsets.zero,
        ),
      ),
      home: const HomeScreen(),
    );
  }
}
