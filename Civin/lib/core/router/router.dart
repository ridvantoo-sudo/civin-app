import 'dart:async';

import 'package:civin/core/constants/strings.dart';
import 'package:civin/core/widgets/error_widget.dart';
import 'package:civin/features/agency/presentation/screens/agency_earnings_screen.dart';
import 'package:civin/features/agency/presentation/screens/agency_home_screen.dart';
import 'package:civin/features/agency/presentation/screens/agency_hosts_screen.dart';
import 'package:civin/features/agency/presentation/screens/agency_profile_screen.dart';
import 'package:civin/features/agency/presentation/screens/create_agency_screen.dart';
import 'package:civin/features/authentication/domain/entities/user.dart';
import 'package:civin/features/authentication/presentation/account_security_page.dart';
import 'package:civin/features/authentication/presentation/complete_profile_page.dart';
import 'package:civin/features/authentication/presentation/forgot_password_page.dart';
import 'package:civin/features/authentication/presentation/login_page.dart';
import 'package:civin/features/authentication/presentation/otp_verification_page.dart';
import 'package:civin/features/authentication/presentation/phone_login_page.dart';
import 'package:civin/features/authentication/presentation/register_page.dart';
import 'package:civin/features/authentication/presentation/reset_password_page.dart';
import 'package:civin/features/authentication/presentation/verify_email_page.dart';
import 'package:civin/features/authentication/repository/auth_repository_impl.dart';
import 'package:civin/features/home/presentation/home_page.dart';
import 'package:civin/features/live/domain/entities/live_room.dart';
import 'package:civin/features/live/presentation/screens/create_live_screen.dart';
import 'package:civin/features/live/presentation/screens/live_home_screen.dart';
import 'package:civin/features/onboarding/presentation/onboarding_page.dart';
import 'package:civin/features/pk/presentation/screens/live_room_with_pk_screen.dart';
import 'package:civin/features/profile/presentation/edit_profile_page.dart';
import 'package:civin/features/profile/presentation/profile_page.dart';
import 'package:civin/features/profile/presentation/report_user_page.dart';
import 'package:civin/features/profile/presentation/search_users_page.dart';
import 'package:civin/features/profile/presentation/user_details_page.dart';
import 'package:civin/features/profile/presentation/user_list_pages.dart';
import 'package:civin/features/rankings/presentation/screens/gifter_ranking_screen.dart';
import 'package:civin/features/rankings/presentation/screens/host_ranking_screen.dart';
import 'package:civin/features/rankings/presentation/screens/pk_ranking_screen.dart';
import 'package:civin/features/rankings/presentation/screens/ranking_home_screen.dart';
import 'package:civin/features/rankings/presentation/screens/voice_ranking_screen.dart';
import 'package:civin/features/splash/presentation/splash_page.dart';
import 'package:civin/features/vip/presentation/screens/vip_home_screen.dart';
import 'package:civin/features/vip/presentation/screens/vip_levels_screen.dart';
import 'package:civin/features/vip/presentation/screens/vip_profile_badge_screen.dart';
import 'package:civin/features/vip/presentation/screens/vip_purchase_screen.dart';
import 'package:civin/features/voice_rooms/domain/entities/voice_room.dart';
import 'package:civin/features/voice_rooms/presentation/screens/create_voice_room_screen.dart';
import 'package:civin/features/voice_rooms/presentation/screens/voice_room_home_screen.dart';
import 'package:civin/features/voice_rooms/presentation/screens/voice_room_screen.dart';
import 'package:civin/features/wallet/presentation/screens/recharge_screen.dart';
import 'package:civin/features/wallet/presentation/screens/transaction_history_screen.dart';
import 'package:civin/features/wallet/presentation/screens/wallet_screen.dart';
import 'package:civin/features/wallet/presentation/screens/withdraw_screen.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

final GlobalKey<NavigatorState> rootNavigatorKey = GlobalKey<NavigatorState>();

