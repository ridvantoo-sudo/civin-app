import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/live_chat/data/datasources/live_chat_realtime_data_source.dart';
import 'package:civin/features/live_chat/data/datasources/live_chat_remote_data_source.dart';
import 'package:civin/features/live_chat/domain/entities/live_message.dart';
import 'package:civin/features/live_chat/domain/repositories/live_chat_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<LiveChatRepository> liveChatRepositoryProvider =
    Provider<LiveChatRepository>(
      (Ref ref) => LiveChatRepositoryImpl(
        ref.watch(liveChatRemoteDataSourceProvider),
        ref.watch(liveChatRealtimeDataSourceProvider),
      ),
    );

final class LiveChatRepositoryImpl extends BaseRepository
    implements LiveChatRepository {
  LiveChatRepositoryImpl(this._remote, this._realtime);

  final LiveChatRemoteDataSource _remote;
  final LiveChatRealtimeDataSource _realtime;

  @override
  Future<RepositoryResult<List<LiveMessage>>> getMessages(
    String roomId, {
    int perPage = 50,
  }) => execute(() => _remote.getMessages(roomId, perPage: perPage));

  @override
  Future<RepositoryResult<LiveMessage>> sendMessage(
    String roomId, {
    required String message,
    Map<String, dynamic>? metadata,
  }) => execute(
    () => _remote.sendMessage(
      roomId,
      message: message,
      metadata: metadata,
    ),
  );

  @override
  Future<RepositoryResult<void>> deleteMessage(
    String roomId,
    String messageId,
  ) => execute(() => _remote.deleteMessage(roomId, messageId));

  @override
  Stream<LiveMessage> watchMessages(String roomId) => _realtime.messages;

  @override
  Stream<String> watchDeletedMessageIds(String roomId) =>
      _realtime.deletedMessageIds;

  @override
  Stream<ChatConnectionStatus> watchConnection(String roomId) =>
      _realtime.connectionStates;

  @override
  Future<void> connect(String roomId, {required bool canModerate}) =>
      _realtime.connect(roomId);

  @override
  Future<void> disconnect() => _realtime.disconnect();
}
