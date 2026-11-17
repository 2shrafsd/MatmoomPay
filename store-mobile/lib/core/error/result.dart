import 'package:matjar_app/core/error/failure.dart';

/// The outcome of a repository call. Repositories return this instead of
/// throwing, so every caller has to handle the failure branch.
sealed class Result<T> {
  const Result();

  /// Runs [onSuccess] or [onFailure] depending on the branch.
  R fold<R>({
    required R Function(T data) onSuccess,
    required R Function(Failure failure) onFailure,
  }) => switch (this) {
    Success<T>(:final data) => onSuccess(data),
    Err<T>(:final failure) => onFailure(failure),
  };
}

final class Success<T> extends Result<T> {
  const Success(this.data);

  final T data;
}

final class Err<T> extends Result<T> {
  const Err(this.failure);

  final Failure failure;
}
