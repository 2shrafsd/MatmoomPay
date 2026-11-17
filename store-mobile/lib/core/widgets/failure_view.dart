import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/error/failure.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';
import 'package:matjar_app/core/widgets/primary_button.dart';
import 'package:matjar_app/core/widgets/state_message.dart';

/// Full-area error state with a retry button.
class FailureView extends StatelessWidget {
  const FailureView({required this.failure, required this.onRetry, super.key});

  final Failure failure;
  final VoidCallback onRetry;

  @override
  Widget build(BuildContext context) {
    final l10n = context.l10n;
    return StateMessage(
      icon: failure is NetworkFailure
          ? Icons.wifi_off_outlined
          : Icons.error_outline,
      iconColor: Theme.of(context).colorScheme.error,
      title: failure.localizedMessage(l10n),
      actions: [PrimaryButton(label: l10n.retry, onPressed: onRetry)],
    );
  }
}
