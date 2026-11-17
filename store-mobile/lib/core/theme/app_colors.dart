import 'dart:ui';

/// Raw palette: warm neutrals plus one lime accent. Widgets never use these
/// directly; they read `ColorScheme` or [AppPalette] from the theme.
abstract final class AppColors {
  // Light
  static const Color page = Color(0xFFFAFAF9);
  static const Color panel = Color(0xFFF2F1EF);
  static const Color white = Color(0xFFFFFFFF);
  static const Color ink = Color(0xFF111111);
  static const Color grey = Color(0xFF6B6B6B); // 4.9:1 on panel
  static const Color hairline = Color(0xFFE4E2DE);
  static const Color outline = Color(0xFFCFCCC6);
  static const Color errorLight = Color(0xFFB3261E);
  static const Color errorContainerLight = Color(0xFFF9E3E1);

  // Accent (both themes)
  static const Color lime = Color(0xFFE3FA5A);
  static const Color limeDim = Color(0xFFD4EB4B);

  // Dark
  static const Color night = Color(0xFF111111);
  static const Color nightPanel = Color(0xFF1C1C1C);
  static const Color nightRaised = Color(0xFF262626);
  static const Color paper = Color(0xFFF5F5F4);
  static const Color greyDark = Color(0xFFA3A3A3);
  static const Color hairlineDark = Color(0xFF2C2C2C);
  static const Color outlineDark = Color(0xFF444444);
  static const Color errorDark = Color(0xFFF2B8B5);
  static const Color errorContainerDark = Color(0xFF3A1513);
}
