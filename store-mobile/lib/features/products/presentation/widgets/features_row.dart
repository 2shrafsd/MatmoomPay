import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/widgets/accent_circle_icon.dart';
import 'package:matjar_app/core/widgets/eyebrow.dart';
import 'package:matjar_app/core/widgets/panel.dart';

/// «المزايا»: three static feature cards that scroll sideways.
class FeaturesRow extends StatelessWidget {
  const FeaturesRow({super.key});

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    final features = [
      (
        Icons.battery_charging_full,
        l10n.featureBatteryTitle,
        l10n.featureBatteryBody,
      ),
      (Icons.mic_none, l10n.featureMicTitle, l10n.featureMicBody),
      (Icons.spa_outlined, l10n.featureComfortTitle, l10n.featureComfortBody),
    ];
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Padding(
          padding: const EdgeInsetsDirectional.symmetric(
            horizontal: AppSpacing.page,
          ),
          child: Eyebrow(l10n.featuresEyebrow),
        ),
        const SizedBox(height: AppSpacing.md),
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          padding: const EdgeInsetsDirectional.symmetric(
            horizontal: AppSpacing.page,
          ),
          child: IntrinsicHeight(
            child: Row(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                for (final (index, (icon, title, body))
                    in features.indexed) ...[
                  if (index > 0) const SizedBox(width: AppSpacing.md),
                  SizedBox(
                    width: AppSizes.featureCardWidth,
                    child: _FeatureCard(icon: icon, title: title, body: body),
                  ),
                ],
              ],
            ),
          ),
        ),
      ],
    );
  }
}

class _FeatureCard extends StatelessWidget {
  const _FeatureCard({
    required this.icon,
    required this.title,
    required this.body,
  });

  final IconData icon;
  final String title;
  final String body;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Panel(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          AccentCircleIcon(icon),
          const SizedBox(height: AppSpacing.lg),
          Text(title, style: textTheme.titleMedium),
          const SizedBox(height: AppSpacing.sm),
          Text(
            body,
            style: textTheme.bodySmall?.copyWith(
              color: context.palette.mutedText,
            ),
          ),
        ],
      ),
    );
  }
}
