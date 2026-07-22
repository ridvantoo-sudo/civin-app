import 'package:connectivity_plus/connectivity_plus.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<NetworkChecker> networkCheckerProvider =
    Provider<NetworkChecker>((Ref ref) => NetworkChecker());

final StreamProvider<bool> connectivityProvider = StreamProvider<bool>(
  (Ref ref) => ref.watch(networkCheckerProvider).onStatusChanged,
);

final class NetworkChecker {
  NetworkChecker({Connectivity? connectivity})
    : _connectivity = connectivity ?? Connectivity();

  final Connectivity _connectivity;

  Future<bool> get isConnected async =>
      _hasConnection(await _connectivity.checkConnectivity());

  Stream<bool> get onStatusChanged =>
      _connectivity.onConnectivityChanged.map(_hasConnection).distinct();

  bool _hasConnection(List<ConnectivityResult> results) => results.any(
    (ConnectivityResult result) => result != ConnectivityResult.none,
  );
}
