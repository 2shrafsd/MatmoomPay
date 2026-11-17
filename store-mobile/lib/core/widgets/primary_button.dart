import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';

/// The lime pill action of a screen. Full width unless [expand] is false.
class PrimaryButton extends StatelessWidget {
  const PrimaryButton({
    required this.label,
    required this.onPressed,
    this.isBusy = false,
    this.icon,
    this.expand = true,
    super.key,
  });

  final String label;
  final VoidCallback? onPressed;

  /// Shows a spinner and ignores taps while a request runs.
  final bool isBusy;

  /// Optional trailing icon (e.g. an arrow that mirrors in RTL).
  final IconData? icon;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final child = isBusy
        ? SizedBox.square(
            dimension: AppSizes.iconMd,
            child: CircularProgressIndicator(
              strokeWidth: AppSizes.progressBarHeight,
              color: context.palette.mutedText,
            ),
          )
        : Text(label, textAlign: TextAlign.center);
    final button = icon == null || isBusy
        ? FilledButton(onPressed: isBusy ? null : onPressed, child: child)
        : FilledButton.icon(
            onPressed: onPressed,
            iconAlignment: IconAlignment.end,
            icon: Icon(icon, size: AppSizes.iconSm),
            label: child,
          );
    return expand ? SizedBox(width: double.infinity, child: button) : button;
  }
}
