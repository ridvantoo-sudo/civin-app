import 'dart:async';

import 'package:civin/features/gifts/domain/entities/gift.dart';
import 'package:civin/features/gifts/presentation/gift_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:lottie/lottie.dart';

/// Full-screen gift animation overlay. Lottie-ready; SVGA falls back to icon.
final class GiftAnimationOverlay extends ConsumerWidget {
  const GiftAnimationOverlay({required this.roomId, super.key});

  final String roomId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final GiftSentEvent? event = ref.watch(
      giftProvider(roomId).select((GiftRoomState s) => s.currentAnimation),
    );
    if (event == null) return const SizedBox.shrink();

    return IgnorePointer(
      child: AnimatedSwitcher(
        duration: const Duration(milliseconds: 320),
        switchInCurve: Curves.easeOutCubic,
        switchOutCurve: Curves.easeInCubic,
        child: _GiftAnimationStage(
          key: ValueKey<String>(event.transactionId),
          event: event,
          onCompleted: () =>
              ref.read(giftProvider(roomId).notifier).dismissCurrentAnimation(),
        ),
      ),
    );
  }
}

final class _GiftAnimationStage extends StatefulWidget {
  const _GiftAnimationStage({
    required this.event,
    required this.onCompleted,
    super.key,
  });

  final GiftSentEvent event;
  final VoidCallback onCompleted;

  @override
  State<_GiftAnimationStage> createState() => _GiftAnimationStageState();
}

final class _GiftAnimationStageState extends State<_GiftAnimationStage>
    with SingleTickerProviderStateMixin {
  late final AnimationController _bannerController = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 480),
  );
  late final Animation<Offset> _slide =
      Tween<Offset>(begin: const Offset(0, -0.4), end: Offset.zero).animate(
        CurvedAnimation(parent: _bannerController, curve: Curves.easeOutBack),
      );
  late final Animation<double> _fade = CurvedAnimation(
    parent: _bannerController,
    curve: Curves.easeOut,
  );
  Timer? _completionTimer;

  @override
  void initState() {
    super.initState();
    _bannerController.forward();
    final GiftAnimationInfo? animation =
        widget.event.animation ?? widget.event.gift.animation;
    if (animation == null ||
        animation.url == null ||
        animation.url!.isEmpty ||
        animation.isSvga ||
        !animation.isLottie) {
      _scheduleCompletion(const Duration(milliseconds: 2200));
    }
  }

  void _scheduleCompletion(Duration delay) {
    _completionTimer?.cancel();
    _completionTimer = Timer(delay, () {
      if (mounted) widget.onCompleted();
    });
  }

  @override
  void dispose() {
    _completionTimer?.cancel();
    _bannerController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final GiftSentEvent event = widget.event;
    final GiftAnimationInfo? animation =
        event.animation ?? event.gift.animation;
    final String? url = animation?.url ?? event.gift.animationUrl;
    final String? icon = animation?.icon ?? event.gift.icon;

    return Stack(
      fit: StackFit.expand,
      children: [
        ColoredBox(color: Colors.black.withValues(alpha: 0.18)),
        Center(child: _buildMedia(url, icon)),
        Positioned(
          top: MediaQuery.paddingOf(context).top + 72,
          left: 20,
          right: 20,
          child: FadeTransition(
            opacity: _fade,
            child: SlideTransition(
              position: _slide,
              child: _GiftComboBanner(event: event),
            ),
          ),
        ),
      ],
    );
  }

  Widget _buildMedia(String? url, String? icon) {
    if (url != null && url.toLowerCase().endsWith('.json')) {
      return Lottie.network(
        url,
        width: 280,
        height: 280,
        fit: BoxFit.contain,
        repeat: false,
        onLoaded: (LottieComposition composition) {
          _scheduleCompletion(
            composition.duration + const Duration(milliseconds: 200),
          );
        },
        errorBuilder: (BuildContext context, Object error, StackTrace? stack) =>
            _GiftIconFallback(iconUrl: icon, giftName: widget.event.gift.name),
      );
    }

    // SVGA-ready placeholder: show icon until an SVGA player is wired.
    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0.72, end: 1),
      duration: const Duration(milliseconds: 700),
      curve: Curves.elasticOut,
      builder: (BuildContext context, double scale, Widget? child) =>
          Transform.scale(scale: scale, child: child),
      child: _GiftIconFallback(iconUrl: icon, giftName: widget.event.gift.name),
    );
  }
}

final class _GiftComboBanner extends StatelessWidget {
  const _GiftComboBanner({required this.event});

  final GiftSentEvent event;

  @override
  Widget build(BuildContext context) {
    final ColorScheme colors = Theme.of(context).colorScheme;
    return Align(
      alignment: Alignment.centerLeft,
      child: DecoratedBox(
        decoration: BoxDecoration(
          color: colors.surface.withValues(alpha: 0.88),
          borderRadius: BorderRadius.circular(18),
          border: Border.all(color: colors.primary.withValues(alpha: 0.35)),
        ),
        child: Padding(
          padding: const EdgeInsets.symmetric(horizontal: 14, vertical: 10),
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              CircleAvatar(
                radius: 16,
                backgroundImage: event.sender.avatarUrl == null
                    ? null
                    : NetworkImage(event.sender.avatarUrl!),
                child: event.sender.avatarUrl == null
                    ? Text(
                        event.sender.displayName.isEmpty
                            ? '?'
                            : event.sender.displayName[0].toUpperCase(),
                      )
                    : null,
              ),
              const SizedBox(width: 10),
              Flexible(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      event.sender.displayName,
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.labelLarge?.copyWith(
                        color: colors.onSurface,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                    Text(
                      'sent ${event.gift.name} ×${event.quantity}',
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: colors.onSurfaceVariant,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

final class _GiftIconFallback extends StatelessWidget {
  const _GiftIconFallback({required this.giftName, this.iconUrl});

  final String? iconUrl;
  final String giftName;

  @override
  Widget build(BuildContext context) {
    final Widget image = iconUrl == null || iconUrl!.isEmpty
        ? Icon(
            Icons.card_giftcard_rounded,
            size: 120,
            color: Theme.of(context).colorScheme.primary,
          )
        : Image.network(
            iconUrl!,
            width: 160,
            height: 160,
            fit: BoxFit.contain,
            errorBuilder:
                (BuildContext context, Object error, StackTrace? stack) => Icon(
                  Icons.card_giftcard_rounded,
                  size: 120,
                  color: Theme.of(context).colorScheme.primary,
                ),
          );

    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        image,
        const SizedBox(height: 12),
        Text(
          giftName,
          style: Theme.of(context).textTheme.titleMedium?.copyWith(
            color: Colors.white,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }
}
