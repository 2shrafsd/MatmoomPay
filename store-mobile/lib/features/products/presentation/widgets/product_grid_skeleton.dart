import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_radius.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/features/products/presentation/widgets/product_grid.dart';

/// Loading placeholder with the same layout as the product grid.
class SliverProductGridSkeleton extends StatefulWidget {
  const SliverProductGridSkeleton({super.key});

  static const int placeholderCount = 6;

  @override
  State<SliverProductGridSkeleton> createState() =>
      _SliverProductGridSkeletonState();
}

class _SliverProductGridSkeletonState extends State<SliverProductGridSkeleton>
    with SingleTickerProviderStateMixin {
  late final AnimationController _pulse = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 1100),
    lowerBound: 0.5,
  );

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    if (MediaQuery.disableAnimationsOf(context)) {
      _pulse.value = 1;
    } else if (!_pulse.isAnimating) {
      _pulse.repeat(reverse: true);
    }
  }

  @override
  void dispose() {
    _pulse.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return SliverProductGrid(
      itemCount: SliverProductGridSkeleton.placeholderCount,
      itemBuilder: (_, _) =>
          FadeTransition(opacity: _pulse, child: const _SkeletonTile()),
    );
  }
}

class _SkeletonTile extends StatelessWidget {
  const _SkeletonTile();

  @override
  Widget build(BuildContext context) {
    final fill = context.palette.skeleton;
    Widget bar(double widthFactor) => FractionallySizedBox(
      alignment: AlignmentDirectional.centerStart,
      widthFactor: widthFactor,
      child: Container(
        height: AppSizes.skeletonLineHeight,
        decoration: BoxDecoration(
          color: fill,
          borderRadius: BorderRadius.circular(AppRadius.pill),
        ),
      ),
    );

    return ExcludeSemantics(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: [
          AspectRatio(
            aspectRatio: 1,
            child: DecoratedBox(
              decoration: BoxDecoration(
                color: fill,
                borderRadius: BorderRadius.circular(AppRadius.tile),
              ),
            ),
          ),
          const SizedBox(height: AppSpacing.md),
          bar(0.6),
          const SizedBox(height: AppSpacing.sm),
          bar(0.35),
        ],
      ),
    );
  }
}
