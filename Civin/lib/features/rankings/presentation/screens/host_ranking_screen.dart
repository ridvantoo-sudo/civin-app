import 'package:civin/features/rankings/domain/entities/ranking.dart';
import 'package:civin/features/rankings/presentation/widgets/ranking_leaderboard.dart';
import 'package:flutter/material.dart';

final class HostRanking extends StatelessWidget {
  const HostRanking({super.key});

  @override
  Widget build(BuildContext context) {
    return Theme(
      data: rankingDarkTheme(context),
      child: Scaffold(
        appBar: AppBar(title: Text(RankingType.host.title)),
        body: const RankingLeaderboard(type: RankingType.host),
      ),
    );
  }
}
