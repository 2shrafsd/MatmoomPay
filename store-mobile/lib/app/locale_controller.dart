import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:material_ui/material_ui.dart';

final localeControllerProvider = NotifierProvider<LocaleController, Locale>(
  LocaleController.new,
);

/// Arabic by default; English on request. Kept in memory for this demo.
class LocaleController extends Notifier<Locale> {
  static const Locale arabic = Locale('ar');
  static const Locale english = Locale('en');

  @override
  Locale build() => arabic;

  void toggle() =>
      state = state.languageCode == arabic.languageCode ? english : arabic;
}
