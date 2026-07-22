import 'dart:async';

import 'package:civin/features/gifts/domain/entities/gift.dart';
import 'package:civin/features/gifts/presentation/gift_providers.dart';
import 'package:civin/features/gifts/presentation/widgets/gift_history_sheet.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Gift catalog panel: categories, gifts, price, and send action.
final class GiftPanel extends ConsumerWidget {
  const GiftPanel({required this.roomId, super.key});

  final String roomId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<GiftCatalog> catalog = ref.watch(giftCatalogProvider);
    final GiftRoomState roomState = ref.watch(giftProvider(roomId));
    final GiftSendingState sending = ref.watch(giftSendingProvider(roomId));
    final ColorScheme colors = Theme.of(context).colorScheme;

    return Material(
      color: const Color(0xFF12121A),
      borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
      child: SafeArea(
        top: false,
        child: SizedBox(
          height: MediaQuery.sizeOf(context).height * 0.52,
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 8),
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: colors.onSurface.withValues(alpha: 0.24),
                    borderRadius: BorderRadius.circular(999),
                  ),
                ),
              ),
              Padding(
                padding: const EdgeInsets.fromLTRB(20, 16, 8, 8),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        'Send a gift',
                        style: Theme.of(context).textTheme.titleLarge?.copyWith(
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                    IconButton(
                      tooltip: 'Gift history',
                      onPressed: () => showGiftHistorySheet(context, roomId),
                      icon: const Icon(Icons.history_rounded),
                    ),
                    IconButton(
                      tooltip: 'Close',
                      onPressed: () =>
                          ref.read(giftProvider(roomId).notifier).closePanel(),
                      icon: const Icon(Icons.close_rounded),
                    ),
                  ],
                ),
              ),
              Expanded(
                child: catalog.when(
                  loading: () =>
                      const Center(child: CircularProgressIndicator()),
                  error: (Object error, StackTrace stack) => Center(
                    child: Padding(
                      padding: const EdgeInsets.all(24),
                      child: Column(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Text(
                            'Unable to load gifts',
                            style: Theme.of(context).textTheme.titleMedium,
                          ),
                          const SizedBox(height: 8),
                          Text(
                            '$error',
                            textAlign: TextAlign.center,
                            style: Theme.of(context).textTheme.bodySmall,
                          ),
                          const SizedBox(height: 16),
                          FilledButton(
                            onPressed: () =>
                                ref.read(giftCatalogProvider.notifier).refresh(),
                            child: const Text('Retry'),
                          ),
                        ],
                      ),
                    ),
                  ),
                  data: (GiftCatalog data) => _GiftCatalogBody(
                    roomId: roomId,
                    catalog: data,
                    selectedCategoryId: roomState.selectedCategoryId,
                    sending: sending,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _GiftCatalogBody extends ConsumerWidget {
  const _GiftCatalogBody({
    required this.roomId,
    required this.catalog,
    required this.sending,
    this.selectedCategoryId,
  });

  final String roomId;
  final GiftCatalog catalog;
  final String? selectedCategoryId;
  final GiftSendingState sending;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final String? categoryId =
        selectedCategoryId ??
        (catalog.categories.isEmpty ? null : catalog.categories.first.id);
    final List<Gift> gifts = catalog.giftsForCategory(categoryId);
    Gift? selected;
    final String? selectedId = sending.selectedGiftId;
    if (selectedId != null) {
      for (final Gift gift in gifts) {
        if (gift.id == selectedId) {
          selected = gift;
          break;
        }
      }
    }
    selected ??= gifts.isEmpty ? null : gifts.first;

    return Column(
      children: [
        if (catalog.categories.isNotEmpty)
          SizedBox(
            height: 44,
            child: ListView.separated(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              scrollDirection: Axis.horizontal,
              itemCount: catalog.categories.length,
              separatorBuilder: (_, _) => const SizedBox(width: 8),
              itemBuilder: (BuildContext context, int index) {
                final GiftCategory category = catalog.categories[index];
                final bool selectedChip = category.id == categoryId;
                return ChoiceChip(
                  label: Text(category.name),
                  selected: selectedChip,
                  onSelected: (_) => ref
                      .read(giftProvider(roomId).notifier)
                      .selectCategory(category.id),
                );
              },
            ),
          ),
        const SizedBox(height: 8),
        Expanded(
          child: gifts.isEmpty
              ? const Center(child: Text('No gifts in this category'))
              : GridView.builder(
                  padding: const EdgeInsets.fromLTRB(16, 8, 16, 8),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 4,
                    mainAxisSpacing: 12,
                    crossAxisSpacing: 12,
                    childAspectRatio: 0.72,
                  ),
                  itemCount: gifts.length,
                  itemBuilder: (BuildContext context, int index) {
                    final Gift gift = gifts[index];
                    final bool isSelected = gift.id == selected?.id;
                    return _GiftTile(
                      gift: gift,
                      selected: isSelected,
                      onTap: () => ref
                          .read(giftSendingProvider(roomId).notifier)
                          .selectGift(gift.id),
                    );
                  },
                ),
        ),
        if (sending.errorMessage != null)
          Padding(
            padding: const EdgeInsets.symmetric(horizontal: 20),
            child: Text(
              sending.errorMessage!,
              style: TextStyle(color: Theme.of(context).colorScheme.error),
            ),
          ),
        Padding(
          padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
          child: Row(
            children: [
              if (selected != null) ...[
                Text(
                  '${selected.coinPrice} coins',
                  style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
                ),
                const SizedBox(width: 12),
                _QuantityStepper(
                  quantity: sending.quantity,
                  onChanged: (int value) => ref
                      .read(giftSendingProvider(roomId).notifier)
                      .setQuantity(value),
                ),
                const Spacer(),
              ] else
                const Spacer(),
              FilledButton.icon(
                onPressed: selected == null || sending.isSending
                    ? null
                    : () {
                        final String giftId = selected!.id;
                        unawaited(_sendSelectedGift(ref, context, giftId));
                      },
                icon: sending.isSending
                    ? const SizedBox(
                        width: 16,
                        height: 16,
                        child: CircularProgressIndicator(strokeWidth: 2),
                      )
                    : const Icon(Icons.card_giftcard_rounded),
                label: Text(sending.isSending ? 'Sending…' : 'Send gift'),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _sendSelectedGift(
    WidgetRef ref,
    BuildContext context,
    String giftId,
  ) async {
    final bool ok = await ref
        .read(giftSendingProvider(roomId).notifier)
        .send(giftId: giftId);
    if (ok && context.mounted) {
      ref.read(giftProvider(roomId).notifier).closePanel();
    }
  }
}

final class _GiftTile extends StatelessWidget {
  const _GiftTile({
    required this.gift,
    required this.selected,
    required this.onTap,
  });

  final Gift gift;
  final bool selected;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 180),
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          border: Border.all(
            color: selected
                ? colors.primary
                : colors.outline.withValues(alpha: 0.2),
            width: selected ? 2 : 1,
          ),
          color: selected
              ? colors.primary.withValues(alpha: 0.14)
              : colors.surfaceContainerHighest.withValues(alpha: 0.35),
        ),
        child: Column(
          children: [
            Expanded(
              child: gift.icon == null || gift.icon!.isEmpty
                  ? Icon(Icons.card_giftcard_rounded, color: colors.primary)
                  : Image.network(
                      gift.icon!,
                      fit: BoxFit.contain,
                      errorBuilder:
                          (
                            BuildContext context,
                            Object error,
                            StackTrace? stack,
                          ) => Icon(
                            Icons.card_giftcard_rounded,
                            color: colors.primary,
                          ),
                    ),
            ),
            const SizedBox(height: 4),
            Text(
              gift.name,
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              style: Theme.of(context).textTheme.labelSmall,
            ),
            Text(
              '${gift.coinPrice}',
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                color: colors.tertiary,
                fontWeight: FontWeight.w700,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

final class _QuantityStepper extends StatelessWidget {
  const _QuantityStepper({required this.quantity, required this.onChanged});

  final int quantity;
  final ValueChanged<int> onChanged;

  @override
  Widget build(BuildContext context) => Row(
    mainAxisSize: MainAxisSize.min,
    children: [
      IconButton.filledTonal(
        tooltip: 'Decrease quantity',
        onPressed: quantity <= 1 ? null : () => onChanged(quantity - 1),
        icon: const Icon(Icons.remove_rounded, size: 18),
        visualDensity: VisualDensity.compact,
      ),
      Padding(
        padding: const EdgeInsets.symmetric(horizontal: 8),
        child: Text(
          '$quantity',
          style: Theme.of(
            context,
          ).textTheme.titleMedium?.copyWith(fontWeight: FontWeight.w700),
        ),
      ),
      IconButton.filledTonal(
        tooltip: 'Increase quantity',
        onPressed: quantity >= 999 ? null : () => onChanged(quantity + 1),
        icon: const Icon(Icons.add_rounded, size: 18),
        visualDensity: VisualDensity.compact,
      ),
    ],
  );
}
