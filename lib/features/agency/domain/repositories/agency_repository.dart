import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';

abstract interface class AgencyRepository {
  Future<RepositoryResult<Agency>> create(CreateAgencyInput input);

  Future<RepositoryResult<Agency>> getAgency(String agencyId);

  Future<RepositoryResult<AgencyMember>> apply({
    required String agencyId,
    String? message,
  });

  Future<RepositoryResult<AgencyMember>> approve({
    required String agencyId,
    required String userId,
  });

  Future<RepositoryResult<AgencyMember>> reject({
    required String agencyId,
    required String userId,
  });

  Future<RepositoryResult<AgencyMember>> removeMember({
    required String agencyId,
    required String userId,
  });

  Future<RepositoryResult<List<AgencyMember>>> getHosts(String agencyId);

  Future<RepositoryResult<List<AgencyCommission>>> getEarnings(
    String agencyId, {
    int perPage = 20,
  });
}
