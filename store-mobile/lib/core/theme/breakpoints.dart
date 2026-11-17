/// Material 3 window size classes.
abstract final class Breakpoints {
  static const double medium = 600;
  static const double expanded = 840;

  /// Product grid columns for the available width.
  static int productColumns(double width) {
    if (width >= expanded) {
      return 4;
    }
    if (width >= medium) {
      return 3;
    }
    return 2;
  }
}
