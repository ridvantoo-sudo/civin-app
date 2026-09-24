import 'package:civin/core/constants/strings.dart';
import 'package:civin/core/router/router.dart';
import 'package:civin/features/live/presentation/screens/live_home_screen.dart';
import 'package:civin/features/profile/presentation/profile_page.dart';
import 'package:civin/features/profile/presentation/search_users_page.dart';
import 'package:civin/features/voice_rooms/presentation/screens/voice_room_home_screen.dart';
import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

final class HomePage extends StatefulWidget {
  const HomePage({super.key});

  @override
  State<HomePage> createState() => _HomePageState();
}

final class _HomePageState extends State<HomePage> {
  int _index = 0;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // IndexedStack keeps tab state and avoids rebuild/layout races that
      // nested switch-built Scaffolds can trigger during tab changes.
      body: IndexedStack(
        index: _index,
        children: const <Widget>[
          _FeedTab(),
          LiveHomeScreen(),
          VoiceRoomHome(),
          SearchUsersPage(),
          ProfilePage(),
        ],
      ),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (int value) => setState(() => _index = value),
        destinations: const <NavigationDestination>[
          NavigationDestination(
            icon: Icon(Icons.home_outlined),
            selectedIcon: Icon(Icons.home_rounded),
            label: 'Feed',
          ),
          NavigationDestination(
            icon: Icon(Icons.live_tv_outlined),
            selectedIcon: Icon(Icons.live_tv_rounded),
            label: 'Live',
          ),
          NavigationDestination(
            icon: Icon(Icons.mic_none_rounded),
            selectedIcon: Icon(Icons.mic_rounded),
            label: 'Voice',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_search_outlined),
            selectedIcon: Icon(Icons.person_search_rounded),
            label: 'Discover',
          ),
          NavigationDestination(
            icon: Icon(Icons.person_outline_rounded),
            selectedIcon: Icon(Icons.person_rounded),
            label: 'Profile',
          ),
        ],
      ),
    );
  }
}

final class _FeedTab extends StatelessWidget {
  const _FeedTab();

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text(AppStrings.appName),
        actions: <Widget>[
          IconButton(
            tooltip: 'Agency',
            onPressed: () => context.push(AppRoutes.agency),
            icon: const Icon(Icons.apartment_outlined),
          ),
          IconButton(
            tooltip: 'VIP',
            onPressed: () => context.push(AppRoutes.vip),
            icon: const Icon(Icons.workspace_premium_outlined),
          ),
          IconButton(
            tooltip: 'Rankings',
            onPressed: () => context.push(AppRoutes.rankings),
            icon: const Icon(Icons.emoji_events_outlined),
          ),
          IconButton(
            tooltip: 'Wallet',
            onPressed: () => context.push(AppRoutes.wallet),
            icon: const Icon(Icons.account_balance_wallet_outlined),
          ),
        ],
      ),
      body: SafeArea(
        child: ListView(
          padding: const EdgeInsets.all(20),
          children: <Widget>[
            Text(
              'Play, finish challenges, and stay with your family.',
              style: Theme.of(context).textTheme.titleMedium,
            ),
            const SizedBox(height: 16),
            _HubTile(
              icon: Icons.sports_esports_rounded,
              title: 'Games',
              subtitle: 'Wheel, slots, dice, quiz, and envelopes',
              onTap: () => context.push(AppRoutes.games),
            ),
            _HubTile(
              icon: Icons.flag_rounded,
              title: 'Challenges',
              subtitle: 'Daily and weekly rewards',
              onTap: () => context.push(AppRoutes.challenges),
            ),
            _HubTile(
              icon: Icons.groups_rounded,
              title: 'Family',
              subtitle: 'Chat, missions, and invites',
              onTap: () => context.push(AppRoutes.family),
            ),
            _HubTile(
              icon: Icons.notifications_rounded,
              title: 'Notifications',
              subtitle: 'Follows, gifts, and live alerts',
              onTap: () => context.push(AppRoutes.notifications),
            ),
          ],
        ),
      ),
    );
  }
}

final class _HubTile extends StatelessWidget {
  const _HubTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.onTap,
  });

  final IconData icon;
  final String title;
  final String subtitle;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      child: ListTile(
        leading: Icon(icon),
        title: Text(title),
        subtitle: Text(subtitle),
        trailing: const Icon(Icons.chevron_right_rounded),
        onTap: onTap,
      ),
    );
  }
}
