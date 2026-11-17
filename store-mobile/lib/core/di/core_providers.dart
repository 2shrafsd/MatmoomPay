import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:matjar_app/core/config/app_config.dart';
import 'package:matjar_app/core/network/dio_client.dart';
import 'package:matjar_app/core/network/loopback_url_rewriter.dart';
import 'package:matjar_app/core/storage/key_value_store.dart';

/// Shared infrastructure. Riverpod is the DI container: tests override these
/// providers instead of touching globals.
final appConfigProvider = Provider<AppConfig>(
  (ref) => AppConfig.fromEnvironment(),
);

final dioProvider = Provider<Dio>((ref) {
  final dio = createDio(ref.watch(appConfigProvider));
  ref.onDispose(dio.close);
  return dio;
});

final loopbackUrlRewriterProvider = Provider<LoopbackUrlRewriter>(
  (ref) =>
      LoopbackUrlRewriter(targetHost: ref.watch(appConfigProvider).apiHost),
);

final keyValueStoreProvider = Provider<KeyValueStore>(
  (ref) => SecureKeyValueStore(),
);
