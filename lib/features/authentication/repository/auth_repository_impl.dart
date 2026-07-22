import 'dart:async';

import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/core/network/network_checker.dart';
import 'package:civin/features/authentication/domain/entities/device.dart';
import 'package:civin/features/authentication/domain/entities/session.dart';
import 'package:civin/features/authentication/domain/entities/token.dart';
import 'package:civin/features/authentication/domain/entities/user.dart';
import 'package:civin/features/authentication/domain/repositories/auth_repository.dart';
import 'package:civin/features/authentication/services/api_session_service.dart';
import 'package:civin/features/authentication/services/biometric_service.dart';
import 'package:civin/features/authentication/services/firebase_auth_service.dart';
import 'package:civin/features/authentication/services/token_service.dart';
import 'package:dio/dio.dart';
import 'package:firebase_auth/firebase_auth.dart' as fb;
import 'package:flutter/foundation.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<AuthRepository> authRepositoryProvider =
    Provider<AuthRepository>(
      (Ref ref) => AuthRepositoryImpl(
        ref.watch(firebaseAuthServiceProvider),
        ref.watch(tokenServiceProvider),
        ref.watch(biometricServiceProvider),
        ref.watch(apiSessionServiceProvider),
        () => ref.read(networkCheckerProvider).isConnected,
      ),
    );

final class AuthRepositoryImpl implements AuthRepository {
  const AuthRepositoryImpl(
    this._firebase,
    this._tokens,
    this._biometrics,
    this._apiSessions,
    this._isConnected,
  );

  final FirebaseAuthService _firebase;
  final TokenService _tokens;
  final BiometricService _biometrics;
  final ApiSessionService _apiSessions;
  final Future<bool> Function() _isConnected;

  @override
  Stream<User?> get authStateChanges {
    try {
      return _firebase.authStateChanges.map(_toUser);
    } on StateError {
      return Stream<User?>.value(null);
    }
  }

  @override
  User? get currentUser {
    try {
      return _toUser(_firebase.currentFirebaseUser);
    } on StateError {
      return null;
    }
  }

  @override
  Future<RepositoryResult<Session>> signInWithEmail(
    String email,
    String password,
  ) => _authenticate(() => _firebase.signInWithEmail(email.trim(), password));

  @override
  Future<RepositoryResult<Session>> registerWithEmail(
    String email,
    String password,
  ) => _authenticate(() => _firebase.registerWithEmail(email.trim(), password));

  @override
  Future<RepositoryResult<Session>> signInWithGoogle() =>
      _authenticate(_firebase.signInWithGoogle);

  @override
  Future<RepositoryResult<Session>> signInWithApple() =>
      _authenticate(_firebase.signInWithApple);

  @override
  Future<RepositoryResult<Session>> signInAsGuest() =>
      _authenticate(_firebase.signInAnonymously);

  @override
  Future<RepositoryResult<String>> sendPhoneCode(String phoneNumber) =>
      _run(() => _firebase.sendPhoneCode(phoneNumber));

  @override
  Future<RepositoryResult<Session>> verifyPhoneCode(
    String verificationId,
    String code,
  ) => _authenticate(() => _firebase.verifyPhoneCode(verificationId, code));

  @override
  Future<RepositoryResult<void>> sendPasswordReset(String email) =>
      _run(() => _firebase.sendPasswordReset(email.trim()));

  @override
  Future<RepositoryResult<void>> confirmPasswordReset(
    String code,
    String newPassword,
  ) => _run(() => _firebase.confirmPasswordReset(code, newPassword));

  @override
  Future<RepositoryResult<void>> sendEmailVerification() =>
      _run(_firebase.sendEmailVerification);

  @override
  Future<RepositoryResult<User>> reloadUser() =>
      _run(() async => _toUser(await _firebase.reloadUser())!);

  @override
  Future<RepositoryResult<User>> updateProfile({
    required String displayName,
    String? photoUrl,
  }) => _run(
    () async => _toUser(
      await _firebase.updateProfile(
        displayName: displayName.trim(),
        photoUrl: photoUrl,
      ),
    )!,
  );

  @override
  Future<RepositoryResult<Session>> restoreSession({
    bool forceRefresh = false,
  }) => _run(() async {
    final fb.User? firebaseUser = _firebase.currentFirebaseUser;
    if (firebaseUser == null) throw StateError('No active session.');
    if (await _biometrics.isEnabled && !await _biometrics.authenticate()) {
      throw StateError('Biometric authentication was not successful.');
    }
    return _createSession(firebaseUser, forceRefresh: forceRefresh);
  }, requireNetwork: forceRefresh);

