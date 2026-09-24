import 'package:civin/features/games/presentation/screens/games_home_screen.dart';
import 'package:flutter/material.dart';

/// Floating games entry for viewers and hosts inside a live room.
final class GameActionButton extends StatelessWidget {
  const GameActionButton({required this.roomId, super.key});

  final String roomId;

  @override
  Widget build(BuildContext context) {
    return SafeArea(
      child: Align(
        alignment: Alignment.bottomCenter,
        child: Padding(
          padding: const EdgeInsets.only(bottom: 168),
          child: FloatingActionButton.extended(
            heroTag: 'games-fab-$roomId',
            onPressed: () {
              showModalBottomSheet<void>(
                context: context,
                isScrollControlled: true,
                backgroundColor: Colors.transparent,
                builder: (BuildContext context) => SizedBox(
                  height: MediaQuery.sizeOf(context).height * 0.78,
                  child: GamesHomeScreen(roomId: roomId, embedded: true),
                ),
              );
            },
            icon: const Icon(Icons.sports_esports_rounded),
            label: const Text('Games'),
          ),
        ),
      ),
    );
  }
}
