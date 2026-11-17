import 'package:flutter/foundation.dart';

/// A product from `GET /api/products`. Prices are whole Sudanese pounds.
@immutable
class Product {
  const Product({
    required this.slug,
    required this.name,
    required this.description,
    required this.price,
    required this.priceFormatted,
    required this.imageUrl,
    required this.colorHex,
    this.category,
  });

  /// Parses one entry of the `data` array; throws [FormatException] when a
  /// required field is missing or has the wrong type.
  factory Product.fromJson(
    Map<String, Object?> json, {
    String Function(String url)? rewriteUrl,
  }) {
    if (json case {
      'slug': final String slug,
      'name': final String name,
      'price': final int price,
    }) {
      final imageUrl = json['image_url'];
      return Product(
        slug: slug,
        name: name,
        description: json['description'] is String
            ? json['description']! as String
            : '',
        price: price,
        priceFormatted: json['price_formatted'] is String
            ? json['price_formatted']! as String
            : '',
        imageUrl: imageUrl is String && imageUrl.isNotEmpty
            ? (rewriteUrl?.call(imageUrl) ?? imageUrl)
            : null,
        colorHex: json['color'] is String ? json['color']! as String : '',
        category: _optionalText(json['category']),
      );
    }
    throw FormatException('Invalid product: $json');
  }

  final String slug;
  final String name;
  final String description;
  final int price;
  final String priceFormatted;
  final String? imageUrl;
  final String colorHex;

  /// Optional grouping the API may add; null when absent or blank.
  final String? category;

  static String? _optionalText(Object? value) =>
      value is String && value.trim().isNotEmpty ? value.trim() : null;

  @override
  bool operator ==(Object other) =>
      other is Product &&
      other.slug == slug &&
      other.name == name &&
      other.description == description &&
      other.price == price &&
      other.priceFormatted == priceFormatted &&
      other.imageUrl == imageUrl &&
      other.colorHex == colorHex &&
      other.category == category;

  @override
  int get hashCode => Object.hash(
    slug,
    name,
    description,
    price,
    priceFormatted,
    imageUrl,
    colorHex,
    category,
  );
}
