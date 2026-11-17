/// Every way a request to the Matjar backend can fail, as seen by the UI.
///
/// Repositories map transport and parsing errors to one of these, so widgets
/// never see a `DioException`. [message] is diagnostic text (often from the
/// server); widgets show localized copy chosen from the failure type.
sealed class Failure {
  const Failure(this.message);

  final String message;

  @override
  String toString() => '$runtimeType($message)';
}

/// No connection, DNS failure or timeout.
final class NetworkFailure extends Failure {
  const NetworkFailure(super.message);
}

/// The server answered with an unexpected status code.
final class ServerFailure extends Failure {
  const ServerFailure(super.message, {this.statusCode});

  final int? statusCode;
}

/// `422`: the request body did not pass the server's validation.
final class ValidationFailure extends Failure {
  const ValidationFailure(super.message, {this.fieldErrors = const {}});

  /// First error message per field, keyed by the API field name
  /// (`customer_name`, `customer_phone`, `items.0.quantity`, ...).
  final Map<String, String> fieldErrors;
}

/// `502`: Matjar could not reach the MatmoomPay gateway.
final class GatewayUnavailableFailure extends Failure {
  const GatewayUnavailableFailure(super.message);
}

/// `429`: too many requests.
final class ThrottledFailure extends Failure {
  const ThrottledFailure(super.message);
}

/// `404`: unknown resource, or an order token that does not match.
final class NotFoundFailure extends Failure {
  const NotFoundFailure(super.message);
}

/// The response arrived but did not match the API contract.
final class ParsingFailure extends Failure {
  const ParsingFailure(super.message);
}

final class UnknownFailure extends Failure {
  const UnknownFailure(super.message);
}