  @override
  Future<RepositoryResult<void>> signOut() => _run(() async {
    await _firebase.signOut();
    await _tokens.clearSession();
  }, requireNetwork: false);

  @override
  Future<RepositoryResult<void>> deleteAccount() => _run(() async {
    await _firebase.deleteAccount();
    await _tokens.clearSession();
    await _biometrics.disable();
  });

  Future<RepositoryResult<Session>> _authenticate(
    Future<fb.UserCredential> Function() operation,
  ) => _run(() async {
    final fb.UserCredential credential = await operation();
    final fb.User? firebaseUser = credential.user;
    if (firebaseUser == null) {
      throw StateError('Authentication completed without a user.');
    }
    return _createSession(firebaseUser, forceRefresh: true);
  });

  Future<Session> _createSession(
    fb.User firebaseUser, {
    required bool forceRefresh,
  }) async {
    final fb.IdTokenResult result = await firebaseUser.getIdTokenResult(
      forceRefresh,
    );
    final String? idToken = result.token;
    if (idToken == null) throw StateError('Firebase did not return a token.');

    // Exchange Firebase identity for a Laravel Sanctum access token.
    final Token token = await _apiSessions.exchangeFirebaseIdToken(idToken);
    final DateTime now = DateTime.now().toUtc();
    final Device device = Device(
      id: await _tokens.getOrCreateDeviceId(),
      platform: _devicePlatform,
      biometricEnabled: await _biometrics.isEnabled,
      lastActiveAt: now,
    );
    return Session(
      user: _toUser(firebaseUser)!,
      token: token,
      device: device,
      createdAt: now,
    );
  }

  Future<RepositoryResult<T>> _run<T>(
    Future<T> Function() operation, {
    bool requireNetwork = true,
  }) async {
    try {
      if (requireNetwork && !await _isConnected()) {
        return RepositoryFailure<T>(
          AppFailure.network(message: 'No internet connection.'),
        );
      }
      return RepositorySuccess<T>(await operation());
    } on fb.FirebaseAuthException catch (error) {
      return RepositoryFailure<T>(
        AppFailure.validation(message: _firebaseMessage(error)),
      );
    } on DioException catch (error) {
      return RepositoryFailure<T>(
        AppFailure.network(message: _dioMessage(error)),
      );
    } on StateError catch (error) {
      return RepositoryFailure<T>(
        AppFailure.unexpected(message: error.message),
      );
    } on Exception catch (error) {
      return RepositoryFailure<T>(
        AppFailure.unexpected(message: error.toString(), cause: error),
      );
    }
  }

  static String _dioMessage(DioException error) {
    final Object? data = error.response?.data;
    if (data is Map && data['message'] is String) {
      return data['message'] as String;
    }
    if (error.type == DioExceptionType.connectionError) {
      return 'Cannot reach the API server. Is Laravel running on port 8000?';
    }
    return error.message ?? 'Authentication API request failed.';
  }

  static User? _toUser(fb.User? user) => user == null
      ? null
      : User(
          id: user.uid,
          email: user.email,
          phoneNumber: user.phoneNumber,
          displayName: user.displayName,
          photoUrl: user.photoURL,
          isAnonymous: user.isAnonymous,
          isEmailVerified: user.emailVerified,
        );

  static String _firebaseMessage(fb.FirebaseAuthException error) =>
      switch (error.code) {
        'invalid-email' => 'Enter a valid email address.',
        'user-disabled' => 'This account has been disabled.',
        'user-not-found' ||
        'wrong-password' ||
        'invalid-credential' => 'The email or password is incorrect.',
        'email-already-in-use' => 'An account already uses this email.',
        'weak-password' => 'Choose a stronger password.',
        'too-many-requests' => 'Too many attempts. Try again later.',
        'network-request-failed' => 'No internet connection.',
        'invalid-verification-code' => 'The verification code is invalid.',
        'session-expired' => 'The verification code has expired.',
        'requires-recent-login' =>
          'Sign in again before performing this action.',
        _ => error.message ?? 'Authentication failed.',
      };

  static DevicePlatform get _devicePlatform {
    if (kIsWeb) return DevicePlatform.web;
    return switch (defaultTargetPlatform) {
      TargetPlatform.android => DevicePlatform.android,
      TargetPlatform.iOS => DevicePlatform.ios,
      TargetPlatform.macOS => DevicePlatform.macos,
      TargetPlatform.windows => DevicePlatform.windows,
      TargetPlatform.linux => DevicePlatform.linux,
      TargetPlatform.fuchsia => DevicePlatform.unknown,
    };
  }
}
