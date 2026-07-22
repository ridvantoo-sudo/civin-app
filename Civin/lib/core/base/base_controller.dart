import 'package:flutter_riverpod/flutter_riverpod.dart';

abstract class BaseController<T> extends AsyncNotifier<T> {
  Future<void> execute(Future<T> Function() operation) async {
    state = AsyncLoading<T>();
    state = await AsyncValue.guard(operation);
  }

  void setData(T value) {
    state = AsyncData<T>(value);
  }
}
