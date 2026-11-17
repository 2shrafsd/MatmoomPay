import 'dart:ui';

/// Parses `#RGB`, `#RRGGBB` or `#AARRGGBB` coming from the API.
/// Returns null for anything else so callers can fall back to a theme colour.
Color? parseHexColor(String? value) {
  if (value == null) {
    return null;
  }
  var hex = value.trim().replaceFirst('#', '');
  if (hex.length == 3) {
    hex = hex.split('').map((c) => '$c$c').join();
  }
  if (hex.length == 6) {
    hex = 'FF$hex';
  }
  if (hex.length != 8) {
    return null;
  }
  final parsed = int.tryParse(hex, radix: 16);
  return parsed == null ? null : Color(parsed);
}
