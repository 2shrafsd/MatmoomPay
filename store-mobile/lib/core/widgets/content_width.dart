import 'package:material_ui/material_ui.dart';

/// Centers [child] and caps its width so content does not stretch edge to
/// edge on tablets.
class ContentWidth extends StatelessWidget {
  const ContentWidth({required this.maxWidth, required this.child, super.key});

  final double maxWidth;
  final Widget child;

  @override
  Widget build(BuildContext context) {
    return Align(
      alignment: AlignmentDirectional.topCenter,
      child: ConstrainedBox(
        constraints: BoxConstraints(maxWidth: maxWidth),
        child: child,
      ),
    );
  }
}

/// Sliver version of [ContentWidth]: pads the sliver horizontally so its
/// content is at most [maxWidth] wide and centered.
class SliverContentWidth extends StatelessWidget {
  const SliverContentWidth({
    required this.maxWidth,
    required this.sliver,
    super.key,
  });

  final double maxWidth;
  final Widget sliver;

  @override
  Widget build(BuildContext context) {
    return SliverLayoutBuilder(
      builder: (context, constraints) {
        final extra = constraints.crossAxisExtent - maxWidth;
        final inset = extra > 0 ? extra / 2 : 0.0;
        return SliverPadding(
          padding: EdgeInsetsDirectional.symmetric(horizontal: inset),
          sliver: sliver,
        );
      },
    );
  }
}
