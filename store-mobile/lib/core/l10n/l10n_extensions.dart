import 'package:intl/intl.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/error/failure.dart';
import 'package:matjar_app/l10n/generated/app_localizations.dart';

extension L10nContext on BuildContext {
  AppLocalizations get l10n => AppLocalizations.of(this);
}

extension AmountFormatting on AppLocalizations {
  /// Whole Sudanese pounds with thousands separators: `12,000 ج.س`.
  String sdg(int amount) =>
      amountSdg(NumberFormat.decimalPattern('en').format(amount));
}

extension FailureMessage on Failure {
  /// Copy shown to the shopper for this failure.
  String localizedMessage(AppLocalizations l10n) => switch (this) {
    NetworkFailure() => l10n.errorNetwork,
    GatewayUnavailableFailure() => l10n.errorGatewayUnavailable,
    ThrottledFailure() => l10n.errorThrottled,
    NotFoundFailure() => l10n.errorNotFound,
    ParsingFailure() => l10n.errorParsing,
    ValidationFailure() => l10n.errorValidation,
    ServerFailure(:final statusCode) => l10n.errorServer(
      '${statusCode ?? '-'}',
    ),
    UnknownFailure() => l10n.errorUnknown,
  };
}
