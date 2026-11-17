import 'package:matjar_app/core/network/loopback_url_rewriter.dart';

/// Which of Matjar's return pages the gateway sent the shopper to.
enum PaymentReturn { success, cancel }

/// Recognises the gateway's redirect back to Matjar inside the WebView.
///
/// A match only means "the hosted page is done": the app then asks Matjar
/// for the order status. The redirect itself is never treated as proof of
/// payment, because anyone can open a success URL.
class PaymentReturnMatcher {
  PaymentReturnMatcher({
    required String successUrlPrefix,
    required String cancelUrlPrefix,
    LoopbackUrlRewriter? urlRewriter,
  }) : _rewriter = urlRewriter,
       _successPrefix = _normalise(successUrlPrefix, urlRewriter),
       _cancelPrefix = _normalise(cancelUrlPrefix, urlRewriter);

  final LoopbackUrlRewriter? _rewriter;
  final String _successPrefix;
  final String _cancelPrefix;

  /// Returns the kind of return page [url] is, or null for any other page
  /// (the gateway's own pages, 3-D Secure / OTP steps, ...).
  PaymentReturn? match(String url) {
    final candidate = _normalise(url, _rewriter);
    final checks = [
      (_successPrefix, PaymentReturn.success),
      (_cancelPrefix, PaymentReturn.cancel),
    ];
    // When one prefix contains the other (`/return` and `/return?cancel=1`),
    // the more specific one must win.
    checks.sort((a, b) => b.$1.length.compareTo(a.$1.length));
    for (final (prefix, kind) in checks) {
      if (prefix.isNotEmpty && candidate.startsWith(prefix)) {
        return kind;
      }
    }
    return null;
  }

  /// Applies the same loopback rewrite the app used for the prefixes (the
  /// gateway redirects to the original `localhost` URL) and lower-cases the
  /// scheme and host, which are case-insensitive.
  static String _normalise(String url, LoopbackUrlRewriter? rewriter) {
    final rewritten = rewriter?.rewrite(url.trim()) ?? url.trim();
    final schemeEnd = rewritten.indexOf('://');
    if (schemeEnd < 0) {
      return rewritten;
    }
    final rest = rewritten.substring(schemeEnd + 3);
    final pathStart = rest.indexOf(RegExp('[/?#]'));
    final authority = pathStart < 0 ? rest : rest.substring(0, pathStart);
    final tail = pathStart < 0 ? '' : rest.substring(pathStart);
    final scheme = rewritten.substring(0, schemeEnd).toLowerCase();
    return '$scheme://${authority.toLowerCase()}$tail';
  }
}
