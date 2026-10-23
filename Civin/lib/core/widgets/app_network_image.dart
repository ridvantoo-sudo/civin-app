import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/material.dart';

final class AppNetworkImage extends StatelessWidget {
  const AppNetworkImage({
    required this.url,
    super.key,
    this.fit = BoxFit.cover,
    this.width,
    this.height,
    this.borderRadius,
  });

  final String url;
  final BoxFit fit;
  final double? width;
  final double? height;
  final BorderRadius? borderRadius;

  @override
  Widget build(BuildContext context) {
    final Widget image = CachedNetworkImage(
      imageUrl: url,
      fit: fit,
      width: width,
      height: height,
      fadeInDuration: const Duration(milliseconds: 180),
      placeholder: (BuildContext context, String url) =>
          const Center(child: CircularProgressIndicator.adaptive()),
      errorWidget: (BuildContext context, String url, Object error) =>
          const Center(child: Icon(Icons.broken_image_outlined)),
    );
    if (borderRadius == null) {
      return image;
    }
    return ClipRRect(borderRadius: borderRadius!, child: image);
  }
}
