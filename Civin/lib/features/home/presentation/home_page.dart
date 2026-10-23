import 'package:civin/core/constants/strings.dart';
import 'package:civin/core/network/dio_client.dart';
import 'package:civin/core/router/router.dart';
import 'package:civin/features/live/presentation/screens/live_home_screen.dart';
import 'package:civin/features/profile/presentation/profile_page.dart';
import 'package:civin/features/profile/presentation/search_users_page.dart';
import 'package:civin/features/voice_rooms/presentation/screens/voice_room_home_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class HomePage extends ConsumerStatefulWidget {
  const HomePage({super.key});

  @override
  ConsumerState<HomePage> createState() => _HomePageState();
}

final class _HomePageState extends ConsumerState<HomePage>
    with WidgetsBindingObserver {
  int _index = 0;

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addObserver(this);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _setOnline(true);
    });
  }

  @override
  void dispose() {
    WidgetsBinding.instance.removeObserver(this);
    super.dispose();
  }

  @override
  void didChangeAppLifecycleState(AppLifecycleState state) {
    switch (state) {
      case AppLifecycleState.resumed:
        _setOnline(true);
      case AppLifecycleState.inactive:
        break;
      case AppLifecycleState.hidden:
      case AppLifecycleState.paused:
      case AppLifecycleState.detached:
        _setOnline(false);
    }
  }

  Future<void> _setOnline(bool online) async {
    try {
      await ref.read(dioClientProvider).patch<dynamic>(
        '/api/v1/user-status',
        data: <String, dynamic>{'is_online': online},
      );
    } on Object {
      // Presence is best-effort and must not block the shell.
    }
  }

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
      body: const LiveHomeScreen(embedded: true),
    );
  }
}
