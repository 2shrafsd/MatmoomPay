import 'package:flutter_test/flutter_test.dart';
import 'package:matjar_app/core/network/loopback_url_rewriter.dart';
import 'package:matjar_app/features/payment/domain/payment_return_matcher.dart';

void main() {
  const rewriter = LoopbackUrlRewriter(targetHost: '10.0.2.2');

  PaymentReturnMatcher matcher({
    String success = 'http://10.0.2.2:8001/orders/MJ-1/return?result=success',
    String cancel = 'http://10.0.2.2:8001/orders/MJ-1/return?result=cancel',
  }) => PaymentReturnMatcher(
    successUrlPrefix: success,
    cancelUrlPrefix: cancel,
    urlRewriter: rewriter,
  );

  test('matches the success and cancel URLs including extra parameters', () {
    final m = matcher();
    expect(
      m.match(
        'http://10.0.2.2:8001/orders/MJ-1/return?result=success&session=cs_1',
      ),
      PaymentReturn.success,
    );
    expect(
      m.match('http://10.0.2.2:8001/orders/MJ-1/return?result=cancel'),
      PaymentReturn.cancel,
    );
  });

  test('ignores the gateway pages, OTP step and other orders', () {
    final m = matcher();
    expect(m.match('http://10.0.2.2:8000/checkout/cs_1'), isNull);
    expect(m.match('http://10.0.2.2:8000/checkout/cs_1/otp'), isNull);
    expect(
      m.match('http://10.0.2.2:8001/orders/MJ-2/return?result=success'),
      isNull,
    );
    expect(m.match('about:blank'), isNull);
  });

  test('matches the original localhost redirect after rewriting it', () {
    // The gateway redirects to the URL Matjar registered (localhost), while
    // the app holds the rewritten prefix.
    expect(
      matcher().match(
        'http://localhost:8001/orders/MJ-1/return?result=success',
      ),
      PaymentReturn.success,
    );
  });

  test('scheme and host are compared case-insensitively, the path is not', () {
    final m = matcher();
    expect(
      m.match('HTTP://10.0.2.2:8001/orders/MJ-1/return?result=success'),
      PaymentReturn.success,
    );
    expect(
      m.match('http://10.0.2.2:8001/ORDERS/MJ-1/return?result=success'),
      isNull,
    );
  });

  test('the more specific prefix wins when one contains the other', () {
    final m = matcher(
      success: 'http://10.0.2.2:8001/orders/MJ-1/return',
      cancel: 'http://10.0.2.2:8001/orders/MJ-1/return?cancelled=1',
    );
    expect(
      m.match('http://10.0.2.2:8001/orders/MJ-1/return?cancelled=1'),
      PaymentReturn.cancel,
    );
    expect(
      m.match('http://10.0.2.2:8001/orders/MJ-1/return?session=cs_1'),
      PaymentReturn.success,
    );
  });

  test('an empty prefix never matches everything', () {
    final m = PaymentReturnMatcher(successUrlPrefix: '', cancelUrlPrefix: '');
    expect(m.match('http://10.0.2.2:8000/checkout/cs_1'), isNull);
  });
}
