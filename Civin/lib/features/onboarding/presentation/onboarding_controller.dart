import 'package:civin/core/services/app_logger.dart';
import 'package:civin/core/storage/shared_pref_service.dart';
import 'package:civin/features/onboarding/data/onboarding_local_data_source.dart';
import 'package:civin/features/onboarding/data/onboarding_pages_data.dart';
import 'package:civin/features/onboarding/domain/entities/onboarding_page_content.dart';
import 'package:civin/features/onboarding/domain/repositories/onboarding_repository.dart';
import 'package:civin/features/onboarding/domain/usecases/complete_onboarding.dart';
import 'package:civin/features/onboarding/domain/usecases/is_onboarding_completed.dart';
import 'package:civin/features/onboarding/repository/onboarding_repository_impl.dart';
import 'package:civin/features/onboarding/services/onboarding_service.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';

final Provider<OnboardingLocalDataSource> onboardingLocalDataSourceProvider =
    Provider<OnboardingLocalDataSource>(
      (Ref ref) =>
          OnboardingLocalDataSource(ref.watch(sharedPrefServiceProvider)),
    );

final Provider<OnboardingRepository> onboardingRepositoryProvider =
    Provider<OnboardingRepository>(
      (Ref ref) => OnboardingRepositoryImpl(
        ref.watch(onboardingLocalDataSourceProvider),
      ),
    );

final Provider<IsOnboardingCompleted> isOnboardingCompletedProvider =
    Provider<IsOnboardingCompleted>(
      (Ref ref) =>
          IsOnboardingCompleted(ref.watch(onboardingRepositoryProvider)),
    );

final Provider<CompleteOnboarding> completeOnboardingProvider =
    Provider<CompleteOnboarding>(
      (Ref ref) => CompleteOnboarding(ref.watch(onboardingRepositoryProvider)),
    );

final Provider<OnboardingService> onboardingServiceProvider =
    Provider<OnboardingService>(
      (Ref ref) => OnboardingService(
        completeOnboarding: ref.watch(completeOnboardingProvider),
        logger: ref.watch(appLoggerProvider),
      ),
    );

final Provider<List<OnboardingPageContent>> onboardingPagesProvider =
    Provider<List<OnboardingPageContent>>(
      (Ref ref) => OnboardingPagesData.pages,
    );

final class OnboardingState {
  const OnboardingState({
    required this.currentIndex,
    required this.isCompleting,
  });

  final int currentIndex;
  final bool isCompleting;

  bool isLastPage(int pageCount) => currentIndex >= pageCount - 1;

  OnboardingState copyWith({int? currentIndex, bool? isCompleting}) =>
      OnboardingState(
        currentIndex: currentIndex ?? this.currentIndex,
        isCompleting: isCompleting ?? this.isCompleting,
      );
}

final NotifierProvider<OnboardingController, OnboardingState>
onboardingControllerProvider =
    NotifierProvider<OnboardingController, OnboardingState>(
      OnboardingController.new,
    );

final class OnboardingController extends Notifier<OnboardingState> {
  @override
  OnboardingState build() =>
      const OnboardingState(currentIndex: 0, isCompleting: false);

  void setPage(int index) {
    state = state.copyWith(currentIndex: index);
  }

  void next(int pageCount) {
    if (state.currentIndex >= pageCount - 1) {
      return;
    }
    state = state.copyWith(currentIndex: state.currentIndex + 1);
  }

  Future<bool> complete() async {
    if (state.isCompleting) {
      return false;
    }
    state = state.copyWith(isCompleting: true);
    try {
      await ref.read(onboardingServiceProvider).finish();
      return true;
    } on Object {
      state = state.copyWith(isCompleting: false);
      rethrow;
    }
  }
}
