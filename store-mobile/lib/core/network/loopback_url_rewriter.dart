/// Rewrites `localhost` / `127.0.0.1` URLs sent by the server so the device
/// can open them.
///
/// Matjar and the MatmoomPay gateway run on the developer's machine and build
/// absolute URLs with `localhost`. Inside the Android emulator `localhost` is
/// the emulator itself, so those URLs must point at `10.0.2.2` instead. The
/// target is the host the app already uses for the API, which also makes a
/// LAN address (`--dart-define=API_BASE_URL=http://192.168.1.20:8001`) work on
/// a physical phone. When the API host is itself loopback (iOS simulator)
/// nothing changes.
class LoopbackUrlRewriter {
  const LoopbackUrlRewriter({required this.targetHost});

  static const Set<String> _loopbackHosts = {'localhost', '127.0.0.1', '::1'};

  final String targetHost;

  bool get _isActive => !_loopbackHosts.contains(targetHost.toLowerCase());

  /// Returns [url] with a loopback host replaced by [targetHost]; the scheme,
  /// port, path, query and fragment are kept. Other URLs come back unchanged.
  String rewrite(String url) {
    if (!_isActive) {
      return url;
    }
    final uri = Uri.tryParse(url);
    if (uri == null || !uri.hasAuthority) {
      return url;
    }
    if (!_loopbackHosts.contains(uri.host.toLowerCase())) {
      return url;
    }
    return uri.replace(host: targetHost).toString();
  }

  /// Like [rewrite] for optional values.
  String? rewriteOrNull(String? url) => url == null ? null : rewrite(url);
}
