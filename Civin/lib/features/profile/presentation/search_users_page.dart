import 'dart:async';

import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/empty_widget.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/core/widgets/loading_widget.dart';
import 'package:civin/core/widgets/text_field.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';
import 'package:civin/features/profile/presentation/social_providers.dart';
import 'package:civin/features/profile/widgets/social_widgets.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class SearchUsersPage extends ConsumerStatefulWidget {
  const SearchUsersPage({super.key});

  @override
  ConsumerState<SearchUsersPage> createState() => _SearchUsersPageState();
}

final class _SearchUsersPageState extends ConsumerState<SearchUsersPage> {
  final TextEditingController _query = TextEditingController();
  final ScrollController _scrollController = ScrollController();
  Timer? _debounce;
  bool _hasQuery = false;
  bool _loadingMore = false;

  @override
  void initState() {
    super.initState();
    _scrollController.addListener(_loadMore);
  }

  @override
  void dispose() {
    _debounce?.cancel();
    _query.dispose();
    _scrollController
      ..removeListener(_loadMore)
      ..dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final AsyncValue<PagedResult<SocialUser>> state = ref.watch(
      searchUsersProvider,
    );
    return Scaffold(
      appBar: AppBar(title: const Text('Search users')),
      body: SocialPageWidth(
        child: Column(
          children: <Widget>[
            Padding(
              padding: const EdgeInsets.fromLTRB(16, 8, 16, 12),
              child: AppTextField(
                controller: _query,
                hint: 'Username or nickname',
                prefixIcon: const Icon(Icons.search_rounded),
                suffixIcon: _hasQuery
                    ? IconButton(
                        tooltip: 'Clear',
                        onPressed: _clear,
                        icon: const Icon(Icons.close_rounded),
                      )
                    : null,
                textInputAction: TextInputAction.search,
                onChanged: _onQueryChanged,
                onSubmitted: _search,
              ),
            ),
            Expanded(
              child: state.when(
                loading: () =>
                    const AppLoadingWidget(message: 'Searching users'),
                error: (Object error, StackTrace stackTrace) => AppErrorWidget(
                  message: error.toString(),
                  onRetry: () => _search(_query.text),
                ),
                data: (PagedResult<SocialUser> page) {
                  if (!_hasQuery) {
                    return const EmptyStateWidget(
                      message: 'Search by username or nickname.',
                      icon: Icons.person_search_rounded,
                    );
                  }
                  if (page.items.isEmpty) {
                    return const EmptyStateWidget(
                      message: 'No users match your search.',
                      icon: Icons.search_off_rounded,
                    );
                  }
                  return ListView.builder(
                    controller: _scrollController,
                    keyboardDismissBehavior:
                        ScrollViewKeyboardDismissBehavior.onDrag,
                    itemCount: page.items.length + (page.hasMore ? 1 : 0),
                    itemBuilder: (BuildContext context, int index) {
                      if (index == page.items.length) {
                        return const Padding(
                          padding: EdgeInsets.all(20),
                          child: Center(
                            child: CircularProgressIndicator.adaptive(),
                          ),
                        );
                      }
                      final SocialUser user = page.items[index];
                      return SocialUserTile(
                        user: user,
                        onTap: () =>
                            context.push(AppRoutes.userDetailsPath(user.id)),
                      );
                    },
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _onQueryChanged(String value) {
    _debounce?.cancel();
    final bool hasQuery = value.trim().isNotEmpty;
    if (_hasQuery != hasQuery) setState(() => _hasQuery = hasQuery);
    _debounce = Timer(const Duration(milliseconds: 350), () => _search(value));
  }

  void _clear() {
    _debounce?.cancel();
    _query.clear();
    setState(() => _hasQuery = false);
    ref.read(searchUsersProvider.notifier).search('');
  }

  Future<void> _search(String value) async {
    final bool hasQuery = value.trim().isNotEmpty;
    if (mounted && _hasQuery != hasQuery) {
      setState(() => _hasQuery = hasQuery);
    }
    await ref.read(searchUsersProvider.notifier).search(value);
  }

  Future<void> _loadMore() async {
    if (_loadingMore ||
        !_scrollController.hasClients ||
        _scrollController.position.extentAfter >= 320) {
      return;
    }
    _loadingMore = true;
    await ref.read(searchUsersProvider.notifier).loadMore();
    _loadingMore = false;
  }
}
