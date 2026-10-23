import 'dart:async';
import 'dart:convert';
import 'dart:math';

import 'package:crypto/crypto.dart';
import 'package:firebase_auth/firebase_auth.dart' as fb;
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';

final Provider<FirebaseAuthService> firebaseAuthServiceProvider =
    Provider<FirebaseAuthService>((Ref ref) => FirebaseAuthService());

final class FirebaseAuthService {
  FirebaseAuthService({fb.FirebaseAuth? auth, GoogleSignIn? googleSignIn})
    : _providedAuth = auth,
      _googleSignIn = googleSignIn ?? GoogleSignIn.instance;

  final fb.FirebaseAuth? _providedAuth;
  final GoogleSignIn _googleSignIn;
  Future<void>? _googleInitialization;

  fb.FirebaseAuth get _auth {
    if (_providedAuth case final fb.FirebaseAuth auth) return auth;
    if (Firebase.apps.isEmpty) {
      throw StateError(
        'Firebase is not configured. Enable Firebase and add platform '
        'configuration files before signing in.',
      );
    }
    return fb.FirebaseAuth.instance;
  }

  fb.FirebaseAuth get firebaseAuth => _auth;
  fb.User? get currentFirebaseUser => _auth.currentUser;
  Stream<fb.User?> get authStateChanges => _auth.authStateChanges();

  Future<fb.UserCredential> signInWithEmail(String email, String password) =>
      _auth.signInWithEmailAndPassword(email: email, password: password);

  Future<fb.UserCredential> registerWithEmail(String email, String password) =>
      _auth.createUserWithEmailAndPassword(email: email, password: password);

  Future<fb.UserCredential> signInWithGoogle() async {
    _googleInitialization ??= _googleSignIn.initialize();
    await _googleInitialization;
    final GoogleSignInAccount account = await _googleSignIn.authenticate();
    final GoogleSignInAuthentication authentication = account.authentication;
    final String? idToken = authentication.idToken;
    if (idToken == null) {
      throw StateError('Google did not return an identity token.');
    }
    return _auth.signInWithCredential(
      fb.GoogleAuthProvider.credential(idToken: idToken),
    );
  }

  Future<fb.UserCredential> signInWithApple() async {
    final String rawNonce = _generateNonce();
    final String hashedNonce = sha256.convert(utf8.encode(rawNonce)).toString();
    final AuthorizationCredentialAppleID appleCredential =
        await SignInWithApple.getAppleIDCredential(
          scopes: const <AppleIDAuthorizationScopes>[
            AppleIDAuthorizationScopes.email,
            AppleIDAuthorizationScopes.fullName,
          ],
          nonce: hashedNonce,
        );
    final String? identityToken = appleCredential.identityToken;
    if (identityToken == null) {
      throw StateError('Apple did not return an identity token.');
    }
    final fb.OAuthCredential credential =
        fb.AppleAuthProvider.credentialWithIDToken(
          identityToken,
          rawNonce,
          fb.AppleFullPersonName(
            givenName: appleCredential.givenName,
            familyName: appleCredential.familyName,
          ),
        );
    return _auth.signInWithCredential(credential);
  }

  Future<fb.UserCredential> signInAnonymously() => _auth.signInAnonymously();

  Future<String> sendPhoneCode(String phoneNumber) {
    final Completer<String> completer = Completer<String>();
    _auth.verifyPhoneNumber(
      phoneNumber: phoneNumber,
      timeout: const Duration(seconds: 60),
      verificationCompleted: (fb.PhoneAuthCredential credential) async {
        try {
          await _auth.signInWithCredential(credential);
          if (!completer.isCompleted) completer.complete('');
        } on Object catch (error, stackTrace) {
          if (!completer.isCompleted) {
            completer.completeError(error, stackTrace);
          }
        }
      },
      verificationFailed: (fb.FirebaseAuthException error) {
        if (!completer.isCompleted) completer.completeError(error);
      },
      codeSent: (String verificationId, int? resendToken) {
        if (!completer.isCompleted) completer.complete(verificationId);
      },
      codeAutoRetrievalTimeout: (String verificationId) {
        if (!completer.isCompleted) completer.complete(verificationId);
      },
    );
    return completer.future;
  }

  Future<fb.UserCredential> verifyPhoneCode(
    String verificationId,
    String code,
  ) => _auth.signInWithCredential(
    fb.PhoneAuthProvider.credential(
      verificationId: verificationId,
      smsCode: code,
    ),
  );

  Future<void> sendPasswordReset(String email) =>
      _auth.sendPasswordResetEmail(email: email);

  Future<void> confirmPasswordReset(String code, String newPassword) =>
      _auth.confirmPasswordReset(code: code, newPassword: newPassword);

  Future<void> sendEmailVerification() async {
    final fb.User? user = _auth.currentUser;
    if (user == null) throw StateError('No signed-in user.');
    await user.sendEmailVerification();
  }

  Future<fb.User> reloadUser() async {
    final fb.User? user = _auth.currentUser;
    if (user == null) throw StateError('No signed-in user.');
    await user.reload();
    final fb.User? refreshed = _auth.currentUser;
    if (refreshed == null) throw StateError('The session has expired.');
    return refreshed;
  }

  Future<fb.User> updateProfile({
    required String displayName,
    String? photoUrl,
  }) async {
    final fb.User? user = _auth.currentUser;
    if (user == null) throw StateError('No signed-in user.');
    await user.updateDisplayName(displayName);
    if (photoUrl != null) await user.updatePhotoURL(photoUrl);
    return reloadUser();
  }

  Future<void> signOut() async {
    await _auth.signOut();
    if (_googleInitialization != null) await _googleSignIn.signOut();
  }

  Future<void> deleteAccount() async {
    final fb.User? user = _auth.currentUser;
    if (user == null) throw StateError('No signed-in user.');
    await user.delete();
  }

  static String _generateNonce([int length = 32]) {
    const String charset =
        '0123456789ABCDEFGHIJKLMNOPQRSTUVXYZ'
        'abcdefghijklmnopqrstuvwxyz-._';
    final Random random = Random.secure();
    return List<String>.generate(
      length,
      (_) => charset[random.nextInt(charset.length)],
    ).join();
  }
}
