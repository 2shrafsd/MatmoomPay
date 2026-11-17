import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/theme/app_colors.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_radius.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/theme/app_typography.dart';

/// Warm neutral themes with one lime accent. Dark mode keeps the same lime
/// on near-black panels. No elevation or heavy shadows anywhere.
abstract final class AppTheme {
  static ThemeData light() => _build(
    palette: AppPalette.light,
    scheme: const ColorScheme(
      brightness: Brightness.light,
      primary: AppColors.lime,
      onPrimary: AppColors.ink,
      secondary: AppColors.ink,
      onSecondary: AppColors.white,
      surface: AppColors.page,
      onSurface: AppColors.ink,
      onSurfaceVariant: AppColors.grey,
      surfaceContainerLowest: AppColors.white,
      surfaceContainerLow: AppColors.page,
      surfaceContainer: AppColors.panel,
      surfaceContainerHigh: AppColors.panel,
      surfaceContainerHighest: AppColors.hairline,
      outline: AppColors.outline,
      outlineVariant: AppColors.hairline,
      error: AppColors.errorLight,
      onError: AppColors.white,
      errorContainer: AppColors.errorContainerLight,
      onErrorContainer: AppColors.errorLight,
      inverseSurface: AppColors.ink,
      onInverseSurface: AppColors.white,
    ),
  );

  static ThemeData dark() => _build(
    palette: AppPalette.dark,
    scheme: const ColorScheme(
      brightness: Brightness.dark,
      primary: AppColors.lime,
      onPrimary: AppColors.ink,
      secondary: AppColors.paper,
      onSecondary: AppColors.night,
      surface: AppColors.night,
      onSurface: AppColors.paper,
      onSurfaceVariant: AppColors.greyDark,
      surfaceContainerLowest: AppColors.night,
      surfaceContainerLow: AppColors.night,
      surfaceContainer: AppColors.nightPanel,
      surfaceContainerHigh: AppColors.nightPanel,
      surfaceContainerHighest: AppColors.nightRaised,
      outline: AppColors.outlineDark,
      outlineVariant: AppColors.hairlineDark,
      error: AppColors.errorDark,
      onError: AppColors.night,
      errorContainer: AppColors.errorContainerDark,
      onErrorContainer: AppColors.errorDark,
      inverseSurface: AppColors.paper,
      onInverseSurface: AppColors.night,
    ),
  );

  static ThemeData _build({
    required ColorScheme scheme,
    required AppPalette palette,
  }) {
    final textTheme = AppTypography.textTheme(scheme.onSurface);
    const pill = StadiumBorder();
    const buttonPadding = EdgeInsetsDirectional.symmetric(
      horizontal: AppSpacing.xxl,
      vertical: AppSpacing.md,
    );
    const buttonSize = Size(AppSizes.minTapTarget, AppSizes.buttonHeight);
    final inputRadius = BorderRadius.circular(AppRadius.input);

    return ThemeData(
      useMaterial3: true,
      brightness: scheme.brightness,
      colorScheme: scheme,
      fontFamily: AppTypography.fontFamily,
      textTheme: textTheme,
      scaffoldBackgroundColor: scheme.surface,
      canvasColor: scheme.surface,
      extensions: [palette],
      iconTheme: IconThemeData(color: scheme.onSurface, size: AppSizes.iconMd),
      appBarTheme: AppBarTheme(
        backgroundColor: scheme.surface,
        foregroundColor: scheme.onSurface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        scrolledUnderElevation: 0,
        centerTitle: false,
        titleTextStyle: textTheme.titleMedium,
      ),
      cardTheme: CardThemeData(
        color: palette.panel,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        margin: EdgeInsets.zero,
        clipBehavior: Clip.antiAlias,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.panel),
        ),
      ),
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: palette.accent,
          foregroundColor: palette.onAccent,
          disabledBackgroundColor: palette.panel,
          disabledForegroundColor: palette.mutedText,
          elevation: 0,
          shape: pill,
          padding: buttonPadding,
          minimumSize: buttonSize,
          textStyle: textTheme.labelLarge,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: scheme.onSurface,
          shape: pill,
          padding: buttonPadding,
          minimumSize: buttonSize,
          side: BorderSide(color: scheme.outline),
          textStyle: textTheme.labelLarge,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: scheme.onSurface,
          shape: pill,
          minimumSize: const Size(AppSizes.minTapTarget, AppSizes.minTapTarget),
          textStyle: textTheme.labelMedium,
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          foregroundColor: scheme.onSurface,
          minimumSize: const Size.square(AppSizes.minTapTarget),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: palette.panelRaised,
        contentPadding: const EdgeInsetsDirectional.symmetric(
          horizontal: AppSpacing.lg,
          vertical: AppSpacing.md,
        ),
        labelStyle: textTheme.bodyMedium?.copyWith(color: palette.mutedText),
        hintStyle: textTheme.bodyMedium?.copyWith(color: palette.mutedText),
        border: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide.none,
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: scheme.onSurface),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: scheme.error),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: inputRadius,
          borderSide: BorderSide(color: scheme.error),
        ),
        floatingLabelStyle: textTheme.bodyMedium,
      ),
      bottomSheetTheme: BottomSheetThemeData(
        backgroundColor: scheme.surface,
        surfaceTintColor: Colors.transparent,
        elevation: 0,
        modalElevation: 0,
        showDragHandle: true,
        dragHandleColor: scheme.outline,
        shape: const RoundedRectangleBorder(
          borderRadius: BorderRadiusDirectional.vertical(
            top: Radius.circular(AppRadius.panel),
          ),
        ),
      ),
      snackBarTheme: SnackBarThemeData(
        behavior: SnackBarBehavior.floating,
        elevation: 0,
        backgroundColor: scheme.inverseSurface,
        contentTextStyle: textTheme.bodyMedium?.copyWith(
          color: scheme.onInverseSurface,
        ),
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(AppRadius.input),
        ),
      ),
      badgeTheme: BadgeThemeData(
        backgroundColor: palette.accent,
        textColor: palette.onAccent,
        textStyle: textTheme.labelSmall,
      ),
      dividerTheme: DividerThemeData(
        color: palette.hairline,
        thickness: AppSizes.hairline,
        space: AppSpacing.xxl,
      ),
      listTileTheme: ListTileThemeData(
        contentPadding: const EdgeInsetsDirectional.symmetric(
          horizontal: AppSpacing.xxl,
        ),
        titleTextStyle: textTheme.bodyLarge,
        iconColor: scheme.onSurface,
        selectedColor: scheme.onSurface,
      ),
      progressIndicatorTheme: ProgressIndicatorThemeData(
        color: scheme.onSurface,
        linearTrackColor: palette.hairline,
        circularTrackColor: Colors.transparent,
      ),
      textSelectionTheme: TextSelectionThemeData(
        cursorColor: scheme.onSurface,
        selectionColor: palette.accent,
        selectionHandleColor: scheme.onSurface,
      ),
    );
  }
}
