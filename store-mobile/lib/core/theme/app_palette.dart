import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/theme/app_colors.dart';

/// Colours the `ColorScheme` has no slot for.
@immutable
class AppPalette extends ThemeExtension<AppPalette> {
  const AppPalette({
    required this.mutedText,
    required this.panel,
    required this.panelRaised,
    required this.hairline,
    required this.skeleton,
    required this.accent,
    required this.onAccent,
  });

  static const AppPalette light = AppPalette(
    mutedText: AppColors.grey,
    panel: AppColors.panel,
    panelRaised: AppColors.white,
    hairline: AppColors.hairline,
    skeleton: AppColors.panel,
    accent: AppColors.lime,
    onAccent: AppColors.ink,
  );

  static const AppPalette dark = AppPalette(
    mutedText: AppColors.greyDark,
    panel: AppColors.nightPanel,
    panelRaised: AppColors.nightRaised,
    hairline: AppColors.hairlineDark,
    skeleton: AppColors.nightPanel,
    accent: AppColors.lime,
    onAccent: AppColors.ink,
  );

  /// Secondary text: sub-headings, prices, captions.
  final Color mutedText;

  /// Rounded panels and product photo tiles.
  final Color panel;

  /// Fields and cards that sit on a [panel].
  final Color panelRaised;

  /// Thin dividers.
  final Color hairline;
  final Color skeleton;

  /// The single lime accent: primary pills, "+" buttons, badge, icon circles.
  final Color accent;
  final Color onAccent;

  @override
  AppPalette copyWith({
    Color? mutedText,
    Color? panel,
    Color? panelRaised,
    Color? hairline,
    Color? skeleton,
    Color? accent,
    Color? onAccent,
  }) {
    return AppPalette(
      mutedText: mutedText ?? this.mutedText,
      panel: panel ?? this.panel,
      panelRaised: panelRaised ?? this.panelRaised,
      hairline: hairline ?? this.hairline,
      skeleton: skeleton ?? this.skeleton,
      accent: accent ?? this.accent,
      onAccent: onAccent ?? this.onAccent,
    );
  }

  @override
  AppPalette lerp(AppPalette? other, double t) {
    if (other == null) {
      return this;
    }
    return AppPalette(
      mutedText: Color.lerp(mutedText, other.mutedText, t)!,
      panel: Color.lerp(panel, other.panel, t)!,
      panelRaised: Color.lerp(panelRaised, other.panelRaised, t)!,
      hairline: Color.lerp(hairline, other.hairline, t)!,
      skeleton: Color.lerp(skeleton, other.skeleton, t)!,
      accent: Color.lerp(accent, other.accent, t)!,
      onAccent: Color.lerp(onAccent, other.onAccent, t)!,
    );
  }
}

extension AppPaletteContext on BuildContext {
  AppPalette get palette => Theme.of(this).extension<AppPalette>()!;
}
