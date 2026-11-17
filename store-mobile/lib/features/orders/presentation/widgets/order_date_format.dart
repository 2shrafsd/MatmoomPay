import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';

/// Formats a server timestamp in the shopper's locale and time zone. Digits
/// stay Western (0-9) in Arabic too, matching how prices are shown.
String formatOrderDate(BuildContext context, DateTime value) {
  final locale = Localizations.localeOf(context).toLanguageTag();
  final format = DateFormat.yMMMd(locale).add_jm()..useNativeDigits = false;
  return format.format(value.toLocal());
}
