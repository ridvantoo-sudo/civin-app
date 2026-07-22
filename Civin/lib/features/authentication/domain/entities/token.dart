final class Token {
  const Token({
    required this.value,
    required this.issuedAt,
    required this.expiresAt,
  });

  final String value;
  final DateTime issuedAt;
  final DateTime expiresAt;

  bool get isExpired => !expiresAt.isAfter(DateTime.now().toUtc());
}
