import 'package:civin/features/profile/domain/entities/social_models.dart';

enum AgencyStatus { active, inactive, suspended, unknown }

enum AgencyMemberRole { owner, host, unknown }

enum AgencyMemberStatus { pending, approved, rejected, removed, unknown }

final class Agency {
  const Agency({
    required this.id,
    required this.name,
    required this.slug,
    this.description,
    this.logo,
    this.commissionRate = 0,
    this.status = AgencyStatus.active,
    this.membersCount = 0,
    this.hostsCount = 0,
    this.totalGrossEarnings = 0,
    this.totalCommission = 0,
    this.owner,
    this.createdAt,
    this.updatedAt,
  });

  final String id;
  final String name;
  final String slug;
  final String? description;
  final String? logo;
  final double commissionRate;
  final AgencyStatus status;
  final int membersCount;
  final int hostsCount;
  final int totalGrossEarnings;
  final int totalCommission;
  final SocialUser? owner;
  final DateTime? createdAt;
  final DateTime? updatedAt;

  String get commissionRateLabel {
    final String value = commissionRate == commissionRate.roundToDouble()
        ? commissionRate.toStringAsFixed(0)
        : commissionRate.toStringAsFixed(2);
    return '$value%';
  }

  String get statusLabel => switch (status) {
    AgencyStatus.active => 'Active',
    AgencyStatus.inactive => 'Inactive',
    AgencyStatus.suspended => 'Suspended',
    AgencyStatus.unknown => 'Unknown',
  };

  bool get isActive => status == AgencyStatus.active;
}

final class AgencyMember {
  const AgencyMember({
    required this.id,
    required this.agencyId,
    required this.role,
    required this.status,
    this.message,
    this.grossEarnings = 0,
    this.commissionPaid = 0,
    this.appliedAt,
    this.reviewedAt,
    this.removedAt,
    this.user,
  });

  final String id;
  final String agencyId;
  final AgencyMemberRole role;
  final AgencyMemberStatus status;
  final String? message;
  final int grossEarnings;
  final int commissionPaid;
  final DateTime? appliedAt;
  final DateTime? reviewedAt;
  final DateTime? removedAt;
  final SocialUser? user;

  String get roleLabel => switch (role) {
    AgencyMemberRole.owner => 'Owner',
    AgencyMemberRole.host => 'Host',
    AgencyMemberRole.unknown => 'Member',
  };

  String get statusLabel => switch (status) {
    AgencyMemberStatus.pending => 'Pending',
    AgencyMemberStatus.approved => 'Approved',
    AgencyMemberStatus.rejected => 'Rejected',
    AgencyMemberStatus.removed => 'Removed',
    AgencyMemberStatus.unknown => 'Unknown',
  };

  bool get isPending => status == AgencyMemberStatus.pending;
  bool get isApproved => status == AgencyMemberStatus.approved;
  bool get isHost => role == AgencyMemberRole.host;
}

final class AgencyCommission {
  const AgencyCommission({
    required this.id,
    required this.agencyId,
    required this.grossAmount,
    required this.commissionRate,
    required this.commissionAmount,
    required this.hostNetAmount,
    this.currency = 'coins',
    this.sourceType,
    this.sourceId,
    this.metadata,
    this.host,
    this.createdAt,
  });

  final String id;
  final String agencyId;
  final int grossAmount;
  final double commissionRate;
  final int commissionAmount;
  final int hostNetAmount;
  final String currency;
  final String? sourceType;
  final String? sourceId;
  final Map<String, dynamic>? metadata;
  final SocialUser? host;
  final DateTime? createdAt;

  String get commissionRateLabel {
    final String value = commissionRate == commissionRate.roundToDouble()
        ? commissionRate.toStringAsFixed(0)
        : commissionRate.toStringAsFixed(2);
    return '$value%';
  }
}

