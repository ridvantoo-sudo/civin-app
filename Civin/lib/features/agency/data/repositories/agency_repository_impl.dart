import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/agency/data/datasources/agency_remote_data_source.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:civin/features/agency/domain/repositories/agency_repository.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<AgencyRepository> agencyRepositoryProvider =
    Provider<AgencyRepository>(
      (Ref ref) =>
          AgencyRepositoryImpl(ref.watch(agencyRemoteDataSourceProvider)),
    );

final class AgencyRepositoryImpl extends BaseRepository
    implements AgencyRepository {
  AgencyRepositoryImpl(this._remote);

  final AgencyRemoteDataSource _remote;

  @override
  Future<RepositoryResult<Agency>> create(CreateAgencyInput input) =>
      execute(() => _remote.create(input));

  @override
  Future<RepositoryResult<Agency>> getAgency(String agencyId) =>
      execute(() => _remote.getAgency(agencyId));

  @override
  Future<RepositoryResult<AgencyMember>> apply({
    required String agencyId,
    String? message,
  }) => execute(() => _remote.apply(agencyId: agencyId, message: message));

  @override
  Future<RepositoryResult<AgencyMember>> approve({
    required String agencyId,
    required String userId,
  }) => execute(() => _remote.approve(agencyId: agencyId, userId: userId));

  @override
  Future<RepositoryResult<AgencyMember>> reject({
    required String agencyId,
    required String userId,
  }) => execute(() => _remote.reject(agencyId: agencyId, userId: userId));

  @override
  Future<RepositoryResult<AgencyMember>> removeMember({
    required String agencyId,
    required String userId,
  }) =>
      execute(() => _remote.removeMember(agencyId: agencyId, userId: userId));

  @override
  Future<RepositoryResult<List<AgencyMember>>> getHosts(String agencyId) =>
      execute(() => _remote.getHosts(agencyId));

  @override
  Future<RepositoryResult<List<AgencyCommission>>> getEarnings(
    String agencyId, {
    int perPage = 20,
  }) => execute(() => _remote.getEarnings(agencyId, perPage: perPage));
}
