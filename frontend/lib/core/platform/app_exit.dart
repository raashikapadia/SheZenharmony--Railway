import 'dart:io' show exit;

import 'package:flutter/foundation.dart';
import 'package:flutter/services.dart';

/// Closes the application on purpose — used when a student declines the
/// survey consent, where the required outcome is that the app stops rather
/// than falls back to some other screen.
///
/// [SystemNavigator.pop] is the polite route and is all Android needs. iOS
/// ignores it by design and desktop targets are inconsistent, so after a
/// beat the process is ended outright. On the web there is no process to
/// end; the caller has already signed the user out, so a reload lands on the
/// sign-in screen.
Future<void> closeApplication() async {
  await SystemNavigator.pop(animated: true);
  if (kIsWeb) return;
  await Future<void>.delayed(const Duration(milliseconds: 400));
  exit(0);
}
