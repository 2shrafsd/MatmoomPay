import 'package:matjar_app/l10n/generated/app_localizations.dart';

/// Shopper-facing name of an API category. Unknown values are shown as sent,
/// so a new category on the server still appears.
String categoryLabel(AppLocalizations l10n, String category) =>
    switch (category) {
      'headphones' => l10n.categoryHeadphones,
      'earbuds' => l10n.categoryEarbuds,
      'speakers' => l10n.categorySpeakers,
      'accessories' => l10n.categoryAccessories,
      _ => category,
    };
