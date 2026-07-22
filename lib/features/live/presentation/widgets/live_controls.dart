import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:flutter/material.dart';

final class LiveControls extends StatelessWidget {
  const LiveControls({
    required this.role,
    required this.isMicMuted,
    required this.onLeave,
    this.onToggleMute,
    this.onSwitchCamera,
    super.key,
  });

  final LiveRole role;
  final bool isMicMuted;
  final VoidCallback onLeave;
  final VoidCallback? onToggleMute;
  final VoidCallback? onSwitchCamera;

  @override
  Widget build(BuildContext context) => SafeArea(
    top: false,
    child: Padding(
      padding: const EdgeInsets.all(20),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          if (role == LiveRole.host) ...[
            _ControlButton(
              tooltip: isMicMuted ? 'Unmute microphone' : 'Mute microphone',
              icon: isMicMuted ? Icons.mic_off_rounded : Icons.mic_rounded,
              onPressed: onToggleMute,
            ),
            const SizedBox(width: 16),
            _ControlButton(
              tooltip: 'Switch camera',
              icon: Icons.cameraswitch_rounded,
              onPressed: onSwitchCamera,
            ),
            const SizedBox(width: 16),
          ],
          _ControlButton(
            tooltip: role == LiveRole.host ? 'End stream' : 'Leave stream',
            icon: role == LiveRole.host
                ? Icons.stop_rounded
                : Icons.logout_rounded,
            backgroundColor: Theme.of(context).colorScheme.error,
            onPressed: onLeave,
          ),
        ],
      ),
    ),
  );
}

final class _ControlButton extends StatelessWidget {
  const _ControlButton({
    required this.tooltip,
    required this.icon,
    required this.onPressed,
    this.backgroundColor,
  });

  final String tooltip;
  final IconData icon;
  final VoidCallback? onPressed;
  final Color? backgroundColor;

  @override
  Widget build(BuildContext context) => IconButton.filled(
    tooltip: tooltip,
    onPressed: onPressed,
    style: IconButton.styleFrom(
      backgroundColor: backgroundColor ?? Colors.black54,
      foregroundColor: Colors.white,
      minimumSize: const Size.square(56),
    ),
    icon: Icon(icon),
  );
}
