import 'package:civin/core/network/dio_client.dart';
import 'package:civin/core/router/router.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class AccountHubPage extends ConsumerWidget {
  const AccountHubPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    return Scaffold(
      appBar: AppBar(title: const Text('Settings')),
      body: ListView(
        children: <Widget>[
          ListTile(
            leading: const Icon(Icons.tune_rounded),
            title: const Text('Preferences'),
            subtitle: const Text('Theme, language, and notifications'),
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => const UserSettingsPage(),
              ),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.notifications_outlined),
            title: const Text('Notifications'),
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => const NotificationsPage(),
              ),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.devices_rounded),
            title: const Text('Devices'),
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(builder: (_) => const DevicesPage()),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.person_add_alt_rounded),
            title: const Text('Follow requests'),
            onTap: () => context.push(AppRoutes.followerRequests),
          ),
          ListTile(
            leading: const Icon(Icons.flag_outlined),
            title: const Text('Report history'),
            onTap: () => Navigator.of(context).push(
              MaterialPageRoute<void>(
                builder: (_) => const ReportHistoryPage(),
              ),
            ),
          ),
          ListTile(
            leading: const Icon(Icons.chat_bubble_outline_rounded),
            title: const Text('Messages'),
            onTap: () => context.push(AppRoutes.chats),
          ),
          ListTile(
            leading: const Icon(Icons.security_rounded),
            title: const Text('Account security'),
            onTap: () => context.push(AppRoutes.accountSecurity),
          ),
        ],
      ),
    );
  }
}

final class UserSettingsPage extends ConsumerStatefulWidget {
  const UserSettingsPage({super.key});

  @override
  ConsumerState<UserSettingsPage> createState() => _UserSettingsPageState();
}

final class _UserSettingsPageState extends ConsumerState<UserSettingsPage> {
  String _theme = 'system';
  bool _push = true;
  String _locale = 'en';
  bool _loading = true;
  String? _error;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    try {
      final Response<dynamic> response = await ref
          .read(dioClientProvider)
          .get<dynamic>('/api/v1/user-settings');
      final Object? data = response.data is Map
          ? (response.data as Map)['data']
          : null;
      final Map<String, dynamic> settings = data is Map
          ? Map<String, dynamic>.from(data)
          : <String, dynamic>{};
      if (!mounted) return;
      setState(() {
        _theme = settings['theme']?.toString() ?? 'system';
        _push = settings['push_enabled'] != false;
        _locale = settings['locale']?.toString() ?? 'en';
        _loading = false;
      });
    } on Object catch (error) {
      if (!mounted) return;
      setState(() {
        _error = error.toString();
        _loading = false;
      });
    }
  }

  Future<void> _save() async {
    await ref.read(dioClientProvider).put<dynamic>(
      '/api/v1/user-settings',
      data: <String, dynamic>{
        'settings': <String, dynamic>{
          'theme': _theme,
          'push_enabled': _push,
          'locale': _locale,
        },
      },
    );
    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Settings saved')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Preferences')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView(
              padding: const EdgeInsets.all(16),
              children: <Widget>[
                if (_error != null) Text(_error!),
                DropdownButtonFormField<String>(
                  initialValue: _theme,
                  decoration: const InputDecoration(labelText: 'Theme'),
                  items: const <DropdownMenuItem<String>>[
                    DropdownMenuItem(value: 'system', child: Text('System')),
                    DropdownMenuItem(value: 'light', child: Text('Light')),
                    DropdownMenuItem(value: 'dark', child: Text('Dark')),
                  ],
                  onChanged: (String? value) {
                    if (value != null) setState(() => _theme = value);
                  },
                ),
                SwitchListTile(
                  title: const Text('Push notifications'),
                  value: _push,
                  onChanged: (bool value) => setState(() => _push = value),
                ),
                TextFormField(
                  initialValue: _locale,
                  decoration: const InputDecoration(labelText: 'Locale'),
                  onChanged: (String value) => _locale = value,
                ),
                const SizedBox(height: 16),
                FilledButton(onPressed: _save, child: const Text('Save')),
              ],
            ),
    );
  }
}

