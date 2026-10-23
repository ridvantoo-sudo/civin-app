import 'package:civin/core/network/dio_client.dart';
import 'package:dio/dio.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final class ChatThreadPage extends ConsumerStatefulWidget {
  const ChatThreadPage({required this.conversationId, super.key});

  final String conversationId;

  @override
  ConsumerState<ChatThreadPage> createState() => _ChatThreadPageState();
}

final class _ChatThreadPageState extends ConsumerState<ChatThreadPage> {
  final TextEditingController _body = TextEditingController();
  List<Map<String, dynamic>> _items = <Map<String, dynamic>>[];
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _body.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    final Response<dynamic> response = await ref
        .read(dioClientProvider)
        .get<dynamic>(
          '/api/v1/conversations/${widget.conversationId}/messages',
        );
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
                .reversed
                .toList()
          : <Map<String, dynamic>>[];
      _loading = false;
    });
  }

  Future<void> _send() async {
    final String text = _body.text.trim();
    if (text.isEmpty) return;
    _body.clear();
    await ref.read(dioClientProvider).post<dynamic>(
      '/api/v1/conversations/${widget.conversationId}/messages',
      data: <String, dynamic>{'body': text},
    );
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(title: const Text('Chat')),
      body: Column(
        children: <Widget>[
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator())
                : ListView.builder(
                    itemCount: _items.length,
                    itemBuilder: (BuildContext context, int index) {
                      final Map<String, dynamic> item = _items[index];
                      return ListTile(
                        title: Text(item['body']?.toString() ?? ''),
                        subtitle: Text(item['created_at']?.toString() ?? ''),
                      );
                    },
                  ),
          ),
          SafeArea(
            child: Row(
              children: <Widget>[
                Expanded(
                  child: TextField(
                    controller: _body,
                    decoration: const InputDecoration(hintText: 'Message'),
                    onSubmitted: (_) => _send(),
                  ),
                ),
                IconButton(
                  onPressed: _send,
                  icon: const Icon(Icons.send_rounded),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
