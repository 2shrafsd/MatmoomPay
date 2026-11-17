import 'package:dio/dio.dart';
import 'package:matjar_app/core/error/failure.dart';

/// Turns anything thrown while calling the API into a [Failure].
Failure mapErrorToFailure(Object error) {
  return switch (error) {
    DioException() => _mapDioException(error),
    FormatException(:final message) => ParsingFailure(message),
    TypeError() => ParsingFailure(error.toString()),
    _ => UnknownFailure(error.toString()),
  };
}

Failure _mapDioException(DioException error) {
  switch (error.type) {
    case DioExceptionType.connectionTimeout:
    case DioExceptionType.sendTimeout:
    case DioExceptionType.receiveTimeout:
    case DioExceptionType.transformTimeout:
    case DioExceptionType.connectionError:
      return NetworkFailure(error.message ?? error.type.name);
    case DioExceptionType.badResponse:
      return _mapResponse(error.response);
    case DioExceptionType.badCertificate:
    case DioExceptionType.cancel:
    case DioExceptionType.unknown:
      return UnknownFailure(error.message ?? error.type.name);
  }
}

Failure _mapResponse(Response<Object?>? response) {
  final statusCode = response?.statusCode;
  final body = response?.data;
  final serverMessage =
      body is Map<String, Object?> && body['message'] is String
      ? body['message']! as String
      : 'HTTP $statusCode';

  return switch (statusCode) {
    422 => ValidationFailure(
      serverMessage,
      fieldErrors: _firstErrorPerField(body),
    ),
    404 => NotFoundFailure(serverMessage),
    429 => ThrottledFailure(serverMessage),
    502 => GatewayUnavailableFailure(serverMessage),
    _ => ServerFailure(serverMessage, statusCode: statusCode),
  };
}

Map<String, String> _firstErrorPerField(Object? body) {
  if (body case {'errors': final Map<String, Object?> errors}) {
    return {
      for (final MapEntry(:key, :value) in errors.entries)
        if (value case [final String first, ...]) key: first,
    };
  }
  return const {};
}
