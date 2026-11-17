import 'package:flutter/foundation.dart';

/// What this device remembers about an order it created: enough to ask
/// Matjar for the status later.
@immutable
class SavedOrder {
  const SavedOrder({
    required this.reference,
    required this.accessToken,
    required this.createdAt,
  });

  factory SavedOrder.fromJson(Map<String, Object?> json) {
    if (json case {
      'reference': final String reference,
      'access_token': final String accessToken,
      'created_at': final String createdAt,
    }) {
      return SavedOrder(
        reference: reference,
        accessToken: accessToken,
        createdAt: DateTime.parse(createdAt),
      );
    }
    throw FormatException('Invalid saved order: ${json.keys}');
  }

  final String reference;
  final String accessToken;
  final DateTime createdAt;

  Map<String, Object?> toJson() => {
    'reference': reference,
    'access_token': accessToken,
    'created_at': createdAt.toIso8601String(),
  };
}
