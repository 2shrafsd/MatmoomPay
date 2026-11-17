import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';

/// Icon on a lime circle.
class AccentCircleIcon extends StatelessWidget {
  const AccentCircleIcon(
    this.icon, {
    this.diameter = AppSizes.accentCircle,
    this.background,
    this.foreground,
    super.key,
  });

  final IconData icon;
  final double diameter;

  /// Defaults to the lime accent.
  final Color? background;
  final Color? foreground;

  @override
  Widget build(BuildContext context) {
    final palette = context.palette;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: background ?? palette.accent,
        shape: BoxShape.circle,
      ),
      child: SizedBox.square(
        dimension: diameter,
        child: Icon(
          icon,
          size: diameter / 2,
          color: foreground ?? palette.onAccent,
        ),
      ),
    );
  }
}
