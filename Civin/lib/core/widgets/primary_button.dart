import 'package:civin/core/constants/app_sizes.dart';
import 'package:flutter/material.dart';

final class PrimaryButton extends StatelessWidget {
  const PrimaryButton({
    required this.label,
    required this.onPressed,
    super.key,
    this.icon,
    this.isLoading = false,
    this.expand = true,
  });

  final String label;
  final VoidCallback? onPressed;
  final IconData? icon;
  final bool isLoading;
  final bool expand;

  @override
  Widget build(BuildContext context) {
    final Widget child = AnimatedSwitcher(
      duration: const Duration(milliseconds: 180),
      child: isLoading
          ? const SizedBox.square(
              key: ValueKey<String>('loading'),
              dimension: 20,
              child: CircularProgressIndicator.adaptive(strokeWidth: 2),
            )
          : Row(
              key: const ValueKey<String>('label'),
              mainAxisSize: MainAxisSize.min,
              mainAxisAlignment: MainAxisAlignment.center,
              children: <Widget>[
                if (icon case final IconData value) ...<Widget>[
                  Icon(value),
                  const SizedBox(width: AppSizes.space8),
                ],
                Text(label),
              ],
            ),
    );
    final Widget button = FilledButton(
      onPressed: isLoading ? null : onPressed,
      child: child,
    );
    return expand ? SizedBox(width: double.infinity, child: button) : button;
  }
}
