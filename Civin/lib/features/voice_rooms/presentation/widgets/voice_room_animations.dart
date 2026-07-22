import 'package:flutter/material.dart';

/// Soft pulse + ambient glow used behind mic seats and room chrome.
final class VoiceRoomAmbientBackground extends StatefulWidget {
  const VoiceRoomAmbientBackground({super.key, this.child});

  final Widget? child;

  @override
  State<VoiceRoomAmbientBackground> createState() =>
      _VoiceRoomAmbientBackgroundState();
}

final class _VoiceRoomAmbientBackgroundState
    extends State<VoiceRoomAmbientBackground>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 3200),
  )..repeat(reverse: true);

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
        return DecoratedBox(
          decoration: BoxDecoration(
            gradient: RadialGradient(
              center: Alignment(-0.2 + (t * 0.15), -0.55),
              radius: 1.15 + (t * 0.12),
              colors: <Color>[
                Color.lerp(
                  const Color(0xFF0F766E),
                  const Color(0xFF0369A1),
                  t,
                )!.withValues(alpha: 0.45),
                const Color(0xFF0B1220),
                const Color(0xFF070B14),
              ],
              stops: const <double>[0, 0.45, 1],
            ),
          ),
          child: child,
        );
      },
      child: widget.child,
    );
  }
}

final class VoiceSpeakingPulse extends StatefulWidget {
  const VoiceSpeakingPulse({
    required this.active,
    required this.child,
    super.key,
  });

  final bool active;
  final Widget child;

  @override
  State<VoiceSpeakingPulse> createState() => _VoiceSpeakingPulseState();
}

final class _VoiceSpeakingPulseState extends State<VoiceSpeakingPulse>
    with SingleTickerProviderStateMixin {
  late final AnimationController _controller = AnimationController(
    vsync: this,
    duration: const Duration(milliseconds: 900),
  );

  @override
  void initState() {
    super.initState();
    _sync();
  }

  @override
  void didUpdateWidget(covariant VoiceSpeakingPulse oldWidget) {
    super.didUpdateWidget(oldWidget);
    if (oldWidget.active != widget.active) _sync();
  }

  void _sync() {
    if (widget.active) {
      _controller.repeat(reverse: true);
    } else {
      _controller
        ..stop()
        ..value = 0;
    }
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
        final double scale = 1 + (_controller.value * 0.06);
        return Transform.scale(scale: scale, child: child);
      },
      child: widget.child,
    );
  }
}
