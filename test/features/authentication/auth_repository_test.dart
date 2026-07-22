import 'package:civin/core/base/base_repository.dart';
import 'package:civin/core/errors/app_failure.dart';
import 'package:civin/core/storage/secure_storage.dart';
import 'package:civin/features/authentication/domain/entities/session.dart';
import 'package:civin/features/authentication/repository/auth_repository_impl.dart';
import 'package:civin/features/authentication/services/api_session_service.dart';
import 'package:civin/features/authentication/services/biometric_service.dart';
import 'package:civin/features/authentication/services/firebase_auth_service.dart';
import 'package:civin/features/authentication/services/token_service.dart';
import 'package:dio/dio.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:local_auth/local_auth.dart';

void main() {
  TestWidgetsFlutterBinding.ensureInitialized();

  late AuthRepositoryImpl repository;

  setUp(() {
    final TokenService tokens = TokenService(const SecureStorage());
    repository = AuthRepositoryImpl(
      FirebaseAuthService(),
      tokens,
      BiometricService(LocalAuthentication(), tokens),
      ApiSessionService(Dio(), tokens),
      () async => false,
    );
  });

  test('returns an offline failure without calling Firebase', () async {
    final result = await repository.signInWithEmail(
      'person@example.com',
      'Strong1!',
    );

    expect(result, isA<RepositoryFailure<Session>>());
    final failure = (result as RepositoryFailure<Session>).failure;
    expect(failure, isA<NetworkFailure>());
    expect(failure.message, 'No internet connection.');
  });

  test('exposes a signed-out state when Firebase is not configured', () async {
    expect(repository.currentUser, isNull);
    expect(await repository.authStateChanges.first, isNull);
  });
}