abstract final class AppRoutes {
  static const String splash = '/';
  static const String onboarding = '/onboarding';
  static const String login = '/login';
  static const String register = '/register';
  static const String forgotPassword = '/forgot-password';
  static const String resetPassword = '/reset-password';
  static const String verifyEmail = '/verify-email';
  static const String phoneLogin = '/phone-login';
  static const String otpVerification = '/otp-verification';
  static const String completeProfile = '/complete-profile';
  static const String accountSecurity = '/account-security';
  static const String home = '/home';
  static const String profile = '/profile';
  static const String editProfile = '/profile/edit';
  static const String searchUsers = '/users/search';
  static const String blockedUsers = '/blocked-users';
  static const String live = '/live';
  static const String createLive = '/live/create';
  static const String wallet = '/wallet';
  static const String walletTransactions = '/wallet/transactions';
  static const String walletRecharge = '/wallet/recharge';
  static const String walletWithdraw = '/wallet/withdraw';
  static const String voiceRooms = '/voice';
  static const String createVoiceRoom = '/voice/create';
  static const String rankings = '/rankings';
  static const String hostRanking = '/rankings/hosts';
  static const String gifterRanking = '/rankings/gifters';
  static const String pkRanking = '/rankings/pk';
  static const String voiceRanking = '/rankings/voice';
  static const String vip = '/vip';
  static const String vipLevels = '/vip/levels';
  static const String vipPurchase = '/vip/purchase';
  static const String vipBadge = '/vip/badge';
  static const String agency = '/agency';
  static const String createAgency = '/agency/create';
  static const String agencyProfile = '/agency/profile';
  static const String agencyHosts = '/agency/hosts';
  static const String agencyEarnings = '/agency/earnings';

  static String userDetailsPath(String userId) => '/users/$userId';
  static String agencyProfilePath(String agencyId) =>
      '/agency/profile?agencyId=$agencyId';
  static String followersPath(String userId) => '/users/$userId/followers';
  static String followingPath(String userId) => '/users/$userId/following';
  static String reportUserPath(String userId) => '/users/$userId/report';
  static String liveRoomPath(String roomId) => '/live/$roomId';
  static String voiceRoomPath(String roomId) => '/voice/$roomId';
}

