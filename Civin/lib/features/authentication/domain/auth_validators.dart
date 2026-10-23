enum PasswordStrength { empty, weak, fair, strong }

abstract final class AuthValidators {
  static final RegExp _email = RegExp(
    r'^[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}$',
    caseSensitive: false,
  );
  static final RegExp _phone = RegExp(r'^\+[1-9]\d{7,14}$');
  static final RegExp _otp = RegExp(r'^\d{6}$');

  static String? email(String? value) {
    final String input = value?.trim() ?? '';
    if (input.isEmpty) return 'Email is required.';
    if (!_email.hasMatch(input)) return 'Enter a valid email address.';
    return null;
  }

  static String? password(String? value) {
    final String input = value ?? '';
    if (input.isEmpty) return 'Password is required.';
    if (input.length < 8) return 'Use at least 8 characters.';
    if (!RegExp(r'[A-Z]').hasMatch(input)) {
      return 'Add at least one uppercase letter.';
    }
    if (!RegExp(r'[a-z]').hasMatch(input)) {
      return 'Add at least one lowercase letter.';
    }
    if (!RegExp(r'\d').hasMatch(input)) return 'Add at least one number.';
    if (!RegExp(r'[^A-Za-z0-9]').hasMatch(input)) {
      return 'Add at least one symbol.';
    }
    return null;
  }

  static PasswordStrength passwordStrength(String value) {
    if (value.isEmpty) return PasswordStrength.empty;
    var score = 0;
    if (value.length >= 8) score++;
    if (RegExp(r'[A-Z]').hasMatch(value) && RegExp(r'[a-z]').hasMatch(value)) {
      score++;
    }
    if (RegExp(r'\d').hasMatch(value)) score++;
    if (RegExp(r'[^A-Za-z0-9]').hasMatch(value)) score++;
    return switch (score) {
      >= 4 => PasswordStrength.strong,
      >= 2 => PasswordStrength.fair,
      _ => PasswordStrength.weak,
    };
  }

  static String? confirmPassword(String? value, String password) {
    if ((value ?? '').isEmpty) return 'Confirm your password.';
    if (value != password) return 'Passwords do not match.';
    return null;
  }

  static String? phone(String? value) {
    final String input = (value ?? '').replaceAll(RegExp(r'[\s()-]'), '');
    if (input.isEmpty) return 'Phone number is required.';
    if (!_phone.hasMatch(input)) {
      return 'Use international format, for example +15551234567.';
    }
    return null;
  }

  static String normalizePhone(String value) =>
      value.replaceAll(RegExp(r'[\s()-]'), '');

  static String? otp(String? value) {
    if (!_otp.hasMatch(value ?? '')) return 'Enter the 6-digit code.';
    return null;
  }

  static String? displayName(String? value) {
    final String input = value?.trim() ?? '';
    if (input.length < 2) return 'Enter at least 2 characters.';
    if (input.length > 50) return 'Use no more than 50 characters.';
    return null;
  }
}
