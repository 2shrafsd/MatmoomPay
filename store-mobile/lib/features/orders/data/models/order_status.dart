/// Payment status of a Matjar order, as confirmed by Matjar with the gateway.
enum OrderStatus {
  pending,
  paid,
  failed,
  cancelled,
  expired,
  abandoned,

  /// A value this app version does not know yet; treated like a final state
  /// that is not a success.
  unknown;

  static OrderStatus fromApi(String? value) {
    for (final status in values) {
      if (status.name == value) {
        return status;
      }
    }
    return unknown;
  }

  bool get isFinal => this != pending;
}
