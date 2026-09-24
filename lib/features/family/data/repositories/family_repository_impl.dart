import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/family/data/datasources/family_remote_data_source.dart';
import 'package:civin/features/family/domain/entities/family_club.dart';
import 'package:civin/features/family/domain/repositories/family_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<FamilyRepository> familyRepositoryProvider =
    Provider<FamilyRepository>(
      (Ref ref) =>
          FamilyRepositoryImpl(ref.watch(familyRemoteDataSourceProvider)),
    );

final class FamilyRepositoryImpl extends BaseRepository
    implements FamilyRepository {
  FamilyRepositoryImpl(this._remote);

  final FamilyRemoteDataSource _remote;

  @override
  Future<RepositoryResult<FamilyClub?>> mine() => execute(_remote.mine);

  @override
  Future<RepositoryResult<List<FamilyClub>>> rankings() =>
      execute(_remote.rankings);

  @override
  Future<RepositoryResult<FamilyClub>> create({
    required String name,
    String? description,
  }) => execute(() => _remote.create(name: name, description: description));

  @override
  Future<RepositoryResult<void>> join(String code) =>
      execute(() => _remote.join(code));

  @override
  Future<RepositoryResult<List<FamilyMemberView>>> members(String familyId) =>
      execute(() => _remote.members(familyId));

  @override
  Future<RepositoryResult<List<FamilyMessageView>>> messages(String familyId) =>
      execute(() => _remote.messages(familyId));

  @override
  Future<RepositoryResult<FamilyMessageView>> sendMessage(
    String familyId,
    String body,
  ) => execute(() => _remote.sendMessage(familyId, body));

  @override
  Future<RepositoryResult<List<FamilyMissionView>>> missions(String familyId) =>
      execute(() => _remote.missions(familyId));

  @override
  Future<RepositoryResult<void>> claimMission(
    String familyId,
    String missionId,
  ) => execute(() => _remote.claimMission(familyId, missionId));

  @override
  Future<RepositoryResult<FamilyInviteView>> invite(String familyId) =>
      execute(() => _remote.invite(familyId));
}
