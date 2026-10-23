import 'package:civin/core/base/base_repository.dart';
import 'package:civin/features/authentication/domain/entities/session.dart';
import 'package:civin/features/authentication/domain/entities/user.dart';

abstract interface class AuthRepository {
  Stream<User?> get authStateChanges;
  User? get currentUser;

  Future<RepositoryResult<Session>> signInWithEmail(
    String email,
    String password,
  );
  Future<RepositoryResult<Session>> registerWithEmail(
    String email,
    String password,
  );
  Future<RepositoryResult<Session>> signInWithGoogle();
  Future<RepositoryResult<Session>> signInWithApple();
  Future<RepositoryResult<Session>> signInAsGuest();
  Future<RepositoryResult<String>> sendPhoneCode(String phoneNumber);
  Future<RepositoryResult<Session>> verifyPhoneCode(
    String verificationId,
    String code,
  );
  Future<RepositoryResult<void>> sendPasswordReset(String email);
  Future<RepositoryResult<void>> confirmPasswordReset(
    String code,
    String newPassword,
  );
  Future<RepositoryResult<void>> sendEmailVerification();
  Future<RepositoryResult<User>> reloadUser();
  Future<RepositoryResult<User>> updateProfile({
    required String displayName,
    String? photoUrl,
  });
  Future<RepositoryResult<Session>> restoreSession({bool forceRefresh = false});
  Future<RepositoryResult<void>> signOut();
  Future<RepositoryResult<void>> deleteAccount();
}