final Provider<GoRouter> routerProvider = Provider<GoRouter>((Ref ref) {
  final authRepository = ref.watch(authRepositoryProvider);
  final _RouterRefreshNotifier refreshNotifier = _RouterRefreshNotifier(
    authRepository.authStateChanges,
  );
  final GoRouter router = GoRouter(
    navigatorKey: rootNavigatorKey,
    initialLocation: AppRoutes.splash,
    refreshListenable: refreshNotifier,
    redirect: (BuildContext context, GoRouterState state) {
      if (Firebase.apps.isEmpty) return null;
      final User? user = authRepository.currentUser;
      final String location = state.matchedLocation;
      final bool protected =
          <String>{
            AppRoutes.home,
            AppRoutes.verifyEmail,
            AppRoutes.completeProfile,
            AppRoutes.accountSecurity,
            AppRoutes.profile,
            AppRoutes.editProfile,
            AppRoutes.searchUsers,
            AppRoutes.blockedUsers,
            AppRoutes.live,
            AppRoutes.createLive,
            AppRoutes.wallet,
            AppRoutes.walletTransactions,
            AppRoutes.walletRecharge,
            AppRoutes.walletWithdraw,
            AppRoutes.voiceRooms,
            AppRoutes.createVoiceRoom,
            AppRoutes.rankings,
            AppRoutes.hostRanking,
            AppRoutes.gifterRanking,
            AppRoutes.pkRanking,
            AppRoutes.voiceRanking,
            AppRoutes.vip,
            AppRoutes.vipLevels,
            AppRoutes.vipPurchase,
            AppRoutes.vipBadge,
            AppRoutes.agency,
            AppRoutes.createAgency,
            AppRoutes.agencyProfile,
            AppRoutes.agencyHosts,
            AppRoutes.agencyEarnings,
          }.contains(location) ||
          location.startsWith('/users/') ||
          location.startsWith('/live/') ||
          location.startsWith('/wallet') ||
          location.startsWith('/voice/') ||
          location.startsWith('/rankings') ||
          location.startsWith('/vip') ||
          location.startsWith('/agency');
      if (protected && user == null) return AppRoutes.login;

      if (user != null) {
        final bool guestOnly = <String>{
          AppRoutes.login,
          AppRoutes.register,
          AppRoutes.phoneLogin,
          AppRoutes.otpVerification,
        }.contains(location);
        if (guestOnly) return _authenticatedDestination(user);
        if (!user.isAnonymous &&
            user.email != null &&
            !user.isEmailVerified &&
            location != AppRoutes.verifyEmail) {
          return AppRoutes.verifyEmail;
        }
        if (!user.isAnonymous &&
            (user.displayName?.trim().isEmpty ?? true) &&
            user.isEmailVerified &&
            location != AppRoutes.completeProfile) {
          return AppRoutes.completeProfile;
        }
      }
      return null;
    },
    routes: <RouteBase>[
      GoRoute(
        path: AppRoutes.splash,
        name: 'splash',
        builder: (BuildContext context, GoRouterState state) =>
            const SplashPage(),
      ),
      GoRoute(
        path: AppRoutes.onboarding,
        name: 'onboarding',
        builder: (BuildContext context, GoRouterState state) =>
            const OnboardingPage(),
      ),
      GoRoute(
        path: AppRoutes.login,
        name: 'login',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _authPage(state, const LoginPage()),
      ),
      GoRoute(
        path: AppRoutes.register,
        name: 'register',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _authPage(state, const RegisterPage()),
      ),
      GoRoute(
        path: AppRoutes.forgotPassword,
        name: 'forgot-password',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _authPage(state, const ForgotPasswordPage()),
      ),
      GoRoute(
        path: AppRoutes.resetPassword,
        name: 'reset-password',
        pageBuilder: (BuildContext context, GoRouterState state) => _authPage(
          state,
          ResetPasswordPage(code: state.uri.queryParameters['oobCode'] ?? ''),
        ),
      ),
      GoRoute(
        path: AppRoutes.verifyEmail,
        name: 'verify-email',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _authPage(state, const VerifyEmailPage()),
      ),
      GoRoute(
        path: AppRoutes.phoneLogin,
        name: 'phone-login',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _authPage(state, const PhoneLoginPage()),
      ),
      GoRoute(
        path: AppRoutes.otpVerification,
        name: 'otp-verification',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _authPage(state, const OtpVerificationPage()),
      ),
      GoRoute(
        path: AppRoutes.completeProfile,
        name: 'complete-profile',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _authPage(state, const CompleteProfilePage()),
      ),
      GoRoute(
        path: AppRoutes.accountSecurity,
        name: 'account-security',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _authPage(state, const AccountSecurityPage()),
      ),
      GoRoute(
        path: AppRoutes.home,
        name: 'home',
        builder: (BuildContext context, GoRouterState state) =>
            const HomePage(),
      ),
      GoRoute(
        path: AppRoutes.profile,
        name: 'profile',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const ProfilePage()),
      ),
      GoRoute(
        path: AppRoutes.live,
        name: 'live',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const LiveHomeScreen()),
      ),
      GoRoute(
        path: AppRoutes.createLive,
        name: 'create-live',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const CreateLiveScreen()),
      ),
      GoRoute(
        path: AppRoutes.wallet,
        name: 'wallet',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const WalletScreen()),
      ),
      GoRoute(
        path: AppRoutes.walletTransactions,
        name: 'wallet-transactions',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const TransactionHistoryScreen()),
      ),
      GoRoute(
        path: AppRoutes.walletRecharge,
        name: 'wallet-recharge',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const RechargeScreen()),
      ),
      GoRoute(
        path: AppRoutes.walletWithdraw,
        name: 'wallet-withdraw',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const WithdrawScreen()),
      ),
      GoRoute(
        path: AppRoutes.voiceRooms,
        name: 'voice-rooms',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const VoiceRoomHome()),
      ),
      GoRoute(
        path: AppRoutes.createVoiceRoom,
        name: 'create-voice-room',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const CreateVoiceRoom()),
      ),
      GoRoute(
        path: AppRoutes.rankings,
        name: 'rankings',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const RankingHome()),
      ),
      GoRoute(
        path: AppRoutes.hostRanking,
        name: 'host-ranking',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const HostRanking()),
      ),
      GoRoute(
        path: AppRoutes.gifterRanking,
        name: 'gifter-ranking',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const GifterRanking()),
      ),
      GoRoute(
        path: AppRoutes.pkRanking,
        name: 'pk-ranking',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const PkRanking()),
      ),
      GoRoute(
        path: AppRoutes.voiceRanking,
        name: 'voice-ranking',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const VoiceRanking()),
      ),
      GoRoute(
        path: AppRoutes.vip,
        name: 'vip',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const VipHome()),
      ),
      GoRoute(
        path: AppRoutes.vipLevels,
        name: 'vip-levels',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const VipLevels()),
      ),
      GoRoute(
        path: AppRoutes.vipPurchase,
        name: 'vip-purchase',
        pageBuilder: (BuildContext context, GoRouterState state) => _socialPage(
          state,
          VipPurchase(initialLevelId: state.uri.queryParameters['levelId']),
        ),
      ),
      GoRoute(
        path: AppRoutes.vipBadge,
        name: 'vip-badge',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const VipProfileBadge()),
      ),
      GoRoute(
        path: AppRoutes.agency,
        name: 'agency',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const AgencyHome()),
      ),
      GoRoute(
        path: AppRoutes.createAgency,
        name: 'create-agency',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const CreateAgency()),
      ),
      GoRoute(
        path: AppRoutes.agencyProfile,
        name: 'agency-profile',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(
              state,
              AgencyProfile(
                agencyId: state.uri.queryParameters['agencyId'],
              ),
            ),
      ),
      GoRoute(
        path: AppRoutes.agencyHosts,
        name: 'agency-hosts',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(
              state,
              AgencyHosts(
                agencyId: state.uri.queryParameters['agencyId'],
              ),
            ),
      ),
      GoRoute(
        path: AppRoutes.agencyEarnings,
        name: 'agency-earnings',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(
              state,
              AgencyEarnings(
                agencyId: state.uri.queryParameters['agencyId'],
              ),
            ),
      ),
      GoRoute(
        path: '/live/:roomId',
        name: 'live-room',
        pageBuilder: (BuildContext context, GoRouterState state) {
          final String roomId = state.pathParameters['roomId']!;
          final LiveRoom room =
              state.extra as LiveRoom? ??
              LiveRoom(
                id: roomId,
                title: 'Live stream',
                channelName: roomId,
                hostName: 'Creator',
                viewerCount: 0,
                isLive: true,
              );
          return _socialPage(
            state,
            LiveRoomWithPkScreen(room: room),
          );
        },
      ),
      GoRoute(
        path: '/voice/:roomId',
        name: 'voice-room',
        pageBuilder: (BuildContext context, GoRouterState state) {
          final String roomId = state.pathParameters['roomId']!;
          final Object? extra = state.extra;
          final VoiceRoomConnection? connection = extra is VoiceRoomConnection
              ? extra
              : null;
          final VoiceRoom? room = extra is VoiceRoom ? extra : connection?.room;
          return _socialPage(
            state,
            VoiceRoomScreen(
              roomId: roomId,
              connection: connection,
              initialRoom: room,
            ),
          );
        },
      ),
      GoRoute(
        path: AppRoutes.editProfile,
        name: 'edit-profile',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const EditProfilePage()),
      ),
      GoRoute(
        path: AppRoutes.searchUsers,
        name: 'search-users',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const SearchUsersPage()),
      ),
      GoRoute(
        path: AppRoutes.blockedUsers,
        name: 'blocked-users',
        pageBuilder: (BuildContext context, GoRouterState state) =>
            _socialPage(state, const BlockedUsersPage()),
      ),
      GoRoute(
        path: '/users/:userId',
        name: 'user-details',
        pageBuilder: (BuildContext context, GoRouterState state) => _socialPage(
          state,
          UserDetailsPage(userId: state.pathParameters['userId']!),
        ),
      ),
      GoRoute(
        path: '/users/:userId/followers',
        name: 'followers',
        pageBuilder: (BuildContext context, GoRouterState state) => _socialPage(
          state,
          FollowersPage(
            userId: state.pathParameters['userId']!,
            userName: state.extra as String?,
          ),
        ),
      ),
      GoRoute(
        path: '/users/:userId/following',
        name: 'following',
        pageBuilder: (BuildContext context, GoRouterState state) => _socialPage(
          state,
          FollowingPage(
            userId: state.pathParameters['userId']!,
            userName: state.extra as String?,
          ),
        ),
      ),
      GoRoute(
        path: '/users/:userId/report',
        name: 'report-user',
        pageBuilder: (BuildContext context, GoRouterState state) => _socialPage(
          state,
          ReportUserPage(
            userId: state.pathParameters['userId']!,
            userName: state.extra as String?,
          ),
        ),
      ),
    ],
    errorBuilder: (BuildContext context, GoRouterState state) => AppErrorWidget(
      message: state.error?.toString() ?? AppStrings.unexpectedError,
    ),
  );
  ref.onDispose(() {
    refreshNotifier.dispose();
    router.dispose();
  });
  return router;
});

