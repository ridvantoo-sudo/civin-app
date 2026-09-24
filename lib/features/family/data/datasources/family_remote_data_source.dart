import 'package:civin/core/network/dio_client.dart';
import 'package:civin/core/utils/json_values.dart';
import 'package:civin/features/family/data/models/family_model.dart';
import 'package:civin/features/family/domain/entities/family_club.dart';
import 'package:dio/dio.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<FamilyRemoteDataSource> familyRemoteDataSourceProvider =
    Provider<FamilyRemoteDataSource>(
      (Ref ref) => DioFamilyRemoteDataSource(ref.watch(dioClientProvider)),
    );

abstract interface class FamilyRemoteDataSource {
  Future<FamilyClub?> mine();

  Future<List<FamilyClub>> rankings();

  Future<FamilyClub> create({required String name, String? description});

  Future<void> join(String code);

  Future<List<FamilyMemberView>> members(String familyId);

  Future<List<FamilyMessageView>> messages(String familyId);

  Future<FamilyMessageView> sendMessage(String familyId, String body);

  Future<List<FamilyMissionView>> missions(String familyId);

  Future<void> claimMission(String familyId, String missionId);

  Future<FamilyInviteView> invite(String familyId);
}

final class DioFamilyRemoteDataSource implements FamilyRemoteDataSource {
  const DioFamilyRemoteDataSource(this._client);

  final DioClient _client;

  @override
  Future<FamilyClub?> mine() async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/families/me',
    );
    return FamilyModel.clubOrNull(_body(response)['data']);
  }

  @override
  Future<List<FamilyClub>> rankings() async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/families/rankings',
    );
    return JsonValues.asMapList(
      _body(response)['data'],
    ).map(FamilyModel.clubFromJson).toList(growable: false);
  }

  @override
  Future<FamilyClub> create({required String name, String? description}) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/families/create',
      data: <String, dynamic>{'name': name, 'description': ?description},
    );
    return FamilyModel.clubFromJson(_data(response));
  }

  @override
  Future<void> join(String code) async {
    await _client.post<dynamic>(
      '/api/v1/families/join',
      data: <String, dynamic>{'code': code},
    );
  }

  @override
  Future<List<FamilyMemberView>> members(String familyId) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/families/$familyId/members',
    );
    return JsonValues.asMapList(
      _body(response)['data'],
    ).map(FamilyModel.memberFromJson).toList(growable: false);
  }

  @override
  Future<List<FamilyMessageView>> messages(String familyId) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/families/$familyId/messages',
    );
    return JsonValues.asMapList(
      _body(response)['data'],
    ).map(FamilyModel.messageFromJson).toList(growable: false);
  }

  @override
  Future<FamilyMessageView> sendMessage(String familyId, String body) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/families/$familyId/messages',
      data: <String, dynamic>{'body': body},
    );
    return FamilyModel.messageFromJson(_data(response));
  }

  @override
  Future<List<FamilyMissionView>> missions(String familyId) async {
    final Response<dynamic> response = await _client.get<dynamic>(
      '/api/v1/families/$familyId/missions',
    );
    return JsonValues.asMapList(
      _body(response)['data'],
    ).map(FamilyModel.missionFromJson).toList(growable: false);
  }

  @override
  Future<void> claimMission(String familyId, String missionId) async {
    await _client.post<dynamic>(
      '/api/v1/families/$familyId/missions/$missionId/claim',
    );
  }

  @override
  Future<FamilyInviteView> invite(String familyId) async {
    final Response<dynamic> response = await _client.post<dynamic>(
      '/api/v1/families/$familyId/invites',
      data: const <String, dynamic>{},
    );
    return FamilyModel.inviteFromJson(_data(response));
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
