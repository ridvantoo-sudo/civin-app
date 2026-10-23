import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/gifts/data/repositories/gift_repository_impl.dart';
import 'package:civin/features/gifts/domain/entities/gift.dart';
import 'package:civin/features/gifts/domain/repositories/gift_repository.dart';
import 'package:civin/features/gifts/presentation/gift_providers.dart';
import 'package:civin/features/gifts/presentation/widgets/gift_animation_overlay.dart';
import 'package:civin/features/gifts/presentation/widgets/gift_panel.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('gift panel shows categories, gifts, price, and send button', (
    WidgetTester tester,
  ) async {
    final _FakeGiftRepository repository = _FakeGiftRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [giftRepositoryProvider.overrideWithValue(repository)],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData(
            useMaterial3: true,
            brightness: Brightness.dark,
            colorScheme: ColorScheme.fromSeed(
              seedColor: const Color(0xFF6C4DFF),
              brightness: Brightness.dark,
            ),
          ),
          home: const Scaffold(body: GiftPanel(roomId: 'room-1')),
        ),
      ),
    );

    await tester.pumpAndSettle();

    expect(find.text('Send a gift'), findsOneWidget);
    expect(find.text('Classic'), findsOneWidget);
    expect(find.text('Rose'), findsWidgets);
    expect(find.text('10 coins'), findsOneWidget);
    expect(find.text('Send gift'), findsOneWidget);

    await tester.tap(find.text('Send gift'));
    await tester.pumpAndSettle();
    expect(repository.sentGiftId, 'gift-1');
  });

  testWidgets('gift animation overlay shows sender, gift name, and quantity', (
    WidgetTester tester,
  ) async {
    final _FakeGiftRepository repository = _FakeGiftRepository();
    await tester.pumpWidget(
      ProviderScope(
        overrides: [giftRepositoryProvider.overrideWithValue(repository)],
        child: MaterialApp(
          themeMode: ThemeMode.dark,
          darkTheme: ThemeData.dark(useMaterial3: true),
          home: const Scaffold(
            body: GiftAnimationOverlay(roomId: 'room-1'),
          ),
        ),
      ),
    );

    final container = ProviderScope.containerOf(
      tester.element(find.byType(GiftAnimationOverlay)),
    );
    await container.read(giftProvider('room-1').notifier).startListening();
    repository.emit(
      GiftSentEvent(
        transactionId: 'tx-1',
        roomId: 'room-1',
        sender: const GiftUser(
          id: 'u1',
          username: 'river',
          nickname: 'River',
        ),
        gift: const Gift(id: 'g1', name: 'Rocket', coinPrice: 99),
        quantity: 5,
        coins: 495,
        createdAt: DateTime.utc(2026, 7, 22, 12),
      ),
    );
    await tester.pump();
    await tester.pump(const Duration(milliseconds: 100));

    expect(find.text('River'), findsOneWidget);
    expect(find.text('sent Rocket ×5'), findsOneWidget);
    expect(find.text('Rocket'), findsWidgets);
  });
}

final class _FakeGiftRepository implements GiftRepository {
  // ignore: close_sinks
  final StreamController<GiftSentEvent> _giftSent =
      StreamController<GiftSentEvent>.broadcast();

  String? sentGiftId;

  static const GiftCategory category = GiftCategory(
    id: 'cat-1',
    name: 'Classic',
  );
  static const Gift gift = Gift(
    id: 'gift-1',
    name: 'Rose',
    coinPrice: 10,
    icon: null,
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
    sentGiftId = giftId;
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
  Future<void> connect(String roomId) async {}

  @override
  Future<void> disconnect() async {}

  void emit(GiftSentEvent event) => _giftSent.add(event);
}
