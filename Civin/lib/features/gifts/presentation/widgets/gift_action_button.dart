import 'package:civin/features/gifts/presentation/gift_providers.dart';
import 'package:civin/features/gifts/presentation/widgets/gift_panel.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

/// Floating gift entry point for the live room overlay.
final class GiftActionButton extends ConsumerWidget {
  const GiftActionButton({required this.roomId, super.key});

  final String roomId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final bool panelOpen = ref.watch(
      giftProvider(roomId).select((state) => state.panelOpen),
    );

    return SafeArea(
      child: Align(
        alignment: Alignment.bottomRight,
        child: Padding(
          padding: const EdgeInsets.fromLTRB(0, 0, 20, 96),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              AnimatedScale(
                scale: panelOpen ? 0.92 : 1,
                duration: const Duration(milliseconds: 180),
                child: FloatingActionButton(
                  heroTag: 'gift-fab-$roomId',
                  tooltip: 'Send gift',
                  onPressed: () async {
                    ref.read(giftProvider(roomId).notifier).openPanel();
                    await showModalBottomSheet<void>(
                      context: context,
                      backgroundColor: Colors.transparent,
                      isScrollControlled: true,
                      builder: (BuildContext context) =>
                          GiftPanel(roomId: roomId),
                    );
                    if (context.mounted) {
                      ref.read(giftProvider(roomId).notifier).closePanel();
                    }
                  },
                  child: const Icon(Icons.card_giftcard_rounded),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
