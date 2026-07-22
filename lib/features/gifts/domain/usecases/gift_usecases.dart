import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/gifts/domain/entities/gift.dart';
import 'package:civin/features/gifts/domain/repositories/gift_repository.dart';

final class GetGiftCatalog {
  const GetGiftCatalog(this._repository);

  final GiftRepository _repository;

  Future<RepositoryResult<GiftCatalog>> call() => _repository.getCatalog();
}

final class SendGift {
  const SendGift(this._repository);

  final GiftRepository _repository;

  Future<RepositoryResult<GiftTransaction>> call(
    String roomId, {
    required String giftId,
    int quantity = 1,
    Map<String, dynamic>? metadata,
    String? clientRequestId,
  }) => _repository.sendGift(
    roomId,
    giftId: giftId,
    quantity: quantity,
    metadata: metadata,
    clientRequestId: clientRequestId,
  );
}

final class GetGiftHistory {
  const GetGiftHistory(this._repository);

  final GiftRepository _repository;

  Future<RepositoryResult<List<GiftTransaction>>> call(
    String userId, {
    int perPage = 30,
  }) => _repository.getGiftHistory(userId, perPage: perPage);
}

final class ConnectGiftRealtime {
  const ConnectGiftRealtime(this._repository);

  final GiftRepository _repository;

  Future<void> call(String roomId) => _repository.connect(roomId);
}

final class DisconnectGiftRealtime {
  const DisconnectGiftRealtime(this._repository);

  final GiftRepository _repository;

  Future<void> call() => _repository.disconnect();
}
