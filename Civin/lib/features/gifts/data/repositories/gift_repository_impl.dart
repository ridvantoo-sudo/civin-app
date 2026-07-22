import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/gifts/data/datasources/gift_realtime_data_source.dart';
import 'package:civin/features/gifts/data/datasources/gift_remote_data_source.dart';
import 'package:civin/features/gifts/domain/entities/gift.dart';
import 'package:civin/features/gifts/domain/repositories/gift_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<GiftRepository> giftRepositoryProvider =
    Provider<GiftRepository>(
      (Ref ref) => GiftRepositoryImpl(
        ref.watch(giftRemoteDataSourceProvider),
        ref.watch(giftRealtimeDataSourceProvider),
      ),
    );

final class GiftRepositoryImpl extends BaseRepository implements GiftRepository {
  GiftRepositoryImpl(this._remote, this._realtime);

  final GiftRemoteDataSource _remote;
  final GiftRealtimeDataSource _realtime;

  @override
  Future<RepositoryResult<GiftCatalog>> getCatalog() =>
      execute(_remote.getCatalog);

  @override
  Future<RepositoryResult<GiftTransaction>> sendGift(
    String roomId, {
    required String giftId,
    int quantity = 1,
    Map<String, dynamic>? metadata,
    String? clientRequestId,
  }) => execute(
    () => _remote.sendGift(
      roomId,
      giftId: giftId,
      quantity: quantity,
      metadata: metadata,
      clientRequestId: clientRequestId,
    ),
  );

  @override
  Future<RepositoryResult<List<GiftTransaction>>> getGiftHistory(
    String userId, {
    int perPage = 30,
  }) => execute(() => _remote.getGiftHistory(userId, perPage: perPage));

  @override
  Stream<GiftSentEvent> watchGiftSent(String roomId) => _realtime.giftSent;

  @override
  Future<void> connect(String roomId) => _realtime.connect(roomId);

  @override
  Future<void> disconnect() => _realtime.disconnect();
}
