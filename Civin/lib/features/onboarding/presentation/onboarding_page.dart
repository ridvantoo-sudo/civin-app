import 'package:civin/core/constants/app_sizes.dart';
import 'package:civin/core/constants/strings.dart';
import 'package:civin/core/router/router.dart';
import 'package:civin/core/widgets/primary_button.dart';
import 'package:civin/features/onboarding/domain/entities/onboarding_page_content.dart';
import 'package:civin/features/onboarding/presentation/onboarding_controller.dart';
import 'package:civin/features/onboarding/widgets/onboarding_page_card.dart';
import 'package:civin/features/onboarding/widgets/onboarding_page_indicator.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final class OnboardingPage extends ConsumerStatefulWidget {
  const OnboardingPage({super.key});

  @override
  ConsumerState<OnboardingPage> createState() => _OnboardingPageState();
}

final class _OnboardingPageState extends ConsumerState<OnboardingPage> {
  late final PageController _pageController;

  @override
  void initState() {
    super.initState();
    _pageController = PageController();
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  Future<void> _finish() async {
    final bool completed = await ref
        .read(onboardingControllerProvider.notifier)
        .complete();
    if (!mounted || !completed) {
      return;
    }
    context.go(AppRoutes.login);
  }

  Future<void> _onNext(int pageCount) async {
    final OnboardingState state = ref.read(onboardingControllerProvider);
    if (state.isLastPage(pageCount)) {
      await _finish();
      return;
    }
    final int nextIndex = state.currentIndex + 1;
    await _pageController.animateToPage(
      nextIndex,
      duration: const Duration(milliseconds: 350),
      curve: Curves.easeOutCubic,
    );
    ref.read(onboardingControllerProvider.notifier).setPage(nextIndex);
  }

  Future<void> _onSkip() async {
    await _finish();
  }

  @override
  Widget build(BuildContext context) {
    final List<OnboardingPageContent> pages = ref.watch(onboardingPagesProvider);
    final OnboardingState state = ref.watch(onboardingControllerProvider);
    final bool isLast = state.isLastPage(pages.length);

    return Semantics(
      label: AppStrings.onboardingSemanticLabel,
      child: Scaffold(
        body: SafeArea(
          child: Column(
            children: <Widget>[
              Align(
                alignment: Alignment.centerRight,
                child: Padding(
                  padding: const EdgeInsets.only(
                    top: AppSizes.space8,
                    right: AppSizes.space8,
                  ),
                  child: AnimatedSwitcher(
                    duration: const Duration(milliseconds: 200),
                    child: isLast
                        ? const SizedBox.shrink(key: ValueKey<String>('no-skip'))
                        : TextButton(
                            key: const ValueKey<String>('skip'),
                            onPressed: state.isCompleting ? null : _onSkip,
                            child: const Text(AppStrings.skip),
                          ),
                  ),
                ),
              ),
              Expanded(
                child: PageView.builder(
                  controller: _pageController,
                  itemCount: pages.length,
                  onPageChanged: ref
                      .read(onboardingControllerProvider.notifier)
                      .setPage,
                  itemBuilder: (BuildContext context, int index) =>
                      AnimatedSwitcher(
                        duration: const Duration(milliseconds: 280),
                        child: OnboardingPageCard(
                          key: ValueKey<String>(pages[index].title),
                          content: pages[index],
                        ),
                      ),
                ),
              ),
              OnboardingPageIndicator(
                length: pages.length,
                index: state.currentIndex,
              ),
              const SizedBox(height: AppSizes.space24),
              Padding(
                padding: const EdgeInsets.symmetric(
                  horizontal: AppSizes.space24,
                ),
                child: AnimatedSwitcher(
                  duration: const Duration(milliseconds: 220),
                  child: PrimaryButton(
                    key: ValueKey<String>(isLast ? 'finish' : 'next'),
                    label: isLast ? AppStrings.finish : AppStrings.next,
                    isLoading: state.isCompleting,
                    onPressed: () => _onNext(pages.length),
                  ),
                ),
              ),
              const SizedBox(height: AppSizes.space24),
            ],
          ),
        ),
      ),
    );
  }
}
