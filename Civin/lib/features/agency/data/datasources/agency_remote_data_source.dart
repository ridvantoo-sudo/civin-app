import 'package:civin/core/network/dio_client.dart';
import 'package:civin/features/agency/data/models/agency_model.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<AgencyRemoteDataSource> agencyRemoteDataSourceProvider =
    Provider<AgencyRemoteDataSource>(
      (Ref ref) => DioAgencyRemoteDataSource(ref.watch(dioClientProvider)),
    );

abstract interface class AgencyRemoteDataSource {
  Future<Agency> create(CreateAgencyInput input);

  Future<Agency> getAgency(String agencyId);

  Future<AgencyMember> apply({required String agencyId, String? message});

  Future<AgencyMember> approve({
    required String agencyId,
    required String userId,
  });

  Future<AgencyMember> reject({
    required String agencyId,
    required String userId,
  });

  Future<AgencyMember> removeMember({
    required String agencyId,
    required String userId,
  });

  Future<List<AgencyMember>> getHosts(String agencyId);

  Future<List<AgencyCommission>> getEarnings(
    String agencyId, {
    int perPage = 20,
  });
}

final class DioAgencyRemoteDataSource implements AgencyRemoteDataSource {
  const DioAgencyRemoteDataSource(this._client);

  final DioClient _client;

  @override
  Future<Agency> create(CreateAgencyInput input) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/agencies/create',
      data: <String, dynamic>{
        'name': input.name,
        'description': ?input.description,
        'logo': ?input.logo,
        'commission_rate': ?input.commissionRate,
      },
    );
    return AgencyModel.fromJson(_data(response));
  }

  @override
  Future<Agency> getAgency(String agencyId) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/agencies/$agencyId',
    );
    return AgencyModel.fromJson(_data(response));
  }

  @override
  Future<AgencyMember> apply({
    required String agencyId,
    String? message,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/agencies/$agencyId/apply',
      data: <String, dynamic>{'message': ?message},
    );
    return AgencyModel.memberFromJson(_data(response));
  }

  @override
  Future<AgencyMember> approve({
    required String agencyId,
    required String userId,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/agencies/$agencyId/approve',
      data: <String, dynamic>{'user_id': userId},
    );
    return AgencyModel.memberFromJson(_data(response));
  }

  @override
  Future<AgencyMember> reject({
    required String agencyId,
    required String userId,
  }) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/agencies/$agencyId/reject',
      data: <String, dynamic>{'user_id': userId},
    );
    return AgencyModel.memberFromJson(_data(response));
  }

  @override
  Future<AgencyMember> removeMember({
    required String agencyId,
    required String userId,
  }) async {
    final Response<dynamic> response = await _client.delete<dynamic>(
      '/api/v1/agencies/$agencyId/members/$userId',
    );
    return AgencyModel.memberFromJson(_data(response));
  }

  @override
  Future<List<AgencyMember>> getHosts(String agencyId) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/agencies/$agencyId/hosts',
    );
    final Object? data = _body(response)['data'];
    if (data is! List<dynamic>) {
      throw const FormatException('Invalid agency hosts response.');
    }
    return AgencyModel.membersFromJson(data);
  }

  @override
  Future<List<AgencyCommission>> getEarnings(
    String agencyId, {
    int perPage = 20,
  }) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/agencies/$agencyId/earnings',
      queryParameters: <String, dynamic>{'per_page': perPage},
    );
    final Object? data = _body(response)['data'];
    if (data is! List<dynamic>) {
      throw const FormatException('Invalid agency earnings response.');
    }
    return AgencyModel.commissionsFromJson(data);
  }

  Map<String, dynamic> _data(Response<dynamic> response) {
    final Object? data = _body(response)['data'];
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid agency payload.');
  }

  Map<String, dynamic> _body(Response<dynamic> response) {
    final Object? data = response.data;
    if (data is Map<String, dynamic>) return data;
    throw const FormatException('Invalid API envelope.');
  }
}
