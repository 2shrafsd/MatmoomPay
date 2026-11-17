import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/app/locale_controller.dart';
import 'package:matjar_app/app/router/routes.dart';
import 'package:matjar_app/core/l10n/l10n_extensions.dart';

class LanguageToggleButton extends ConsumerWidget {
  const LanguageToggleButton({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final l10n = context.l10n;
    return Tooltip(
      message: l10n.changeLanguageTooltip,
      child: TextButton(
        onPressed: ref.read(localeControllerProvider.notifier).toggle,
        child: Text(l10n.languageToggle),
      ),
    );
  }
}

class MyOrdersButton extends StatelessWidget {
  const MyOrdersButton({super.key});

  @override
  Widget build(BuildContext context) {
    return IconButton(
      tooltip: context.l10n.myOrdersTitle,
      onPressed: () => context.push(Routes.myOrders),
      icon: const Icon(Icons.receipt_long_outlined),
    );
  }
}
