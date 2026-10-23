import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/gifts/data/repositories/gift_repository_impl.dart';
import 'package:civin/features/gifts/domain/entities/gift.dart';
import 'package:civin/features/gifts/domain/repositories/gift_repository.dart';
import 'package:civin/features/gifts/presentation/gift_providers.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('giftCatalogProvider loads gifts and categories', () async {
    final _FakeGiftRepository repository = _FakeGiftRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [giftRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    final GiftCatalog catalog = await container.read(
      giftCatalogProvider.future,
    );
    expect(catalog.gifts.single.name, 'Rose');
    expect(catalog.categories.single.name, 'Classic');
  });

  test('giftProvider listens for GiftSent and queues animation', () async {
    final _FakeGiftRepository repository = _FakeGiftRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [giftRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    await container.read(giftProvider('room-1').notifier).startListening();
    expect(repository.connectedRoomId, 'room-1');
    expect(container.read(giftProvider('room-1')).isListening, isTrue);

    repository.emit(
      GiftSentEvent(
        transactionId: 'tx-1',
        roomId: 'room-1',
        sender: const GiftUser(
          id: 'u1',
          username: 'river',
          nickname: 'River',
        ),
        gift: const Gift(id: 'g1', name: 'Rose', coinPrice: 10),
        quantity: 3,
        coins: 30,
        createdAt: DateTime.utc(2026, 7, 22, 12),
      ),
    );
    await Future<void>.delayed(Duration.zero);

    final GiftRoomState state = container.read(giftProvider('room-1'));
    expect(state.currentAnimation?.gift.name, 'Rose');
    expect(state.currentAnimation?.sender.displayName, 'River');
    expect(state.currentAnimation?.quantity, 3);
    expect(state.history, hasLength(1));
  });

  test('giftSendingProvider sends gift and stores last transaction', () async {
    final _FakeGiftRepository repository = _FakeGiftRepository();
    final ProviderContainer container = ProviderContainer(
      overrides: [giftRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    final GiftSendingController controller = container.read(
      giftSendingProvider('room-1').notifier,
    );
    controller.selectGift('gift-1');
    controller.setQuantity(2);
    final bool ok = await controller.send();

    expect(ok, isTrue);
    expect(repository.sentGiftId, 'gift-1');
    expect(repository.sentQuantity, 2);
    expect(container.read(giftSendingProvider('room-1')).lastSent?.coins, 20);
  });

  test('giftSendingProvider surfaces send failures', () async {
    final _FakeGiftRepository repository = _FakeGiftRepository()
      ..failSend = true;
    final ProviderContainer container = ProviderContainer(
      overrides: [giftRepositoryProvider.overrideWithValue(repository)],
    );
    addTearDown(container.dispose);

    final bool ok = await container
        .read(giftSendingProvider('room-1').notifier)
        .send(giftId: 'gift-1');
    expect(ok, isFalse);
    expect(
      container.read(giftSendingProvider('room-1')).errorMessage,
      'Insufficient coins',
    );
  });
}

final class _FakeGiftRepository implements GiftRepository {
  // ignore: close_sinks
  final StreamController<GiftSentEvent> _giftSent =
      StreamController<GiftSentEvent>.broadcast();

  String? connectedRoomId;
  String? sentGiftId;
  int? sentQuantity;
  bool failSend = false;

  static const GiftCategory category = GiftCategory(
    id: 'cat-1',
    name: 'Classic',
  );
  static const Gift gift = Gift(
    id: 'gift-1',
    name: 'Rose',
    coinPrice: 10,
    category: category,
  );

  @override
  Future<RepositoryResult<GiftCatalog>> getCatalog() async =>
      const RepositorySuccess<GiftCatalog>(
        GiftCatalog(gifts: <Gift>[gift], categories: <GiftCategory>[category]),
      );

  @override
  Future<RepositoryResult<GiftTransaction>> sendGift(
    String roomId, {
    required String giftId,
    int quantity = 1,
    Map<String, dynamic>? metadata,
    String? clientRequestId,
  }) async {
    if (failSend) {
      return const RepositoryFailure<GiftTransaction>(
        AppFailure.network(message: 'Insufficient coins'),
      );
    }
    sentGiftId = giftId;
    sentQuantity = quantity;
    return RepositorySuccess<GiftTransaction>(
      GiftTransaction(
        id: 'tx-new',
        roomId: roomId,
        quantity: quantity,
        coins: gift.coinPrice * quantity,
        gift: gift,
        createdAt: DateTime.utc(2026, 7, 22),
      ),
    );
  }

  @override
  Future<RepositoryResult<List<GiftTransaction>>> getGiftHistory(
    String userId, {
    int perPage = 30,
  }) async => const RepositorySuccess<List<GiftTransaction>>(
    <GiftTransaction>[],
  );

  @override
  Stream<GiftSentEvent> watchGiftSent(String roomId) => _giftSent.stream;

  @override
  Future<void> connect(String roomId) async {
    connectedRoomId = roomId;
  }

  @override
  Future<void> disconnect() async {
    connectedRoomId = null;
  }

  void emit(GiftSentEvent event) => _giftSent.add(event);
}
