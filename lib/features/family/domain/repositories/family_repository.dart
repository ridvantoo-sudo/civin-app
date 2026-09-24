import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/family/domain/entities/family_club.dart';

abstract interface class FamilyRepository {
  Future<RepositoryResult<FamilyClub?>> mine();

  Future<RepositoryResult<List<FamilyClub>>> rankings();

  Future<RepositoryResult<FamilyClub>> create({
    required String name,
    String? description,
  });

  Future<RepositoryResult<void>> join(String code);

  Future<RepositoryResult<List<FamilyMemberView>>> members(String familyId);

  Future<RepositoryResult<List<FamilyMessageView>>> messages(String familyId);

  Future<RepositoryResult<FamilyMessageView>> sendMessage(
    String familyId,
    String body,
  );

  Future<RepositoryResult<List<FamilyMissionView>>> missions(String familyId);

  Future<RepositoryResult<void>> claimMission(
    String familyId,
    String missionId,
  );

  Future<RepositoryResult<FamilyInviteView>> invite(String familyId);
}
