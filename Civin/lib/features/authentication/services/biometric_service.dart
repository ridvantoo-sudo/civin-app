import 'package:civin/features/authentication/services/token_service.dart';
import 'package:flutter/services.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:local_auth/local_auth.dart';

final Provider<BiometricService> biometricServiceProvider =
    Provider<BiometricService>(
      (Ref ref) => BiometricService(
        LocalAuthentication(),
        ref.watch(tokenServiceProvider),
      ),
    );

final class BiometricService {
  const BiometricService(this._localAuth, this._tokenService);

  final LocalAuthentication _localAuth;
  final TokenService _tokenService;

  Future<bool> get isAvailable async {
    try {
      return await _localAuth.isDeviceSupported() &&
          await _localAuth.canCheckBiometrics;
    } on PlatformException {
      return false;
    }
  }

  Future<List<BiometricType>> availableTypes() async {
    try {
      return await _localAuth.getAvailableBiometrics();
    } on PlatformException {
      return const <BiometricType>[];
    }
  }

  Future<bool> authenticate() async {
    if (!await isAvailable) return false;
    try {
      return await _localAuth.authenticate(
        localizedReason: 'Authenticate to access your Civin account',
        biometricOnly: true,
        persistAcrossBackgrounding: true,
      );
    } on PlatformException {
      return false;
    }
  }

  Future<bool> get isEnabled => _tokenService.isBiometricEnabled();

  Future<bool> enable() async {
    final bool authenticated = await authenticate();
    if (authenticated) await _tokenService.setBiometricEnabled(true);
    return authenticated;
  }

  Future<void> disable() => _tokenService.setBiometricEnabled(false);
}