String _authenticatedDestination(User user) {
  if (!user.isAnonymous && user.email != null && !user.isEmailVerified) {
    return AppRoutes.verifyEmail;
  }
  if (!user.isAnonymous && (user.displayName?.trim().isEmpty ?? true)) {
    return AppRoutes.completeProfile;
  }
  return AppRoutes.home;
}

final class _RouterRefreshNotifier extends ChangeNotifier {
  _RouterRefreshNotifier(Stream<User?> stream) {
    _subscription = stream.listen(
      (_) => notifyListeners(),
      onError: (Object error, StackTrace stackTrace) => notifyListeners(),
    );
  }

  late final StreamSubscription<User?> _subscription;

  @override
  void dispose() {
    unawaited(_subscription.cancel());
    super.dispose();
  }
}

CustomTransitionPage<void> _authPage(GoRouterState state, Widget child) =>
    CustomTransitionPage<void>(
      key: state.pageKey,
      child: child,
      transitionsBuilder:
          (
            BuildContext context,
            Animation<double> animation,
            Animation<double> secondaryAnimation,
            Widget child,
          ) => FadeTransition(
            opacity: CurvedAnimation(
              parent: animation,
              curve: Curves.easeOutCubic,
            ),
            child: SlideTransition(
              position: Tween<Offset>(
                begin: const Offset(0, .025),
                end: Offset.zero,
              ).animate(animation),
              child: child,
            ),
          ),
    );

CustomTransitionPage<void> _socialPage(
  GoRouterState state,
  Widget child,
) => CustomTransitionPage<void>(
  key: state.pageKey,
  child: child,
  transitionsBuilder:
      (
        BuildContext context,
        Animation<double> animation,
        Animation<double> secondaryAnimation,
        Widget child,
      ) => FadeTransition(
        opacity: CurvedAnimation(parent: animation, curve: Curves.easeOut),
        child: SlideTransition(
          position: Tween<Offset>(begin: const Offset(.04, 0), end: Offset.zero)
              .animate(
                CurvedAnimation(parent: animation, curve: Curves.easeOutCubic),
              ),
          child: child,
        ),
      ),
);
