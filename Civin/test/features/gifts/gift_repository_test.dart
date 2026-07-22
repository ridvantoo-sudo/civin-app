import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/gifts/data/datasources/gift_realtime_data_source.dart';
import 'package:civin/features/gifts/data/datasources/gift_remote_data_source.dart';
import 'package:civin/features/gifts/data/repositories/gift_repository_impl.dart';
import 'package:civin/features/gifts/domain/entities/gift.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  late _FakeRemote remote;
  late _FakeRealtime realtime;
  late GiftRepositoryImpl repository;

  setUp(() {
    remote = _FakeRemote();
    realtime = _FakeRealtime();
    repository = GiftRepositoryImpl(remote, realtime);
  });

  test('loads catalog and sends gift through remote datasource', () async {
    final RepositoryResult<GiftCatalog> catalog = await repository.getCatalog();
    final RepositoryResult<GiftTransaction> sent = await repository.sendGift(
      'room-1',
      giftId: 'gift-1',
      quantity: 2,
    );

    expect(catalog, isA<RepositorySuccess<GiftCatalog>>());
    expect(
      (catalog as RepositorySuccess<GiftCatalog>).data.gifts.single.name,
      'Rose',
    );
    expect(catalog.data.categories.single.name, 'Classic');
    expect(sent, isA<RepositorySuccess<GiftTransaction>>());
    expect(remote.sentGiftId, 'gift-1');
    expect(remote.sentQuantity, 2);
  });

  test('loads gift history and maps remote failures', () async {
    final RepositoryResult<List<GiftTransaction>> history = await repository
        .getGiftHistory('user-1');
    expect(history, isA<RepositorySuccess<List<GiftTransaction>>>());
    expect(
      (history as RepositorySuccess<List<GiftTransaction>>).data.single.coins,
      20,
    );

    remote.error = DioException(
      requestOptions: RequestOptions(path: '/api/v1/live/room-1/gifts/send'),
      response: Response<dynamic>(
        requestOptions: RequestOptions(path: '/api/v1/live/room-1/gifts/send'),
        statusCode: 422,
        data: <String, dynamic>{'message': 'Insufficient coins'},
      ),
    );

    final RepositoryResult<GiftTransaction> failed = await repository.sendGift(
      'room-1',
      giftId: 'gift-1',
    );
    expect(failed, isA<RepositoryFailure<GiftTransaction>>());
    expect(
      (failed as RepositoryFailure<GiftTransaction>).failure,
      isA<NetworkFailure>(),
    );
    expect(failed.failure.message, 'Insufficient coins');
  });

  test('connects realtime channel and exposes gift.sent stream', () async {
    await repository.connect('room-1');
    expect(realtime.connectedRoomId, 'room-1');

    final Future<void> expectation = expectLater(
      repository.watchGiftSent('room-1'),
      emits(
        isA<GiftSentEvent>().having(
          (GiftSentEvent e) => e.gift.name,
          'gift.name',
          'Rocket',
        ),
      ),
    );
    realtime.emit(
      GiftSentEvent(
        transactionId: 'tx-1',
        roomId: 'room-1',
        sender: const GiftUser(id: 'u1', username: 'river'),
        gift: const Gift(id: 'g2', name: 'Rocket', coinPrice: 99),
        quantity: 1,
        coins: 99,
        createdAt: DateTime.utc(2026, 7, 22, 12),
      ),
    );
    await expectation;
  });
}

final class _FakeRemote implements GiftRemoteDataSource {
  Object? error;
  String? sentGiftId;
  int? sentQuantity;

  static const GiftCategory category = GiftCategory(
    id: 'cat-1',
    name: 'Classic',
    sortOrder: 1,
  );

  static const Gift sampleGift = Gift(
    id: 'gift-1',
    name: 'Rose',
    coinPrice: 10,
    category: category,
  );

  @override
  Future<GiftCatalog> getCatalog() async {
    _throwIfNeeded();
    return const GiftCatalog(
      gifts: <Gift>[sampleGift],
      categories: <GiftCategory>[category],
    );
  }

  @override
  Future<GiftTransaction> sendGift(
    String roomId, {
    required String giftId,
    int quantity = 1,
    Map<String, dynamic>? metadata,
    String? clientRequestId,
  }) async {
    _throwIfNeeded();
    sentGiftId = giftId;
    sentQuantity = quantity;
    return GiftTransaction(
      id: 'tx-new',
      roomId: roomId,
      quantity: quantity,
      coins: sampleGift.coinPrice * quantity,
      gift: sampleGift,
      createdAt: DateTime.utc(2026, 7, 22),
    );
  }

  @override
  Future<List<GiftTransaction>> getGiftHistory(
    String userId, {
    int perPage = 30,
  }) async {
    _throwIfNeeded();
    return <GiftTransaction>[
      GiftTransaction(
        id: 'tx-1',
        roomId: 'room-1',
        quantity: 2,
        coins: 20,
        gift: sampleGift,
        createdAt: DateTime.utc(2026, 7, 22),
      ),
    ];
  }

  void _throwIfNeeded() {
    final Object? current = error;
    if (current != null) throw current;
  }
}

final class _FakeRealtime implements GiftRealtimeDataSource {
  // ignore: close_sinks
  final StreamController<GiftSentEvent> _giftSent =
      StreamController<GiftSentEvent>.broadcast();

  String? connectedRoomId;

  @override
  Stream<GiftSentEvent> get giftSent => _giftSent.stream;

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
