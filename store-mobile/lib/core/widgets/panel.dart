import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_radius.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';

/// Rounded warm-grey panel, the basic surface of every screen.
class Panel extends StatelessWidget {
  const Panel({
    required this.child,
    this.padding = const EdgeInsetsDirectional.all(AppSpacing.xl),
    this.radius = AppRadius.panel,
    super.key,
  });

  final Widget child;
  final EdgeInsetsGeometry padding;
  final double radius;

  @override
  Widget build(BuildContext context) {
    return DecoratedBox(
      decoration: BoxDecoration(
        color: context.palette.panel,
        borderRadius: BorderRadius.circular(radius),
      ),
      child: Padding(padding: padding, child: child),
    );
  }
}
