import 'dart:async';

import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/features/family/domain/entities/family_club.dart';
import 'package:civin/features/family/presentation/family_providers.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class FamilyHomeScreen extends ConsumerStatefulWidget {
  const FamilyHomeScreen({super.key});

  @override
  ConsumerState<FamilyHomeScreen> createState() => _FamilyHomeScreenState();
}

final class _FamilyHomeScreenState extends ConsumerState<FamilyHomeScreen> {
  final TextEditingController _name = TextEditingController();
  final TextEditingController _description = TextEditingController();
  final TextEditingController _code = TextEditingController();
  final TextEditingController _message = TextEditingController();

  @override
  void initState() {
    super.initState();
    WidgetsBinding.instance.addPostFrameCallback((_) {
      ref.read(familyProvider.notifier).load();
    });
  }

  @override
  void dispose() {
    _name.dispose();
    _description.dispose();
    _code.dispose();
    _message.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final FamilyViewState view = ref.watch(familyProvider);
    final FamilyClub? family = view.family;
    return Scaffold(
      appBar: AppBar(title: Text(family?.name ?? 'Family')),
      body: view.isLoading && family == null
          ? const AppLoadingWidget(message: 'Loading family')
          : view.errorMessage != null && family == null && view.rankings.isEmpty
          ? AppErrorWidget(
              message: view.errorMessage!,
              onRetry: () => ref.read(familyProvider.notifier).load(),
            )
          : family == null
          ? _JoinOrCreate(
              name: _name,
              description: _description,
              code: _code,
              rankings: view.rankings,
              isSaving: view.isSaving,
              errorMessage: view.errorMessage,
              onCreate: () => ref
                  .read(familyProvider.notifier)
                  .create(
                    name: _name.text.trim(),
                    description: _description.text.trim(),
                  ),
              onJoin: () =>
                  ref.read(familyProvider.notifier).join(_code.text.trim()),
            )
          : _FamilyTabs(
              view: view,
              message: _message,
              onInvite: () => ref.read(familyProvider.notifier).createInvite(),
              onSend: () async {
                final bool ok = await ref
                    .read(familyProvider.notifier)
                    .sendMessage(_message.text.trim());
                if (ok) _message.clear();
              },
              onClaim: (String id) =>
                  ref.read(familyProvider.notifier).claimMission(id),
              onRefresh: () => ref.read(familyProvider.notifier).load(),
            ),
    );
  }
}

final class _JoinOrCreate extends StatelessWidget {
  const _JoinOrCreate({
    required this.name,
    required this.description,
    required this.code,
    required this.rankings,
    required this.isSaving,
    required this.onCreate,
    required this.onJoin,
    this.errorMessage,
  });

  final TextEditingController name;
  final TextEditingController description;
  final TextEditingController code;
  final List<FamilyClub> rankings;
  final bool isSaving;
  final String? errorMessage;
  final Future<bool> Function() onCreate;
  final Future<bool> Function() onJoin;

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(16),
      children: <Widget>[
        Text(
          'Create a family or join one with an invite code.',
          style: Theme.of(context).textTheme.bodyLarge,
        ),
        if (errorMessage != null) ...<Widget>[
          const SizedBox(height: 12),
          Text(
            errorMessage!,
            style: TextStyle(color: Theme.of(context).colorScheme.error),
          ),
        ],
        const SizedBox(height: 16),
        TextField(
          controller: name,
          decoration: const InputDecoration(
            labelText: 'Family name',
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 12),
        TextField(
          controller: description,
          minLines: 2,
          maxLines: 4,
          decoration: const InputDecoration(
            labelText: 'Description',
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 12),
        FilledButton(
          onPressed: isSaving ? null : () => unawaited(onCreate()),
          child: const Text('Create family'),
        ),
        const SizedBox(height: 24),
        TextField(
          controller: code,
          textCapitalization: TextCapitalization.characters,
          decoration: const InputDecoration(
            labelText: 'Invite code',
            border: OutlineInputBorder(),
          ),
        ),
        const SizedBox(height: 12),
        OutlinedButton(
          onPressed: isSaving ? null : () => unawaited(onJoin()),
          child: const Text('Join with code'),
        ),
        if (rankings.isNotEmpty) ...<Widget>[
          const SizedBox(height: 24),
          Text('Rankings', style: Theme.of(context).textTheme.titleMedium),
          ...rankings.map(
            (FamilyClub club) => ListTile(
              contentPadding: EdgeInsets.zero,
              title: Text(club.name),
              subtitle: Text(
                'Level ${club.level} · ${club.membersCount} members',
              ),
              trailing: Text('${club.walletCoins}'),
            ),
          ),
        ],
      ],
    );
  }
}

