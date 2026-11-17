import 'package:cached_network_image/cached_network_image.dart';
import 'package:material_ui/material_ui.dart';
import 'package:matjar_app/core/theme/app_palette.dart';
import 'package:matjar_app/core/utils/hex_color.dart';

/// Product photo from the server on a warm-grey tile. Callers clip it to
/// their own radius.
///
/// While the photo loads, fails or is missing, the tile shows a soft wash of
/// the product's own colour with its first letter.
class ProductImage extends StatelessWidget {
  const ProductImage({
    required this.imageUrl,
    required this.colorHex,
    required this.name,
    this.memCacheWidth,
    this.fit = BoxFit.cover,
    super.key,
  });

  /// Opacity of the product colour over the tile.
  static const double colourWashOpacity = 0.18;

  final String? imageUrl;
  final String colorHex;
  final String name;
  final BoxFit fit;

  /// Decode width in physical pixels, so large photos are not decoded at full
  /// size for a small tile.
  final int? memCacheWidth;

  @override
  Widget build(BuildContext context) {
    final placeholder = _ColourPlaceholder(colorHex: colorHex, name: name);
    final url = imageUrl;
    return ColoredBox(
      color: context.palette.panel,
      child: url == null || url.isEmpty
          ? placeholder
          : CachedNetworkImage(
              imageUrl: url,
              fit: fit,
              width: double.infinity,
              height: double.infinity,
              memCacheWidth: memCacheWidth,
              fadeInDuration: const Duration(milliseconds: 250),
              placeholder: (_, _) => placeholder,
              errorWidget: (_, _, _) => placeholder,
            ),
    );
  }
}

class _ColourPlaceholder extends StatelessWidget {
  const _ColourPlaceholder({required this.colorHex, required this.name});

  final String colorHex;
  final String name;

  @override
  Widget build(BuildContext context) {
    final colour = parseHexColor(colorHex);
    final initial = name.characters.isEmpty ? '' : name.characters.first;
    return DecoratedBox(
      decoration: BoxDecoration(
        color: colour?.withValues(alpha: ProductImage.colourWashOpacity),
      ),
      child: SizedBox.expand(
        child: Center(
          child: ExcludeSemantics(
            child: Text(
              initial,
              style: Theme.of(context).textTheme.displaySmall
                  ?.copyWith(color: context.palette.mutedText),
            ),
          ),
        ),
      ),
    );
  }
}
