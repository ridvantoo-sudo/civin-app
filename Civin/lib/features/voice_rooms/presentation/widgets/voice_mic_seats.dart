import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:civin/features/voice_rooms/presentation/voice_room_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class VoiceMicSeats extends ConsumerWidget {
  const VoiceMicSeats({required this.roomId, super.key});

  final String roomId;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final SeatViewState seats = ref.watch(seatProvider(roomId));
    final VoiceRoomSessionState session = ref.watch(voiceRoomProvider(roomId));

    if (seats.seats.isEmpty) {
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(24),
          child: CircularProgressIndicator(),
        ),
      );
    }

    return GridView.builder(
      itemCount: seats.seats.length,
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 4,
        mainAxisSpacing: 12,
        crossAxisSpacing: 12,
        childAspectRatio: 0.78,
      ),
      itemBuilder: (BuildContext context, int index) {
        final VoiceSeat seat = seats.seats[index];
        return _SeatTile(
          seat: seat,
          isHost: session.isHost,
          onTap: () => _onSeatTap(context, ref, session, seat),
        );
      },
    );
  }

  Future<void> _onSeatTap(
    BuildContext context,
    WidgetRef ref,
    VoiceRoomSessionState session,
    VoiceSeat seat,
  ) async {
    final VoiceRoomController controller = ref.read(
      voiceRoomProvider(roomId).notifier,
    );

    if (session.isHost) {
      if (seat.isPending) {
        await showModalBottomSheet<void>(
          context: context,
          backgroundColor: const Color(0xFF141820),
          showDragHandle: true,
          builder: (BuildContext context) => SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    'Mic request',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 8),
                  Text(
                    '${seat.user?.displayName ?? 'Someone'} wants seat ${seat.seatIndex}',
                  ),
                  const SizedBox(height: 16),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {
                            Navigator.pop(context);
                            controller.rejectSeat(seat.seatIndex);
                          },
                          child: const Text('Reject'),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: FilledButton(
                          onPressed: () {
                            Navigator.pop(context);
                            controller.approveSeat(seat.seatIndex);
                          },
                          child: const Text('Approve'),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ),
        );
        return;
      }
      if (seat.isOccupied && !seat.isHostSeat) {
        await showModalBottomSheet<void>(
          context: context,
          backgroundColor: const Color(0xFF141820),
          showDragHandle: true,
          builder: (BuildContext context) => SafeArea(
            child: Padding(
              padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Text(
                    seat.user?.displayName ?? 'Speaker',
                    style: Theme.of(context).textTheme.titleMedium,
                  ),
                  const SizedBox(height: 16),
                  ListTile(
                    leading: Icon(
                      seat.isMuted
                          ? Icons.mic_rounded
                          : Icons.mic_off_rounded,
                    ),
                    title: Text(seat.isMuted ? 'Unmute speaker' : 'Mute speaker'),
                    onTap: () {
                      Navigator.pop(context);
                      controller.muteSpeaker(
                        seat.seatIndex,
                        muted: !seat.isMuted,
                      );
                    },
                  ),
                  ListTile(
                    leading: const Icon(Icons.person_remove_rounded),
                    title: const Text('Remove speaker'),
                    onTap: () {
                      Navigator.pop(context);
                      controller.removeSpeaker(seat.seatIndex);
                    },
                  ),
                ],
              ),
            ),
          ),
        );
      }
      return;
    }

    if (seat.isEmpty && !session.isSpeaker) {
      final bool ok = await controller.requestMic(seat.seatIndex);
      if (!context.mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(
            ok ? 'Mic request sent' : session.errorMessage ?? 'Request failed',
          ),
        ),
      );
    }
  }
}

final class _SeatTile extends StatelessWidget {
  const _SeatTile({
    required this.seat,
    required this.isHost,
    required this.onTap,
  });

  final VoiceSeat seat;
  final bool isHost;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    final bool occupied = seat.isOccupied;
    final bool pending = seat.isPending;
    final Color accent = occupied
        ? const Color(0xFF2DD4BF)
        : pending
        ? const Color(0xFFFBBF24)
        : Colors.white24;

    return InkWell(
      onTap: onTap,
      borderRadius: BorderRadius.circular(16),
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 260),
        curve: Curves.easeOutCubic,
        padding: const EdgeInsets.all(8),
        decoration: BoxDecoration(
          borderRadius: BorderRadius.circular(16),
          color: Colors.white.withValues(alpha: occupied ? 0.08 : 0.04),
          border: Border.all(color: accent.withValues(alpha: 0.55)),
        ),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Stack(
              alignment: Alignment.center,
              children: [
                AnimatedContainer(
                  duration: const Duration(milliseconds: 320),
                  width: occupied ? 48 : 42,
                  height: occupied ? 48 : 42,
                  decoration: BoxDecoration(
                    shape: BoxShape.circle,
                    color: accent.withValues(alpha: 0.18),
                    border: Border.all(color: accent, width: 1.4),
                  ),
                  child: Icon(
                    occupied
                        ? (seat.isMuted
                              ? Icons.mic_off_rounded
                              : Icons.mic_rounded)
                        : pending
                        ? Icons.hourglass_top_rounded
                        : Icons.add_rounded,
                    color: Colors.white,
                    size: 22,
                  ),
                ),
                if (seat.isHostSeat)
                  const Positioned(
                    right: 0,
                    bottom: 0,
                    child: Icon(
                      Icons.star_rounded,
                      size: 14,
                      color: Color(0xFFFBBF24),
                    ),
                  ),
              ],
            ),
            const SizedBox(height: 8),
            Text(
              occupied || pending
                  ? (seat.user?.displayName ?? 'Seat ${seat.seatIndex}')
                  : (isHost ? 'Open' : 'Request'),
              maxLines: 1,
              overflow: TextOverflow.ellipsis,
              textAlign: TextAlign.center,
              style: Theme.of(context).textTheme.labelSmall?.copyWith(
                color: Colors.white,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
