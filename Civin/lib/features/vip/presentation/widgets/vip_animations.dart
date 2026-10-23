import 'package:flutter/material.dart';

final class VipEntranceAnimation extends StatelessWidget {
  const VipEntranceAnimation({
    required this.animation,
    required this.child,
    this.delay = 0,
    super.key,
  });

  final Animation<double> animation;
  final Widget child;
  final double delay;

  @override
  Widget build(BuildContext context) {
    final Animation<double> curved = CurvedAnimation(
      parent: animation,
      curve: Interval(delay.clamp(0, 0.8), 1, curve: Curves.easeOutCubic),
    );
    return FadeTransition(
      opacity: curved,
      child: SlideTransition(
        position: Tween<Offset>(
          begin: const Offset(0, 0.08),
          end: Offset.zero,
        ).animate(curved),
        child: child,
      ),
    );
  }
}

final class VipPulseBadgeShell extends StatefulWidget {
  const VipPulseBadgeShell({required this.child, super.key});

  final Widget child;

  @override
  State<VipPulseBadgeShell> createState() => _VipPulseBadgeShellState();
}

final class _VipPulseBadgeShellState extends State<VipPulseBadgeShell>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller;

  @override
  void initState() {
    super.initState();
    _controller = AnimationController(
      vsync: this,
      duration: const Duration(milliseconds: 1600),
    )..repeat(reverse: true);
  }

  @override
  void dispose() {
    _controller.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return AnimatedBuilder(
      animation: _controller,
      builder: (BuildContext context, Widget? child) {
        final double t = Curves.easeInOut.transform(_controller.value);
        return Transform.scale(
          scale: 1 + (t * 0.04),
          child: child,
        );
      },
      child: widget.child,
    );
  }
}
