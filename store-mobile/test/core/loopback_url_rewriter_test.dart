import 'package:flutter/foundation.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:matjar_app/core/config/app_config.dart';
import 'package:matjar_app/core/network/loopback_url_rewriter.dart';

void main() {
  group('LoopbackUrlRewriter on the Android emulator (10.0.2.2)', () {
    const rewriter = LoopbackUrlRewriter(targetHost: '10.0.2.2');

    test('rewrites localhost and keeps scheme, port, path and query', () {
      expect(
        rewriter.rewrite('http://localhost:8000/checkout/cs_1?lang=ar#pay'),
        'http://10.0.2.2:8000/checkout/cs_1?lang=ar#pay',
      );
    });

    test('rewrites 127.0.0.1 and upper-case LOCALHOST', () {
      expect(
        rewriter.rewrite('http://127.0.0.1:8001/orders/MJ-1/return'),
        'http://10.0.2.2:8001/orders/MJ-1/return',
      );
      expect(
        rewriter.rewrite('http://LOCALHOST:8001/x'),
        'http://10.0.2.2:8001/x',
      );
    });

    test('leaves other hosts and non-URLs alone', () {
      expect(
        rewriter.rewrite('https://pay.example.com/checkout/1'),
        'https://pay.example.com/checkout/1',
      );
      expect(rewriter.rewrite('not a url'), 'not a url');
      expect(rewriter.rewriteOrNull(null), isNull);
    });
  });

  test('does nothing when the API itself is on localhost (iOS simulator)', () {
    const rewriter = LoopbackUrlRewriter(targetHost: 'localhost');
    expect(
      rewriter.rewrite('http://localhost:8000/checkout/1'),
      'http://localhost:8000/checkout/1',
    );
  });

  test('targets a LAN IP when the API base URL uses one', () {
    const rewriter = LoopbackUrlRewriter(targetHost: '192.168.1.20');
    expect(
      rewriter.rewrite('http://localhost:8000/checkout/1'),
      'http://192.168.1.20:8000/checkout/1',
    );
  });

  group('AppConfig default base URL', () {
    test('uses 10.0.2.2 on Android and localhost elsewhere', () {
      expect(
        AppConfig.fromEnvironment(platform: TargetPlatform.android).apiBaseUrl,
        'http://10.0.2.2:8001',
      );
      expect(
        AppConfig.fromEnvironment(platform: TargetPlatform.iOS).apiBaseUrl,
        'http://localhost:8001',
      );
    });
  });
}
