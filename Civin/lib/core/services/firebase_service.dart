import 'package:firebase_core/firebase_core.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<FirebaseService> firebaseServiceProvider =
    Provider<FirebaseService>((Ref ref) => FirebaseService());

final class FirebaseService {
  bool get isInitialized => Firebase.apps.isNotEmpty;

  Future<FirebaseApp?> initialize({required bool enabled}) async {
    if (!enabled) {
      return null;
    }
    if (Firebase.apps.isNotEmpty) {
      return Firebase.app();
    }
    return Firebase.initializeApp();
  }
}
