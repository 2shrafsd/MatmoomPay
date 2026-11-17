import 'package:material_ui/material_ui.dart';

/// Type scale on the bundled IBM Plex Sans Arabic (400/500/600/700): heavy,
/// tight headlines and calm body text. Arabic needs a little more leading
/// than Latin, so "tight" here is 1.2 rather than 1.0.
abstract final class AppTypography {
  static const String fontFamily = 'IBMPlexSansArabic';

  static TextTheme textTheme(Color onSurface) {
    TextStyle style(
      double size,
      FontWeight weight,
      double height, {
      double letterSpacing = 0,
    }) => TextStyle(
      fontFamily: fontFamily,
      fontSize: size,
      fontWeight: weight,
      height: height,
      letterSpacing: letterSpacing,
      color: onSurface,
    );

    return TextTheme(
      displaySmall: style(34, FontWeight.w700, 1.2),
      headlineSmall: style(24, FontWeight.w700, 1.25),
      titleLarge: style(20, FontWeight.w700, 1.3),
      titleMedium: style(16, FontWeight.w700, 1.35),
      titleSmall: style(14, FontWeight.w500, 1.45),
      bodyLarge: style(16, FontWeight.w400, 1.6),
      bodyMedium: style(14, FontWeight.w400, 1.6),
      bodySmall: style(12.5, FontWeight.w400, 1.55),
      labelLarge: style(15, FontWeight.w700, 1.3),
      labelMedium: style(13, FontWeight.w500, 1.4),
      labelSmall: style(11.5, FontWeight.w500, 1.4, letterSpacing: 0.3),
    );
  }

  /// The «متجر» wordmark in the app bar.
  static TextStyle wordmark(Color color) => TextStyle(
    fontFamily: fontFamily,
    fontSize: 20,
    fontWeight: FontWeight.w700,
    color: color,
  );
}