final class _FamilyTabs extends StatelessWidget {
  const _FamilyTabs({
    required this.view,
    required this.message,
    required this.onInvite,
    required this.onSend,
    required this.onClaim,
    required this.onRefresh,
  });

  final FamilyViewState view;
  final TextEditingController message;
  final VoidCallback onInvite;
  final Future<void> Function() onSend;
  final Future<void> Function(String id) onClaim;
  final Future<void> Function() onRefresh;

  @override
  Widget build(BuildContext context) {
    final FamilyClub family = view.family!;
    return DefaultTabController(
      length: 3,
      child: Column(
        children: <Widget>[
          ListTile(
            title: Text(family.name),
            subtitle: Text(
              'Level ${family.level} · ${family.membersCount}/${family.maxMembers} · ${family.walletCoins} coins',
            ),
            trailing: IconButton(
              tooltip: 'Invite code',
              onPressed: view.isSaving ? null : onInvite,
              icon: const Icon(Icons.person_add_alt_rounded),
            ),
          ),
          if (view.inviteCode != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Row(
                children: <Widget>[
                  Expanded(child: Text('Invite code ${view.inviteCode}')),
                  IconButton(
                    tooltip: 'Copy code',
                    onPressed: () {
                      unawaited(
                        Clipboard.setData(
                          ClipboardData(text: view.inviteCode!),
                        ),
                      );
                    },
                    icon: const Icon(Icons.copy_rounded),
                  ),
                ],
              ),
            ),
          if (view.errorMessage != null)
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Text(
                view.errorMessage!,
                style: TextStyle(color: Theme.of(context).colorScheme.error),
              ),
            ),
          const TabBar(
            tabs: <Widget>[
              Tab(text: 'Chat'),
              Tab(text: 'Missions'),
              Tab(text: 'Members'),
            ],
          ),
          Expanded(
            child: TabBarView(
              children: <Widget>[
                Column(
                  children: <Widget>[
                    Expanded(
                      child: RefreshIndicator.adaptive(
                        onRefresh: onRefresh,
                        child: ListView(
                          reverse: true,
                          physics: const AlwaysScrollableScrollPhysics(),
                          padding: const EdgeInsets.all(16),
                          children: view.messages.reversed
                              .map(
                                (FamilyMessageView item) => ListTile(
                                  contentPadding: EdgeInsets.zero,
                                  title: Text(item.author?.label ?? 'Member'),
                                  subtitle: Text(item.body),
                                ),
                              )
                              .toList(growable: false),
                        ),
                      ),
                    ),
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 0, 16, 16),
                      child: Row(
                        children: <Widget>[
                          Expanded(
                            child: TextField(
                              controller: message,
                              decoration: const InputDecoration(
                                hintText: 'Message the family',
                                border: OutlineInputBorder(),
                              ),
                            ),
                          ),
                          IconButton(
                            tooltip: 'Send',
                            onPressed: view.isSaving
                                ? null
                                : () => unawaited(onSend()),
                            icon: const Icon(Icons.send_rounded),
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                RefreshIndicator.adaptive(
                  onRefresh: onRefresh,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: view.missions
                        .map(
                          (FamilyMissionView mission) => ListTile(
                            title: Text(mission.title),
                            subtitle: Text(
                              '${mission.progress}/${mission.targetValue} · ${mission.rewardCoins} coins',
                            ),
                            trailing: FilledButton(
                              onPressed: !mission.isClaimable || view.isSaving
                                  ? null
                                  : () => unawaited(onClaim(mission.id)),
                              child: Text(
                                mission.claimedAt == null ? 'Claim' : 'Claimed',
                              ),
                            ),
                          ),
                        )
                        .toList(growable: false),
                  ),
                ),
                RefreshIndicator.adaptive(
                  onRefresh: onRefresh,
                  child: ListView(
                    physics: const AlwaysScrollableScrollPhysics(),
                    children: view.members
                        .map(
                          (FamilyMemberView member) => ListTile(
                            title: Text(member.user?.label ?? 'Member'),
                            subtitle: Text('${member.role} · ${member.status}'),
                            trailing: Text('${member.contributionXp} XP'),
                          ),
                        )
                        .toList(growable: false),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
