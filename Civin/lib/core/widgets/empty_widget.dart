import 'package:civin/core/constants/app_sizes.dart';
import 'package:civin/core/constants/strings.dart';
import 'package:flutter/material.dart';

final class EmptyStateWidget extends StatelessWidget {
  const EmptyStateWidget({
    super.key,
    this.message = AppStrings.emptyState,
    this.icon = Icons.inbox_outlined,
    this.action,
  });

  final String message;
  final IconData icon;
  final Widget? action;

  @override
  Widget build(BuildContext context) => Center(
    child: Padding(
      padding: AppSizes.pagePadding,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Icon(icon, size: 48, color: Theme.of(context).colorScheme.outline),
          const SizedBox(height: AppSizes.space16),
          Text(message, textAlign: TextAlign.center),
          if (action != null) ...<Widget>[
            const SizedBox(height: AppSizes.space16),
            action!,
          ],
        ],
      ),
    ),
  );
}
