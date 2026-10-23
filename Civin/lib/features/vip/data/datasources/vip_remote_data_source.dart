import 'package:civin/core/network/dio_client.dart';
import 'package:civin/features/vip/data/models/vip_model.dart';
import 'package:civin/features/vip/domain/entities/vip.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<VipRemoteDataSource> vipRemoteDataSourceProvider =
    Provider<VipRemoteDataSource>(
      (Ref ref) => DioVipRemoteDataSource(ref.watch(dioClientProvider)),
    );

abstract interface class VipRemoteDataSource {
  Future<List<VipLevel>> getLevels();

  Future<VipSubscription> getMyVip();

  Future<VipSubscription> purchase({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  });

  Future<VipSubscription> upgrade({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  });
}

final class DioVipRemoteDataSource implements VipRemoteDataSource {
  const DioVipRemoteDataSource(this._client);

  final DioClient _client;

  @override
  Future<List<VipLevel>> getLevels() async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/vip/levels',
    );
    final Object? data = _body(response)['data'];
    if (data is! List<dynamic>) {
      throw const FormatException('Invalid VIP levels response.');
    }
    return VipModel.levelsFromJson(data);
  }

  @override
  Future<VipSubscription> getMyVip() async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/vip/me',
    );
    return VipModel.subscriptionFromJson(_data(response));
  }

  @override
  Future<VipSubscription> purchase({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/vip/purchase',
      data: <String, dynamic>{
        'vip_level_id': vipLevelId,
        'metadata': ?metadata,
      },
    );
    return VipModel.subscriptionFromJson(_data(response));
  }

  @override
  Future<VipSubscription> upgrade({
    required String vipLevelId,
    Map<String, dynamic>? metadata,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/vip/upgrade',
      data: <String, dynamic>{
        'vip_level_id': vipLevelId,
        'metadata': ?metadata,
      },
    );
    return VipModel.subscriptionFromJson(_data(response));
  }

  Map<String, dynamic> _data(Response<dynamic> response) {
    final Object? data = _body(response)['data'];
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid VIP payload.');
  }

  Map<String, dynamic> _body(Response<dynamic> response) {
    final Object? data = response.data;
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid API envelope.');
  }
}
