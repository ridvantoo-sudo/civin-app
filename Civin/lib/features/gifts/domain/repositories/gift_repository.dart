import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/gifts/domain/entities/gift.dart';

abstract interface class GiftRepository {
  Future<RepositoryResult<GiftCatalog>> getCatalog();

  Future<RepositoryResult<GiftTransaction>> sendGift(
    String roomId, {
    required String giftId,
    int quantity = 1,
    Map<String, dynamic>? metadata,
    String? clientRequestId,
  });

  Future<RepositoryResult<List<GiftTransaction>>> getGiftHistory(
    String userId, {
    int perPage = 30,
  });

  Stream<GiftSentEvent> watchGiftSent(String roomId);

  Future<void> connect(String roomId);

  Future<void> disconnect();
}