final class AgencyStatistics {
  const AgencyStatistics({
    this.hostsCount = 0,
    this.membersCount = 0,
    this.totalGrossEarnings = 0,
    this.totalCommission = 0,
    this.commissionRate = 0,
  });

  factory AgencyStatistics.fromAgency(Agency agency) => AgencyStatistics(
    hostsCount: agency.hostsCount,
    membersCount: agency.membersCount,
    totalGrossEarnings: agency.totalGrossEarnings,
    totalCommission: agency.totalCommission,
    commissionRate: agency.commissionRate,
  );

  final int hostsCount;
  final int membersCount;
  final int totalGrossEarnings;
  final int totalCommission;
  final double commissionRate;

  int get hostNetTotal =>
      (totalGrossEarnings - totalCommission).clamp(0, totalGrossEarnings);
}

final class CreateAgencyInput {
  const CreateAgencyInput({
    required this.name,
    this.description,
    this.logo,
    this.commissionRate,
  });

  final String name;
  final String? description;
  final String? logo;
  final double? commissionRate;
}

final class AgencyViewState {
  const AgencyViewState({
    this.agency,
    this.selectedAgencyId,
    this.hosts = const <AgencyMember>[],
    this.earnings = const <AgencyCommission>[],
    this.lastApplication,
    this.isLoading = false,
    this.isCreating = false,
    this.isApplying = false,
    this.isManagingHosts = false,
    this.isLoadingHosts = false,
    this.isLoadingEarnings = false,
    this.errorMessage,
    this.actionMessage,
  });

  final Agency? agency;
  final String? selectedAgencyId;
  final List<AgencyMember> hosts;
  final List<AgencyCommission> earnings;
  final AgencyMember? lastApplication;
  final bool isLoading;
  final bool isCreating;
  final bool isApplying;
  final bool isManagingHosts;
  final bool isLoadingHosts;
  final bool isLoadingEarnings;
  final String? errorMessage;
  final String? actionMessage;

  bool get isBusy =>
      isLoading ||
      isCreating ||
      isApplying ||
      isManagingHosts ||
      isLoadingHosts ||
      isLoadingEarnings;

  bool get hasAgency => agency != null;

  AgencyStatistics get statistics => agency == null
      ? const AgencyStatistics()
      : AgencyStatistics.fromAgency(agency!);

  String? get activeAgencyId => agency?.id ?? selectedAgencyId;

  AgencyViewState copyWith({
    Agency? agency,
    String? selectedAgencyId,
    List<AgencyMember>? hosts,
    List<AgencyCommission>? earnings,
    AgencyMember? lastApplication,
    bool? isLoading,
    bool? isCreating,
    bool? isApplying,
    bool? isManagingHosts,
    bool? isLoadingHosts,
    bool? isLoadingEarnings,
    String? errorMessage,
    String? actionMessage,
    bool clearAgency = false,
    bool clearError = false,
    bool clearAction = false,
    bool clearApplication = false,
    bool clearSelection = false,
  }) => AgencyViewState(
    agency: clearAgency ? null : agency ?? this.agency,
    selectedAgencyId: clearSelection
        ? null
        : selectedAgencyId ?? this.selectedAgencyId,
    hosts: hosts ?? this.hosts,
    earnings: earnings ?? this.earnings,
    lastApplication: clearApplication
        ? null
        : lastApplication ?? this.lastApplication,
    isLoading: isLoading ?? this.isLoading,
    isCreating: isCreating ?? this.isCreating,
    isApplying: isApplying ?? this.isApplying,
    isManagingHosts: isManagingHosts ?? this.isManagingHosts,
    isLoadingHosts: isLoadingHosts ?? this.isLoadingHosts,
    isLoadingEarnings: isLoadingEarnings ?? this.isLoadingEarnings,
    errorMessage: clearError ? null : errorMessage ?? this.errorMessage,
    actionMessage: clearAction ? null : actionMessage ?? this.actionMessage,
  );
}
