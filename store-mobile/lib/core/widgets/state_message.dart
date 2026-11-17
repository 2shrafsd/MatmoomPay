import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/theme/app_sizes.dart';
import 'package:matjar_app/core/theme/app_spacing.dart';
import 'package:matjar_app/core/widgets/accent_circle_icon.dart';

/// Centered icon + title + body + optional action, used for empty, error and
/// result states.
class StateMessage extends StatelessWidget {
  const StateMessage({
    required this.icon,
    required this.title,
    this.body,
    this.iconColor,
    this.iconBackground,
    this.actions = const [],
    super.key,
  });

  final IconData icon;
  final String title;
  final String? body;
  final Color? iconColor;

  /// Circle behind the icon; the panel grey by default.
  final Color? iconBackground;
  final List<Widget> actions;

  @override
  Widget build(BuildContext context) {
    final textTheme = Theme.of(context).textTheme;
    return Center(
      child: ConstrainedBox(
        constraints: const BoxConstraints(maxWidth: AppSpacing.maxFormWidth),
        child: Padding(
          padding: const EdgeInsetsDirectional.all(AppSpacing.xxl),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              Center(
                child: AccentCircleIcon(
                  icon,
                  diameter: AppSizes.statusIcon,
                  background: iconBackground ?? context.palette.panel,
                  foreground: iconColor ?? context.palette.mutedText,
                ),
              ),
              const SizedBox(height: AppSpacing.lg),
              Text(
                title,
                style: textTheme.titleLarge,
                textAlign: TextAlign.center,
              ),
              if (body case final body?) ...[
                const SizedBox(height: AppSpacing.sm),
                Text(
                  body,
                  style: textTheme.bodyMedium?.copyWith(
                    color: context.palette.mutedText,
                  ),
                  textAlign: TextAlign.center,
                ),
              ],
              if (actions.isNotEmpty) ...[
                const SizedBox(height: AppSpacing.xxxl),
                for (final (index, action) in actions.indexed) ...[
                  if (index > 0) const SizedBox(height: AppSpacing.sm),
                  SizedBox(width: double.infinity, child: action),
                ],
              ],
            ],
          ),
        ),
      ),
    );
  }
}
