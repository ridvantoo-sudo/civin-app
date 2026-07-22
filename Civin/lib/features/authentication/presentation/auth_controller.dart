import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/features/authentication/domain/entities/session.dart';
import 'package:civin/features/authentication/domain/entities/user.dart';
import 'package:civin/features/authentication/domain/repositories/auth_repository.dart';
import 'package:civin/features/authentication/repository/auth_repository_impl.dart';
import 'package:civin/features/authentication/services/biometric_service.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final NotifierProvider<AuthController, AuthViewState> authControllerProvider =
    NotifierProvider<AuthController, AuthViewState>(AuthController.new);

final StreamProvider<User?> authStateProvider = StreamProvider<User?>(
  (Ref ref) => ref.watch(authRepositoryProvider).authStateChanges,
);

final class AuthViewState {
  const AuthViewState({
    this.isLoading = false,
    this.error,
    this.message,
    this.verificationId,
    this.session,
  });

  final bool isLoading;
  final String? error;
  final String? message;
  final String? verificationId;
  final Session? session;

  AuthViewState copyWith({
    bool? isLoading,
    String? error,
    String? message,
    String? verificationId,
    Session? session,
    bool clearError = false,
    bool clearMessage = false,
  }) => AuthViewState(
    isLoading: isLoading ?? this.isLoading,
    error: clearError ? null : error ?? this.error,
    message: clearMessage ? null : message ?? this.message,
    verificationId: verificationId ?? this.verificationId,
    session: session ?? this.session,
  );
}

final class AuthController extends Notifier<AuthViewState> {
  AuthRepository get _repository => ref.read(authRepositoryProvider);
  BiometricService get _biometrics => ref.read(biometricServiceProvider);

  @override
  AuthViewState build() => const AuthViewState();

  Future<bool> signInWithEmail(String email, String password) =>
      _sessionAction(() => _repository.signInWithEmail(email, password));

  Future<bool> register(String email, String password) =>
      _sessionAction(() => _repository.registerWithEmail(email, password));

  Future<bool> signInWithGoogle() =>
      _sessionAction(_repository.signInWithGoogle);

  Future<bool> signInWithApple() => _sessionAction(_repository.signInWithApple);

  Future<bool> signInAsGuest() => _sessionAction(_repository.signInAsGuest);

  Future<bool> sendPhoneCode(String phoneNumber) async {
    _start();
    final RepositoryResult<String> result = await _repository.sendPhoneCode(
      phoneNumber,
    );
    return result.fold(
      onSuccess: (String verificationId) {
        state = AuthViewState(
          verificationId: verificationId,
          message: verificationId.isEmpty ? 'Phone number verified.' : null,
        );
        return true;
      },
      onFailure: _fail,
    );
  }

  Future<bool> verifyPhoneCode(String code) async {
    final String? verificationId = state.verificationId;
    if (verificationId == null) {
      state = const AuthViewState(error: 'Request a new code first.');
      return false;
    }
    if (verificationId.isEmpty) {
      return restoreSession();
    }
    return _sessionAction(
      () => _repository.verifyPhoneCode(verificationId, code),
      preserveVerificationId: true,
    );
  }

  Future<bool> sendPasswordReset(String email) => _voidAction(
    () => _repository.sendPasswordReset(email),
    successMessage: 'Password reset email sent.',
  );

  Future<bool> confirmPasswordReset(String code, String newPassword) =>
      _voidAction(
        () => _repository.confirmPasswordReset(code, newPassword),
        successMessage: 'Your password has been reset.',
      );

  Future<bool> sendEmailVerification() => _voidAction(
    _repository.sendEmailVerification,
    successMessage: 'Verification email sent.',
  );

  Future<User?> reloadUser() async {
    _start();
    final RepositoryResult<User> result = await _repository.reloadUser();
    return result.fold(
      onSuccess: (User user) {
        state = const AuthViewState();
        return user;
      },
      onFailure: (failure) {
        _fail(failure);
        return null;
      },
    );
  }

  Future<bool> updateProfile(String displayName, {String? photoUrl}) async {
    _start();
    final RepositoryResult<User> result = await _repository.updateProfile(
      displayName: displayName,
      photoUrl: photoUrl,
    );
    return result.fold(
      onSuccess: (User user) {
        state = const AuthViewState(message: 'Profile completed.');
        return true;
      },
      onFailure: _fail,
    );
  }

  Future<bool> restoreSession({bool forceRefresh = false}) => _sessionAction(
    () => _repository.restoreSession(forceRefresh: forceRefresh),
  );

  Future<bool> enableBiometrics() async {
    _start();
    final bool enabled = await _biometrics.enable();
    state = AuthViewState(
      message: enabled ? 'Biometric login enabled.' : null,
      error: enabled ? null : 'Biometric authentication was not successful.',
    );
    return enabled;
  }

  Future<void> disableBiometrics() async {
    await _biometrics.disable();
    state = const AuthViewState(message: 'Biometric login disabled.');
  }

  Future<bool> signOut() => _voidAction(_repository.signOut);

  Future<bool> deleteAccount() => _voidAction(_repository.deleteAccount);

  void clearFeedback() =>
      state = state.copyWith(clearError: true, clearMessage: true);

  Future<bool> _sessionAction(
    Future<RepositoryResult<Session>> Function() operation, {
    bool preserveVerificationId = false,
  }) async {
    final String? verificationId = state.verificationId;
    _start();
    final RepositoryResult<Session> result = await operation();
    return result.fold(
      onSuccess: (Session session) {
        state = AuthViewState(
          session: session,
          verificationId: preserveVerificationId ? verificationId : null,
        );
        return true;
      },
      onFailure: _fail,
    );
  }

  Future<bool> _voidAction(
    Future<RepositoryResult<void>> Function() operation, {
    String? successMessage,
  }) async {
    _start();
    final RepositoryResult<void> result = await operation();
    return result.fold(
      onSuccess: (_) {
        state = AuthViewState(message: successMessage);
        return true;
      },
      onFailure: _fail,
    );
  }

  void _start() => state = AuthViewState(
    isLoading: true,
    verificationId: state.verificationId,
  );

  bool _fail(AppFailure failure) {
    state = AuthViewState(
      error: failure.message,
      verificationId: state.verificationId,
    );
    return false;
  }
}
