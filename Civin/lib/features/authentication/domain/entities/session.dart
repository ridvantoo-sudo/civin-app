import 'package:civin/features/authentication/domain/entities/device.dart';
import 'package:civin/features/authentication/domain/entities/token.dart';
import 'package:civin/features/authentication/domain/entities/user.dart';

final class Session {
  const Session({
    required this.user,
    required this.token,
    required this.device,
    required this.createdAt,
  });

  final User user;
  final Token token;
  final Device device;
  final DateTime createdAt;
}
