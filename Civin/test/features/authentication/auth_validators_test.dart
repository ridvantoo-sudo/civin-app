import 'package:civin/features/authentication/domain/auth_validators.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  group('AuthValidators', () {
    test('accepts valid email and rejects malformed email', () {
      expect(AuthValidators.email('person@example.com'), isNull);
      expect(AuthValidators.email('person@'), isNotNull);
      expect(AuthValidators.email(''), isNotNull);
    });

    test('requires a strong password', () {
      expect(AuthValidators.password('Strong1!'), isNull);
      expect(AuthValidators.password('short'), isNotNull);
      expect(
        AuthValidators.passwordStrength('Strong1!'),
        PasswordStrength.strong,
      );
    });

    test('validates password confirmation', () {
      expect(AuthValidators.confirmPassword('Strong1!', 'Strong1!'), isNull);
      expect(
        AuthValidators.confirmPassword('Different1!', 'Strong1!'),
        isNotNull,
      );
    });

    test('normalizes and validates E.164 phone numbers', () {
      expect(AuthValidators.phone('+1 (555) 123-4567'), isNull);
      expect(
        AuthValidators.normalizePhone('+1 (555) 123-4567'),
        '+15551234567',
      );
      expect(AuthValidators.phone('5551234'), isNotNull);
    });

    test('requires a six digit OTP', () {
      expect(AuthValidators.otp('123456'), isNull);
      expect(AuthValidators.otp('12345'), isNotNull);
      expect(AuthValidators.otp('12345a'), isNotNull);
    });
  });
}
