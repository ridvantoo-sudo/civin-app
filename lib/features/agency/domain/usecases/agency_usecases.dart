import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:civin/features/agency/domain/repositories/agency_repository.dart';

final class CreateAgency {
  const CreateAgency(this._repository);

  final AgencyRepository _repository;

  Future<RepositoryResult<Agency>> call(CreateAgencyInput input) =>
      _repository.create(input);
}

final class GetAgency {
  const GetAgency(this._repository);

  final AgencyRepository _repository;

  Future<RepositoryResult<Agency>> call(String agencyId) =>
      _repository.getAgency(agencyId);
}

final class ApplyToAgency {
  const ApplyToAgency(this._repository);

  final AgencyRepository _repository;

  Future<RepositoryResult<AgencyMember>> call({
    required String agencyId,
    String? message,
  }) => _repository.apply(agencyId: agencyId, message: message);
}

final class ApproveAgencyApplication {
  const ApproveAgencyApplication(this._repository);

  final AgencyRepository _repository;

  Future<RepositoryResult<AgencyMember>> call({
    required String agencyId,
    required String userId,
  }) => _repository.approve(agencyId: agencyId, userId: userId);
}

final class RejectAgencyApplication {
  const RejectAgencyApplication(this._repository);

  final AgencyRepository _repository;

  Future<RepositoryResult<AgencyMember>> call({
    required String agencyId,
    required String userId,
  }) => _repository.reject(agencyId: agencyId, userId: userId);
}

final class RemoveAgencyMember {
  const RemoveAgencyMember(this._repository);

  final AgencyRepository _repository;

  Future<RepositoryResult<AgencyMember>> call({
    required String agencyId,
    required String userId,
  }) => _repository.removeMember(agencyId: agencyId, userId: userId);
}

final class ListAgencyHosts {
  const ListAgencyHosts(this._repository);

  final AgencyRepository _repository;

  Future<RepositoryResult<List<AgencyMember>>> call(String agencyId) =>
      _repository.getHosts(agencyId);
}

final class ListAgencyEarnings {
  const ListAgencyEarnings(this._repository);

  final AgencyRepository _repository;

  Future<RepositoryResult<List<AgencyCommission>>> call(
    String agencyId, {
    int perPage = 20,
  }) => _repository.getEarnings(agencyId, perPage: perPage);
}
