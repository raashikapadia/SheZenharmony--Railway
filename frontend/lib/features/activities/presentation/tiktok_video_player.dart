import 'dart:async';
import 'dart:convert';

import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';
import 'package:webview_flutter_wkwebview/webview_flutter_wkwebview.dart';

/// Plays a TikTok video inside the app by hosting TikTok's official embed
/// player in a webview.
///
/// TikTok has no Flutter SDK, but every public video is available at
/// `https://www.tiktok.com/embed/v2/<id>`, which serves a self-contained
/// player. The only work is turning whatever link an admin pasted into that
/// numeric id — share links such as `vm.tiktok.com/XXXX` don't contain one, so
/// those are resolved through TikTok's public oEmbed endpoint.
class TikTokVideoPlayer extends StatefulWidget {
  const TikTokVideoPlayer({
    super.key,
    required this.url,
    required this.onFallback,
    this.httpClient,
  });

  /// A TikTok link with a scheme, e.g. `https://www.tiktok.com/@you/video/123`.
  final String url;

  /// Shown when the video can't be embedded (bad link, no network, private
  /// video) so the student can still open it in the TikTok app.
  final WidgetBuilder onFallback;

  final http.Client? httpClient;

  /// Whether this platform can host the embed. `webview_flutter` only ships
  /// Android and iOS implementations, so everything else keeps the launch card.
  static bool get isSupported {
    if (kIsWeb) return false;
    return defaultTargetPlatform == TargetPlatform.android ||
        defaultTargetPlatform == TargetPlatform.iOS;
  }

  static bool isTikTokUrl(String url) {
    final host = Uri.tryParse(url)?.host.toLowerCase() ?? '';
    return host == 'tiktok.com' || host.endsWith('.tiktok.com');
  }

  @override
  State<TikTokVideoPlayer> createState() => _TikTokVideoPlayerState();
}

class _TikTokVideoPlayerState extends State<TikTokVideoPlayer> {
  late Future<WebViewController> _controller;
  http.Client? _ownedClient;

  @override
  void initState() {
    super.initState();
    _controller = _prepare();
  }

  @override
  void dispose() {
    _ownedClient?.close();
    super.dispose();
  }

  Future<WebViewController> _prepare() async {
    final videoId = await _resolveVideoId(widget.url);
    if (videoId == null) throw const _EmbedUnavailable();

    // Inline playback and gesture-free autoplay have to be requested at
    // creation time on iOS; on Android they are set on the controller below.
    final params = switch (WebViewPlatform.instance) {
      WebKitWebViewPlatform() => WebKitWebViewControllerCreationParams(
        allowsInlineMediaPlayback: true,
        mediaTypesRequiringUserAction: const <PlaybackMediaTypes>{},
      ),
      _ => const PlatformWebViewControllerCreationParams(),
    };

    final controller = WebViewController.fromPlatformCreationParams(params)
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setBackgroundColor(Colors.black)
      ..setNavigationDelegate(
        NavigationDelegate(
          // Keep the webview on the embed page. Taps on the TikTok
          // watermark or the author's profile would otherwise navigate this
          // small frame somewhere it cannot render, so hand those to TikTok
          // itself.
          onNavigationRequest: (request) {
            if (request.url.contains('/embed/')) {
              return NavigationDecision.navigate;
            }
            unawaited(
              launchUrl(
                Uri.parse(request.url),
                mode: LaunchMode.externalApplication,
              ).catchError((_) => false),
            );
            return NavigationDecision.prevent;
          },
        ),
      );

    if (controller.platform case final AndroidWebViewController android) {
      await android.setMediaPlaybackRequiresUserGesture(false);
    }

    await controller.loadRequest(
      Uri.parse('https://www.tiktok.com/embed/v2/$videoId'),
    );
    return controller;
  }

  /// Pulls the numeric video id out of the link, falling back to oEmbed for
  /// short share links that only carry an opaque slug.
  Future<String?> _resolveVideoId(String url) async {
    final direct = RegExp(r'/(?:video|photo)/(\d+)').firstMatch(url);
    if (direct != null) return direct.group(1);

    final client = widget.httpClient ?? (_ownedClient ??= http.Client());
    try {
      final response = await client
          .get(
            Uri.https('www.tiktok.com', '/oembed', {'url': url}),
            headers: const {'Accept': 'application/json'},
          )
          .timeout(const Duration(seconds: 10));
      if (response.statusCode != 200) return null;
      final body = jsonDecode(response.body);
      if (body is! Map<String, dynamic>) return null;
      final id = body['embed_product_id'] as String?;
      if (id != null && id.isNotEmpty) return id;
      // Older responses only carry the canonical URL.
      final canonical = body['embed_html'] as String? ?? '';
      return RegExp(r'/(?:video|photo)/(\d+)').firstMatch(canonical)?.group(1);
    } catch (_) {
      return null;
    }
  }

  @override
  Widget build(BuildContext context) => FutureBuilder<WebViewController>(
    future: _controller,
    builder: (context, snapshot) {
      if (snapshot.hasError) return widget.onFallback(context);
      final controller = snapshot.data;
      return AspectRatio(
        // TikTok's embed is a portrait video plus a caption strip; this keeps
        // the whole card visible without the frame scrolling internally.
        aspectRatio: 9 / 15,
        child: ColoredBox(
          color: Colors.black,
          child: controller == null
              ? const Center(
                  child: CircularProgressIndicator(color: Colors.white70),
                )
              : WebViewWidget(controller: controller),
        ),
      );
    },
  );
}

class _EmbedUnavailable implements Exception {
  const _EmbedUnavailable();
}
