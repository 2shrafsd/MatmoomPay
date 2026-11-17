/// Client-side checks that mirror Matjar's validation rules, so most mistakes
/// are caught before a request. The server stays the source of truth.
enum CustomerNameError { required, tooLong }

enum CustomerPhoneError { required, invalid }

abstract final class CustomerValidators {
  static const int nameMaxLength = 80;
  static final RegExp phonePattern = RegExp(r'^\+?[0-9 ]{8,16}$');

  static CustomerNameError? name(String? value) {
    final trimmed = value?.trim() ?? '';
    if (trimmed.isEmpty) {
      return CustomerNameError.required;
    }
    if (trimmed.length > nameMaxLength) {
      return CustomerNameError.tooLong;
    }
    return null;
  }

  static CustomerPhoneError? phone(String? value) {
    final trimmed = value?.trim() ?? '';
    if (trimmed.isEmpty) {
      return CustomerPhoneError.required;
    }
    if (!phonePattern.hasMatch(trimmed)) {
      return CustomerPhoneError.invalid;
    }
    return null;
  }
}
