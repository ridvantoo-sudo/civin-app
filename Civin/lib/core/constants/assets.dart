abstract final class AppAssets {
  static const String animations = 'assets/animations';
  static const String fonts = 'assets/fonts';
  static const String icons = 'assets/icons';
  static const String images = 'assets/images';

  static String animation(String fileName) => '$animations/$fileName';
  static String icon(String fileName) => '$icons/$fileName';
  static String image(String fileName) => '$images/$fileName';

  static const String splashLogoAnimation = '$animations/splash_logo.json';
}
