class ApiConfig {
  ApiConfig._();

  /// Android emulator default:
  /// http://10.0.2.2:8000/api
  ///
  /// Override for a physical device:
  /// flutter run --dart-define=API_BASE_URL=http://YOUR_PC_IP:8000/api
  static const String baseUrl = String.fromEnvironment(
    'API_BASE_URL',
    defaultValue: 'http://10.0.2.2:8000/api',
  );
}
