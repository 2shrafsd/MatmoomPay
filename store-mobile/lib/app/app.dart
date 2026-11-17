import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/app/locale_controller.dart';
import 'package:matjar_app/app/router/app_router.dart';
import 'package:matjar_app/core/theme/app_theme.dart';
import 'package:matjar_app/l10n/generated/app_localizations.dart';

class MatjarApp extends ConsumerWidget {
  const MatjarApp({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return MaterialApp.router(
      onGenerateTitle: (context) => AppLocalizations.of(context).appTitle,
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light(),
      darkTheme: AppTheme.dark(),
      locale: ref.watch(localeControllerProvider),
      supportedLocales: AppLocalizations.supportedLocales,
      localizationsDelegates: const [
        AppLocalizations.delegate,
        ...GlobalMaterialLocalizations.delegates,
      ],
      routerConfig: ref.watch(appRouterProvider),
    );
  }
}
