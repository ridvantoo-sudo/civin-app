import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/live_chat/data/repositories/live_chat_repository_impl.dart';
import 'package:civin/features/live_chat/domain/entities/live_message.dart';
import 'package:civin/features/live_chat/domain/repositories/live_chat_repository.dart';
import 'package:civin/features/live_chat/presentation/widgets/live_chat_message_tile.dart';
import 'package:civin/features/live_chat/presentation/widgets/live_chat_panel.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('live chat panel renders messages, username, and join lines', (
    WidgetTester tester,
  ) async {
    final _FakeChatRepository repository = _FakeChatRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          liveChatRepositoryProvider.overrideWithValue(repository),
        ],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData.dark(useMaterial3: true),
          home: const Scaffold(
            body: LiveChatPanel(roomId: 'room-1', canModerate: true),
          ),
        ),
      ),
    );

    await tester.pump();
    await tester.pump(const Duration(milliseconds: 50));
    await tester.pumpAndSettle();

    expect(find.text('Live chat'), findsOneWidget);
    expect(find.text('River'), findsOneWidget);
    expect(find.text('Hello world'), findsOneWidget);
    expect(find.text('River joined'), findsOneWidget);
    expect(find.byType(LiveChatMessageTile), findsWidgets);
  });

  testWidgets('sending a message clears the input field', (
    WidgetTester tester,
  ) async {
    final _FakeChatRepository repository = _FakeChatRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [
          liveChatRepositoryProvider.overrideWithValue(repository),
        ],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData.dark(useMaterial3: true),
          home: const Scaffold(
            body: LiveChatPanel(roomId: 'room-1', canModerate: true),
          ),
        ),
      ),
    );
    await tester.pumpAndSettle();

    await tester.enterText(find.byType(TextField), 'Nice stream');
    await tester.tap(find.byTooltip('Send'));
    await tester.pumpAndSettle();

    expect(find.text('Nice stream'), findsOneWidget);
    expect(
      tester.widget<TextField>(find.byType(TextField)).controller?.text,
      isEmpty,
    );
  });
}

final class _FakeChatRepository implements LiveChatRepository {
  // ignore: close_sinks
  final StreamController<LiveMessage> _messages =
      StreamController<LiveMessage>.broadcast();
  // ignore: close_sinks
  final StreamController<String> _deleted = StreamController<String>.broadcast();
  // ignore: close_sinks
  final StreamController<ChatConnectionStatus> _connection =
      StreamController<ChatConnectionStatus>.broadcast();

  @override
  Future<RepositoryResult<List<LiveMessage>>> getMessages(
    String roomId, {
    int perPage = 50,
  }) async => RepositorySuccess<List<LiveMessage>>(<LiveMessage>[
    LiveMessage(
      id: 'join-1',
      roomId: roomId,
      message: 'River joined',
      type: LiveMessageType.join,
      createdAt: DateTime.utc(2026, 7, 22),
      user: const LiveChatUser(id: 'u1', username: 'river', nickname: 'River'),
    ),
    LiveMessage(
      id: 'msg-1',
      roomId: roomId,
      message: 'Hello world',
      type: LiveMessageType.text,
      createdAt: DateTime.utc(2026, 7, 22, 1),
      user: const LiveChatUser(id: 'u1', username: 'river', nickname: 'River'),
    ),
  ]);

  @override
  Future<RepositoryResult<LiveMessage>> sendMessage(
    String roomId, {
    required String message,
    Map<String, dynamic>? metadata,
  }) async => RepositorySuccess<LiveMessage>(
    LiveMessage(
      id: 'msg-${message.hashCode}',
      roomId: roomId,
      message: message,
      type: LiveMessageType.text,
      createdAt: DateTime.utc(2026, 7, 22, 2),
      user: const LiveChatUser(id: 'u1', username: 'river', nickname: 'River'),
    ),
  );

  @override
  Future<RepositoryResult<void>> deleteMessage(
    String roomId,
    String messageId,
  ) async => const RepositorySuccess<void>(null);

  @override
  Stream<LiveMessage> watchMessages(String roomId) => _messages.stream;

  @override
  Stream<String> watchDeletedMessageIds(String roomId) => _deleted.stream;

  @override
  Stream<ChatConnectionStatus> watchConnection(String roomId) =>
      _connection.stream;

  @override
  Future<void> connect(String roomId, {required bool canModerate}) async {
    _connection.add(ChatConnectionStatus.connected);
  }

  @override
  Future<void> disconnect() async {
    _connection.add(ChatConnectionStatus.disconnected);
  }
}
