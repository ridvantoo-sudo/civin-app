import 'package:civin/features/agency/domain/entities/agency.dart';
import 'package:civin/features/profile/domain/entities/social_models.dart';

abstract final class AgencyModel {
  static Agency fromJson(Map<String, dynamic> json) {
    final Object? ownerRaw = json['owner'];
    return Agency(
      id: json['id']?.toString() ?? '',
      name: json['name'] as String? ?? 'Agency',
      slug: json['slug'] as String? ?? '',
      description: json['description'] as String?,
      logo: json['logo'] as String?,
      commissionRate: _double(json['commission_rate']),
      status: _status(json['status'] as String?),
      membersCount: _int(json['members_count']),
      hostsCount: _int(json['hosts_count']),
      totalGrossEarnings: _int(json['total_gross_earnings']),
      totalCommission: _int(json['total_commission']),
      owner: ownerRaw is Map<String, dynamic>
          ? SocialUser.fromJson(ownerRaw)
          : null,
      createdAt: _date(json['created_at']),
      updatedAt: _date(json['updated_at']),
    );
  }

  static AgencyMember memberFromJson(Map<String, dynamic> json) {
    final Object? userRaw = json['user'];
    return AgencyMember(
      id: json['id']?.toString() ?? '',
      agencyId: json['agency_id']?.toString() ?? '',
      role: _role(json['role'] as String?),
      status: _memberStatus(json['status'] as String?),
      message: json['message'] as String?,
      grossEarnings: _int(json['gross_earnings']),
      commissionPaid: _int(json['commission_paid']),
      appliedAt: _date(json['applied_at']),
      reviewedAt: _date(json['reviewed_at']),
      removedAt: _date(json['removed_at']),
      user: userRaw is Map<String, dynamic>
          ? SocialUser.fromJson(userRaw)
          : null,
    );
  }

  static List<AgencyMember> membersFromJson(List<dynamic> items) => items
      .map((dynamic item) {
        if (item is! Map<String, dynamic>) {
          throw const FormatException('Invalid agency member item.');
        }
        return memberFromJson(item);
      })
      .toList(growable: false);

  static AgencyCommission commissionFromJson(Map<String, dynamic> json) {
    final Object? hostRaw = json['host'];
    final Object? metadataRaw = json['metadata'];
    return AgencyCommission(
      id: json['id']?.toString() ?? '',
      agencyId: json['agency_id']?.toString() ?? '',
      grossAmount: _int(json['gross_amount']),
      commissionRate: _double(json['commission_rate']),
      commissionAmount: _int(json['commission_amount']),
      hostNetAmount: _int(json['host_net_amount']),
      currency: json['currency'] as String? ?? 'coins',
      sourceType: json['source_type'] as String?,
      sourceId: json['source_id']?.toString(),
      metadata: metadataRaw is Map<String, dynamic> ? metadataRaw : null,
      host: hostRaw is Map<String, dynamic>
          ? SocialUser.fromJson(hostRaw)
          : null,
      createdAt: _date(json['created_at']),
    );
  }

  static List<AgencyCommission> commissionsFromJson(List<dynamic> items) =>
      items
          .map((dynamic item) {
            if (item is! Map<String, dynamic>) {
              throw const FormatException('Invalid agency commission item.');
            }
            return commissionFromJson(item);
          })
          .toList(growable: false);

  static AgencyStatus _status(String? value) => switch (value?.toLowerCase()) {
    null => AgencyStatus.unknown,
    'active' => AgencyStatus.active,
    'inactive' => AgencyStatus.inactive,
    'suspended' => AgencyStatus.suspended,
    _ => AgencyStatus.unknown,
  };

  static AgencyMemberRole _role(String? value) =>
      switch (value?.toLowerCase()) {
        'owner' => AgencyMemberRole.owner,
        'host' => AgencyMemberRole.host,
        _ => AgencyMemberRole.unknown,
      };

  static AgencyMemberStatus _memberStatus(String? value) =>
      switch (value?.toLowerCase()) {
        'pending' => AgencyMemberStatus.pending,
        'approved' => AgencyMemberStatus.approved,
        'rejected' => AgencyMemberStatus.rejected,
        'removed' => AgencyMemberStatus.removed,
        _ => AgencyMemberStatus.unknown,
      };

  static DateTime? _date(Object? value) {
    if (value is DateTime) return value.toUtc();
    if (value is String && value.isNotEmpty) {
      return DateTime.tryParse(value)?.toUtc();
    }
    return null;
  }

  static int _int(Object? value, {int fallback = 0}) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value?.toString() ?? '') ?? fallback;
  }

  static double _double(Object? value, {double fallback = 0}) {
    if (value is double) return value;
    if (value is num) return value.toDouble();
    return double.tryParse(value?.toString() ?? '') ?? fallback;
  }
}
