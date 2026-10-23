final class User {
  const User({
    required this.id,
    required this.isAnonymous,
    required this.isEmailVerified,
    this.email,
    this.phoneNumber,
    this.displayName,
    this.photoUrl,
  });

  final String id;
  final String? email;
  final String? phoneNumber;
  final String? displayName;
  final String? photoUrl;
  final bool isAnonymous;
  final bool isEmailVerified;

  User copyWith({
    String? email,
    String? phoneNumber,
    String? displayName,
    String? photoUrl,
    bool? isAnonymous,
    bool? isEmailVerified,
  }) => User(
    id: id,
    email: email ?? this.email,
    phoneNumber: phoneNumber ?? this.phoneNumber,
    displayName: displayName ?? this.displayName,
    photoUrl: photoUrl ?? this.photoUrl,
    isAnonymous: isAnonymous ?? this.isAnonymous,
    isEmailVerified: isEmailVerified ?? this.isEmailVerified,
  );
}
