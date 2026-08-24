package com.sroor.cofe.erp;

import android.app.DownloadManager;
import android.content.BroadcastReceiver;
import android.content.Context;
import android.content.Intent;
import android.content.IntentFilter;
import android.net.Uri;
import android.os.Build;
import android.os.Bundle;
import android.os.Environment;
import android.webkit.DownloadListener;
import android.webkit.WebView;
import android.widget.Toast;
import androidx.core.content.FileProvider;
import com.getcapacitor.BridgeActivity;
import java.io.File;

public class MainActivity extends BridgeActivity {

    private long downloadId = -1;

    @Override
    public void onCreate(Bundle savedInstanceState) {
        super.onCreate(savedInstanceState);

        // Configure WebView Native Download Handler
        if (getBridge() != null && getBridge().getWebView() != null) {
            WebView webView = getBridge().getWebView();
            webView.setDownloadListener(new DownloadListener() {
                @Override
                public void onDownloadStart(String url, String userAgent, String contentDisposition, String mimeType, long contentLength) {
                    if (url.endsWith(".apk") || (mimeType != null && mimeType.contains("vnd.android.package-archive")) || url.contains("download-apk") || url.contains("download-latest-apk")) {
                        downloadAndInstallApk(url);
                    } else {
                        // Open other downloads in standard system browser
                        try {
                            Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                            startActivity(intent);
                        } catch (Exception ignored) {}
                    }
                }
            });
        }

        // Register receiver for when the APK download finishes
        IntentFilter filter = new IntentFilter(DownloadManager.ACTION_DOWNLOAD_COMPLETE);
        try {
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                registerReceiver(onDownloadComplete, filter, Context.RECEIVER_EXPORTED);
            } else {
                registerReceiver(onDownloadComplete, filter);
            }
        } catch (Exception ignored) {}
    }

    private void downloadAndInstallApk(String url) {
        try {
            Toast.makeText(this, "جاري تنزيل ملف التحديث في الخلفية...", Toast.LENGTH_LONG).show();

            DownloadManager.Request request = new DownloadManager.Request(Uri.parse(url));
            request.setMimeType("application/vnd.android.package-archive");
            request.setTitle("منظومة ERP | تحديث جديد");
            request.setDescription("جاري تحميل أحدث إصدار من التطبيق...");
            request.setNotificationVisibility(DownloadManager.Request.VISIBILITY_VISIBLE_NOTIFY_COMPLETED);
            request.setDestinationInExternalPublicDir(Environment.DIRECTORY_DOWNLOADS, "sroor-erp-update.apk");

            DownloadManager manager = (DownloadManager) getSystemService(Context.DOWNLOAD_SERVICE);
            if (manager != null) {
                downloadId = manager.enqueue(request);
            }
        } catch (Exception e) {
            // Fallback: Open URL in default browser
            try {
                Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                startActivity(intent);
            } catch (Exception ignored) {}
        }
    }

    private final BroadcastReceiver onDownloadComplete = new BroadcastReceiver() {
        @Override
        public void onReceive(Context context, Intent intent) {
            long id = intent.getLongExtra(DownloadManager.EXTRA_DOWNLOAD_ID, -1);
            if (downloadId != -1 && downloadId == id) {
                installDownloadedApk();
            }
        }
    };

    private void installDownloadedApk() {
        try {
            File apkFile = new File(Environment.getExternalStoragePublicDirectory(Environment.DIRECTORY_DOWNLOADS), "sroor-erp-update.apk");
            if (apkFile.exists()) {
                Uri apkUri;
                if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.N) {
                    apkUri = FileProvider.getUriForFile(this, getPackageName() + ".fileprovider", apkFile);
                } else {
                    apkUri = Uri.fromFile(apkFile);
                }

                Intent installIntent = new Intent(Intent.ACTION_VIEW);
                installIntent.setDataAndType(apkUri, "application/vnd.android.package-archive");
                installIntent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
                installIntent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                startActivity(installIntent);
            }
        } catch (Exception e) {
            Toast.makeText(this, "يرجى فتح ملف التحديث من مجلد التحميلات (Downloads)", Toast.LENGTH_LONG).show();
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

    @Override
    public void onDestroy() {
        super.onDestroy();
        try {
            unregisterReceiver(onDownloadComplete);
        } catch (Exception ignored) {}
    }
}