final class NotificationsPage extends ConsumerStatefulWidget {
  const NotificationsPage({super.key});

  @override
  ConsumerState<NotificationsPage> createState() => _NotificationsPageState();
}

final class _NotificationsPageState extends ConsumerState<NotificationsPage> {
  List<Map<String, dynamic>> _items = <Map<String, dynamic>>[];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final Response<dynamic> response = await ref
        .read(dioClientProvider)
        .get<dynamic>('/api/v1/notifications');
    final Object? data = response.data is Map
        ? (response.data as Map)['data']
        : null;
    if (!mounted) return;
    setState(() {
      _items = data is List
          ? data
                .whereType<Map>()
                .map((Map item) => Map<String, dynamic>.from(item))
                .toList()
          : <Map<String, dynamic>>[];
      _loading = false;
    });
  }

  Future<void> _read(String id) async {
    await ref
        .read(dioClientProvider)
        .patch<dynamic>('/api/v1/notifications/$id/read');
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        title: const Text('Notifications'),
        actions: <Widget>[
          IconButton(
            tooltip: 'Mark all read',
            onPressed: () async {
              await ref
                  .read(dioClientProvider)
                  .patch<dynamic>('/api/v1/notifications/read-all');
              await _load();
            },
            icon: const Icon(Icons.done_all_rounded),
          ),
        ],
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView.builder(
              itemCount: _items.length,
              itemBuilder: (BuildContext context, int index) {
                final Map<String, dynamic> item = _items[index];
                final String id = item['id']?.toString() ?? '';
                final Object? payload = item['data'];
                return ListTile(
                  title: Text(item['type']?.toString() ?? 'Notification'),
                  subtitle: Text(payload?.toString() ?? ''),
                  trailing: item['read_at'] == null
                      ? IconButton(
                          onPressed: () => _read(id),
                          icon: const Icon(Icons.mark_email_read_outlined),
                        )
                      : null,
                );
              },
            ),
    );
  }
}

final class DevicesPage extends ConsumerStatefulWidget {
  const DevicesPage({super.key});

  @override
  ConsumerState<DevicesPage> createState() => _DevicesPageState();
}

final class _DevicesPageState extends ConsumerState<DevicesPage> {
  List<Map<String, dynamic>> _items = <Map<String, dynamic>>[];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final Response<dynamic> response = await ref
        .read(dioClientProvider)
        .get<dynamic>('/api/v1/devices');
    final Object? data = response.data is Map
        ? (response.data as Map)['data']
        : null;
    if (!mounted) return;
    setState(() {
      _items = data is List
          ? data
                .whereType<Map>()
                .map((Map item) => Map<String, dynamic>.from(item))
                .toList()
          : <Map<String, dynamic>>[];
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Devices')),
      body: ListView.builder(
        itemCount: _items.length,
        itemBuilder: (BuildContext context, int index) {
          final Map<String, dynamic> item = _items[index];
          final String id = item['id']?.toString() ?? '';
          return ListTile(
            title: Text(
              item['name']?.toString() ??
                  item['device_name']?.toString() ??
                  'Device',
            ),
            subtitle: Text(item['platform']?.toString() ?? ''),
            trailing: IconButton(
              icon: const Icon(Icons.delete_outline),
              onPressed: () async {
                await ref
                    .read(dioClientProvider)
                    .delete<dynamic>('/api/v1/devices/$id');
                await _load();
              },
            ),
          );
        },
      ),
    );
  }
}

final class FollowerRequestsPage extends ConsumerStatefulWidget {
  const FollowerRequestsPage({super.key});

  @override
  ConsumerState<FollowerRequestsPage> createState() =>
      _FollowerRequestsPageState();
}

