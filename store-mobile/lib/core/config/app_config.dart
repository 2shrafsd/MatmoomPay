import 'package:flutter/foundation.dart';

/// Runtime configuration resolved once at start-up.
@immutable
class AppConfig {
  const AppConfig({required this.apiBaseUrl});

  /// Builds the config from `--dart-define=API_BASE_URL=...`.
  ///
  /// Without the define, the default points at Matjar on the developer's
  /// machine: `10.0.2.2` is how the Android emulator reaches the host, while
  /// the iOS simulator shares the host's `localhost`.
  factory AppConfig.fromEnvironment({TargetPlatform? platform}) {
    const defined = String.fromEnvironment('API_BASE_URL');
    if (defined.isNotEmpty) {
      return AppConfig(apiBaseUrl: _trimTrailingSlash(defined));
    }
    final target = platform ?? defaultTargetPlatform;
    return AppConfig(
      apiBaseUrl: target == TargetPlatform.android
          ? androidEmulatorBaseUrl
          : localBaseUrl,
    );
  }

  static const String localBaseUrl = 'http://localhost:8001';
  static const String androidEmulatorBaseUrl = 'http://10.0.2.2:8001';

  final String apiBaseUrl;

  /// Host of [apiBaseUrl]; loopback URLs from the server are rewritten to it.
  String get apiHost => Uri.parse(apiBaseUrl).host;

  static String _trimTrailingSlash(String value) =>
      value.endsWith('/') ? value.substring(0, value.length - 1) : value;
}
