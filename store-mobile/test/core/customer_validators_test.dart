import 'package:flutter_test/flutter_test.dart';
import 'package:matjar_app/core/utils/customer_validators.dart';

void main() {
  test('name is required and at most 80 characters', () {
    expect(CustomerValidators.name('  '), CustomerNameError.required);
    expect(CustomerValidators.name('ا' * 81), CustomerNameError.tooLong);
    expect(CustomerValidators.name('آمنة عثمان'), isNull);
  });

  test('phone follows ^\\+?[0-9 ]{8,16}\$', () {
    expect(CustomerValidators.phone(''), CustomerPhoneError.required);
    expect(CustomerValidators.phone('0912'), CustomerPhoneError.invalid);
    expect(CustomerValidators.phone('09123-45678'), CustomerPhoneError.invalid);
    expect(CustomerValidators.phone('+249 912 345 678'), isNull);
    expect(CustomerValidators.phone('0912345678'), isNull);
  });
}