final class _FollowerRequestsPageState
    extends ConsumerState<FollowerRequestsPage> {
  List<Map<String, dynamic>> _items = <Map<String, dynamic>>[];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final Response<dynamic> response = await ref
        .read(dioClientProvider)
        .get<dynamic>('/api/v1/follower-requests');
    final Object? data = response.data is Map
        ? (response.data as Map)['data']
        : null;
    if (!mounted) return;
    setState(() {
      _items = data is List
          ? data
                .whereType<Map>()
                .map((Map item) => Map<String, dynamic>.from(item))
                .toList()
          : <Map<String, dynamic>>[];
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Follow requests')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView.builder(
              itemCount: _items.length,
              itemBuilder: (BuildContext context, int index) {
                final Map<String, dynamic> item = _items[index];
                final String id = item['id']?.toString() ?? '';
                final Object? follower = item['user'] ?? item['follower'];
                final String name = follower is Map
                    ? (follower['nickname'] ?? follower['username'] ?? 'User')
                          .toString()
                    : 'User';
                return ListTile(
                  title: Text(name),
                  trailing: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: <Widget>[
                      IconButton(
                        tooltip: 'Accept',
                        onPressed: () async {
                          await ref
                              .read(dioClientProvider)
                              .post<dynamic>(
                                '/api/v1/follower-requests/$id/accept',
                              );
                          await _load();
                        },
                        icon: const Icon(Icons.check_rounded),
                      ),
                      IconButton(
                        tooltip: 'Reject',
                        onPressed: () async {
                          await ref
                              .read(dioClientProvider)
                              .delete<dynamic>('/api/v1/follower-requests/$id');
                          await _load();
                        },
                        icon: const Icon(Icons.close_rounded),
                      ),
                    ],
                  ),
                );
              },
            ),
    );
  }
}

final class ReportHistoryPage extends ConsumerStatefulWidget {
  const ReportHistoryPage({super.key});

  @override
  ConsumerState<ReportHistoryPage> createState() => _ReportHistoryPageState();
}

final class _ReportHistoryPageState extends ConsumerState<ReportHistoryPage> {
  List<Map<String, dynamic>> _items = <Map<String, dynamic>>[];

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final Response<dynamic> response = await ref
        .read(dioClientProvider)
        .get<dynamic>('/api/v1/reports/history');
    final Object? data = response.data is Map
        ? (response.data as Map)['data']
        : null;
    if (!mounted) return;
    setState(() {
      _items = data is List
          ? data
                .whereType<Map>()
                .map((Map item) => Map<String, dynamic>.from(item))
                .toList()
          : <Map<String, dynamic>>[];
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Report history')),
      body: ListView.builder(
        itemCount: _items.length,
        itemBuilder: (BuildContext context, int index) {
          final Map<String, dynamic> item = _items[index];
          return ListTile(
            title: Text(item['category']?.toString() ?? 'Report'),
            subtitle: Text(item['status']?.toString() ?? ''),
          );
        },
      ),
    );
  }
}

final class ChatListPage extends ConsumerStatefulWidget {
  const ChatListPage({super.key});

  @override
  ConsumerState<ChatListPage> createState() => _ChatListPageState();
}

final class _ChatListPageState extends ConsumerState<ChatListPage> {
  List<Map<String, dynamic>> _items = <Map<String, dynamic>>[];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    final Response<dynamic> response = await ref
        .read(dioClientProvider)
        .get<dynamic>('/api/v1/conversations');
    final Object? data = response.data is Map
        ? (response.data as Map)['data']
        : null;
    if (!mounted) return;
    setState(() {
      _items = data is List
          ? data
                .whereType<Map>()
                .map((Map item) => Map<String, dynamic>.from(item))
                .toList()
          : <Map<String, dynamic>>[];
      _loading = false;
    });
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Messages')),
      body: _loading
          ? const Center(child: CircularProgressIndicator())
          : ListView.builder(
              itemCount: _items.length,
              itemBuilder: (BuildContext context, int index) {
                final Map<String, dynamic> item = _items[index];
                final Object? other = item['other_user'];
                final String name = other is Map
                    ? (other['nickname'] ?? other['username'] ?? 'Chat')
                          .toString()
                    : 'Chat';
                return ListTile(
                  title: Text(name),
                  subtitle: Text(item['last_message_at']?.toString() ?? ''),
                  onTap: () => context.push(
                    AppRoutes.chatPath(item['id']?.toString() ?? ''),
                  ),
                );
              },
            ),
    );
  }
}
