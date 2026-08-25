import 'package:flutter/foundation.dart';

class ApiConfig {
  ApiConfig._();

  /// Resolution order:
  /// 1. Explicit override: `flutter run --dart-define=API_BASE_URL=http://YOUR_PC_IP:8000/api`
  ///    (required for physical devices — iOS, or Android over Wi-Fi — since
  ///    they can't reach "localhost" on the development machine).
  /// 2. Android emulator special alias `10.0.2.2`, which maps to the host
  ///    machine's localhost from inside the emulator only.
  /// 3. `127.0.0.1`, correct for web (Chrome), Windows desktop, and the iOS
  ///    simulator, all of which share the host machine's network stack.
  static String get baseUrl {
    const override = String.fromEnvironment('API_BASE_URL');
    if (override.isNotEmpty) return override;

    if (!kIsWeb && defaultTargetPlatform == TargetPlatform.android) {
      return 'http://10.0.2.2:8000/api';
    }

    return 'http://127.0.0.1:8000/api';
  }
}
