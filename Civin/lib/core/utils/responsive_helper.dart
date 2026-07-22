import 'package:flutter/widgets.dart';

enum DeviceType { mobile, tablet, desktop }

abstract final class ResponsiveHelper {
  static const double tabletBreakpoint = 600;
  static const double desktopBreakpoint = 1024;

  static DeviceType deviceTypeOf(BuildContext context) {
    final double width = MediaQuery.sizeOf(context).width;
    if (width >= desktopBreakpoint) {
      return DeviceType.desktop;
    }
    if (width >= tabletBreakpoint) {
      return DeviceType.tablet;
    }
    return DeviceType.mobile;
  }

  static bool isMobile(BuildContext context) =>
      deviceTypeOf(context) == DeviceType.mobile;

  static double value<T extends num>(
    BuildContext context, {
    required T mobile,
    required T tablet,
    required T desktop,
  }) => switch (deviceTypeOf(context)) {
    DeviceType.mobile => mobile.toDouble(),
    DeviceType.tablet => tablet.toDouble(),
    DeviceType.desktop => desktop.toDouble(),
  };
}
