import 'package:civin/core/network/dio_client.dart';
import 'package:civin/features/gifts/data/models/gift_model.dart';
import 'package:civin/features/gifts/domain/entities/gift.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<GiftRemoteDataSource> giftRemoteDataSourceProvider =
    Provider<GiftRemoteDataSource>(
      (Ref ref) => DioGiftRemoteDataSource(ref.watch(dioClientProvider)),
    );

abstract interface class GiftRemoteDataSource {
  Future<GiftCatalog> getCatalog();

  Future<GiftTransaction> sendGift(
    String roomId, {
    required String giftId,
    int quantity = 1,
    Map<String, dynamic>? metadata,
    String? clientRequestId,
  });

  Future<List<GiftTransaction>> getGiftHistory(
    String userId, {
    int perPage = 30,
  });
}

final class DioGiftRemoteDataSource implements GiftRemoteDataSource {
  const DioGiftRemoteDataSource(this._client);

  final DioClient _client;

  @override
  Future<GiftCatalog> getCatalog() async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/gifts',
    );
    final Object? data = _body(response)['data'];
    if (data is! List<dynamic>) {
      throw const FormatException('Invalid gift catalog response.');
    }
    return GiftModel.catalogFromJson(data);
  }

  @override
  Future<GiftTransaction> sendGift(
    String roomId, {
    required String giftId,
    int quantity = 1,
    Map<String, dynamic>? metadata,
    String? clientRequestId,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/live/$roomId/gifts/send',
      data: <String, dynamic>{
        'gift_id': giftId,
        'quantity': quantity,
        'metadata': ?metadata,
        'client_request_id': ?clientRequestId,
      },
    );
    return GiftModel.transactionFromJson(_data(response));
  }

  @override
  Future<List<GiftTransaction>> getGiftHistory(
    String userId, {
    int perPage = 30,
  }) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/users/$userId/gift-history',
      queryParameters: <String, dynamic>{'per_page': perPage},
    );
    final Object? data = _body(response)['data'];
    if (data is! List<dynamic>) {
      throw const FormatException('Invalid gift history response.');
    }
    return data
        .map((dynamic item) {
          if (item is! Map<String, dynamic>) {
            throw const FormatException('Invalid gift history item.');
          }
          return GiftModel.transactionFromJson(item);
        })
        .toList(growable: false);
  }

  Map<String, dynamic> _body(Response<dynamic> response) {
    final Object? data = response.data;
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid API envelope.');
  }

  Map<String, dynamic> _data(Response<dynamic> response) {
    final Object? data = _body(response)['data'];
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid API data payload.');
  }
}
