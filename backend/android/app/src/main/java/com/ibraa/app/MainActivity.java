package com.ibraa.app;

import android.content.Intent;
import android.net.Uri;
import android.os.Bundle;
import android.webkit.WebView;
import com.getcapacitor.BridgeActivity;

public class MainActivity extends BridgeActivity {

    @Override
    public void onCreate(Bundle savedInstanceState) {
        // MOB-03: flavor plugins. direct = AppUpdaterPlugin (APK self-update, SHA-256 + signer verification);
        // play = PlayStoreBuildPlugin marker only (Play owns updates, no install permission).
        for (Class<? extends com.getcapacitor.Plugin> plugin : FlavorPlugins.all()) {
            registerPlugin(plugin);
        }
        super.onCreate(savedInstanceState);

        if (getBridge() != null && getBridge().getWebView() != null) {
            WebView webView = getBridge().getWebView();
            // Generic file downloads (exports, PDFs…) open in the system browser. There is deliberately no
            // silent APK download/install path here: it bypassed checksum and signer verification.
            webView.setDownloadListener((url, userAgent, contentDisposition, mimeType, contentLength) -> {
                try {
                    startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
                } catch (Exception ignored) {
                    // no browser available; nothing else to do
                }
            });
        }
    }

    @Override
    public void onBackPressed() {
        if (getBridge() != null && getBridge().getWebView() != null) {
            WebView webView = getBridge().getWebView();
            if (webView.canGoBack()) {
                webView.goBack();
                return;
            }
        }
        super.onBackPressed();
    }
}
