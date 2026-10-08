package com.sroor.cofe.erp;

import android.content.Intent;
import android.content.pm.ApplicationInfo;
import android.content.pm.PackageInfo;
import android.content.pm.PackageManager;
import android.content.pm.Signature;
import android.net.Uri;
import android.os.Build;
import android.provider.Settings;
import androidx.core.content.FileProvider;
import com.getcapacitor.JSObject;
import com.getcapacitor.Plugin;
import com.getcapacitor.PluginCall;
import com.getcapacitor.PluginMethod;
import com.getcapacitor.annotation.CapacitorPlugin;
import java.io.File;
import java.io.FileInputStream;
import java.io.FileOutputStream;
import java.io.IOException;
import java.io.InputStream;
import java.io.OutputStream;
import java.net.HttpURLConnection;
import java.net.URL;
import java.security.MessageDigest;
import java.security.NoSuchAlgorithmException;
import java.util.HashSet;
import java.util.Locale;
import java.util.Set;
import java.util.concurrent.ExecutorService;
import java.util.concurrent.Executors;
import java.util.concurrent.atomic.AtomicBoolean;

/**
 * APP-3: real in-app APK update.
 *
 * downloadAndInstall({ url, sha256, expectedSize, versionCode })
 *   1. streams the APK from the URL the API returned (https only in release builds),
 *      emitting "downloadProgress" { bytes, total, stage } from the real byte count;
 *   2. verifies the SHA-256 published in app_versions while streaming;
 *   3. verifies the archive is this package and is signed by the same certificate as the installed app;
 *   4. opens the system installer through the FileProvider.
 *
 * Rejections carry a stable code (CHECKSUM_MISMATCH, SIGNATURE_MISMATCH, SIGNATURE_UNVERIFIABLE, PACKAGE_MISMATCH,
 * INSTALL_PERMISSION_REQUIRED, INSECURE_URL, INVALID_ARGS, FILE_TOO_LARGE, DOWNLOAD_FAILED, INSTALL_FAILED);
 * the web layer maps them to translated messages. No user-facing text lives here.
 */
@CapacitorPlugin(name = "AppUpdater")
public class AppUpdaterPlugin extends Plugin {

    private static final String UPDATE_DIR = "app-updates";
    private static final long MAX_APK_BYTES = 300L * 1024L * 1024L;
    private static final long PROGRESS_INTERVAL_MS = 200L;

    private final ExecutorService executor = Executors.newSingleThreadExecutor();
    private final AtomicBoolean busy = new AtomicBoolean(false);

    private static final class UpdateException extends Exception {
        private static final long serialVersionUID = 1L;
        final String code;

        UpdateException(String code, String message) {
            super(message);
            this.code = code;
        }
    }

    @PluginMethod
    public void downloadAndInstall(PluginCall call) {
        final String url = call.getString("url");
        final String sha256 = call.getString("sha256");
        final Long expectedSize = call.getLong("expectedSize", 0L);

        if (url == null || sha256 == null || !sha256.matches("(?i)^[a-f0-9]{64}$")) {
            call.reject("url and a 64-char sha256 are required", "INVALID_ARGS");
            return;
        }

        Uri parsed = Uri.parse(url);
        String scheme = parsed.getScheme() == null ? "" : parsed.getScheme().toLowerCase(Locale.ROOT);
        boolean allowHttp = isDebuggable() && "http".equals(scheme);
        if (!"https".equals(scheme) && !allowHttp) {
            call.reject("update url must be https", "INSECURE_URL");
            return;
        }

        if (!canInstallPackages()) {
            openInstallPermissionSettings();
            call.reject("install permission required", "INSTALL_PERMISSION_REQUIRED");
            return;
        }

        if (!busy.compareAndSet(false, true)) {
            call.reject("an update is already in progress", "DOWNLOAD_FAILED");
            return;
        }

        executor.execute(() -> {
            try {
                File apk = obtainVerifiedApk(url, sha256.toLowerCase(Locale.ROOT), expectedSize == null ? 0L : expectedSize);
                emitProgress(apk.length(), apk.length(), "verifying");
                verifyPackageAndSigner(apk);
                launchInstaller(apk);
                JSObject result = new JSObject();
                result.put("status", "installer_opened");
                call.resolve(result);
            } catch (UpdateException e) {
                call.reject(e.getMessage(), e.code);
            } catch (Exception e) {
                call.reject(e.getMessage(), "DOWNLOAD_FAILED");
            } finally {
                busy.set(false);
            }
        });
    }

    // ---- download + checksum ------------------------------------------------------------------------------------

    private File obtainVerifiedApk(String url, String sha256, long expectedSize) throws UpdateException {
        File dir = new File(getContext().getCacheDir(), UPDATE_DIR);
        if (!dir.exists() && !dir.mkdirs()) {
            throw new UpdateException("DOWNLOAD_FAILED", "cannot create update dir");
        }

        File target = new File(dir, "update-" + sha256 + ".apk");

        // A previously downloaded file for the same checksum is reused (no re-download when the user re-opens
        // the installer), but only after hashing it again.
        if (target.exists()) {
            emitProgress(target.length(), target.length(), "verifying");
            if (sha256.equals(hashFile(target))) {
                return target;
            }
            deleteQuietly(target);
        }

        clearDir(dir);
        File partial = new File(dir, "update-" + sha256 + ".part");
        String actual = download(url, partial, expectedSize);

        if (!sha256.equals(actual)) {
            deleteQuietly(partial);
            throw new UpdateException("CHECKSUM_MISMATCH", "sha256 mismatch");
        }
        if (!partial.renameTo(target)) {
            deleteQuietly(partial);
            throw new UpdateException("DOWNLOAD_FAILED", "cannot finalize download");
        }
        return target;
    }

    private String download(String url, File out, long expectedSize) throws UpdateException {
        HttpURLConnection conn = null;
        try {
            conn = (HttpURLConnection) new URL(url).openConnection();
            conn.setConnectTimeout(15000);
            conn.setReadTimeout(30000);
            conn.setInstanceFollowRedirects(true);
            conn.setRequestProperty("Accept", "application/vnd.android.package-archive, application/octet-stream");
            int status = conn.getResponseCode();
            if (status != HttpURLConnection.HTTP_OK) {
                throw new UpdateException("DOWNLOAD_FAILED", "http " + status);
            }

            long total = conn.getContentLengthLong();
            if (total <= 0) {
                total = expectedSize;
            }
            if (total > MAX_APK_BYTES) {
                throw new UpdateException("FILE_TOO_LARGE", "apk too large");
            }

            MessageDigest digest = sha256Digest();
            long bytes = 0;
            long lastEmit = 0;
            emitProgress(0, total, "downloading");

            try (InputStream in = conn.getInputStream(); OutputStream os = new FileOutputStream(out)) {
                byte[] buffer = new byte[64 * 1024];
                int read;
                while ((read = in.read(buffer)) != -1) {
                    os.write(buffer, 0, read);
                    digest.update(buffer, 0, read);
                    bytes += read;
                    if (bytes > MAX_APK_BYTES) {
                        throw new UpdateException("FILE_TOO_LARGE", "apk too large");
                    }
                    long now = System.currentTimeMillis();
                    if (now - lastEmit >= PROGRESS_INTERVAL_MS) {
                        lastEmit = now;
                        emitProgress(bytes, total, "downloading");
                    }
                }
            }
            emitProgress(bytes, total > 0 ? total : bytes, "downloading");
            return toHex(digest.digest());
        } catch (UpdateException e) {
            deleteQuietly(out);
            throw e;
        } catch (IOException e) {
            deleteQuietly(out);
            throw new UpdateException("DOWNLOAD_FAILED", e.getMessage());
        } finally {
            if (conn != null) {
                conn.disconnect();
            }
        }
    }

    private String hashFile(File file) throws UpdateException {
        MessageDigest digest = sha256Digest();
        try (InputStream in = new FileInputStream(file)) {
            byte[] buffer = new byte[64 * 1024];
            int read;
            while ((read = in.read(buffer)) != -1) {
                digest.update(buffer, 0, read);
            }
        } catch (IOException e) {
            throw new UpdateException("DOWNLOAD_FAILED", e.getMessage());
        }
        return toHex(digest.digest());
    }

    // ---- package + signing certificate --------------------------------------------------------------------------

    private void verifyPackageAndSigner(File apk) throws UpdateException {
        PackageManager pm = getContext().getPackageManager();
        String ownPackage = getContext().getPackageName();

        PackageInfo archive = readArchive(pm, apk.getAbsolutePath());
        if (archive == null) {
            throw new UpdateException("SIGNATURE_UNVERIFIABLE", "cannot parse apk");
        }
        if (!ownPackage.equals(archive.packageName)) {
            throw new UpdateException("PACKAGE_MISMATCH", "package mismatch");
        }

        Set<String> archiveSigners = signerDigests(archive);
        Set<String> installedSigners;
        try {
            installedSigners = signerDigests(readInstalled(pm, ownPackage));
        } catch (PackageManager.NameNotFoundException e) {
            throw new UpdateException("SIGNATURE_UNVERIFIABLE", "installed package not found");
        }

        if (archiveSigners.isEmpty() || installedSigners.isEmpty()) {
            throw new UpdateException("SIGNATURE_UNVERIFIABLE", "missing signer info");
        }

        // Signer sets include the rotation lineage on API 28+, so an overlap accepts a legitimate key rotation
        // (which needs the old private key) and rejects any APK signed by a different key.
        Set<String> overlap = new HashSet<>(installedSigners);
        overlap.retainAll(archiveSigners);
        if (overlap.isEmpty()) {
            throw new UpdateException("SIGNATURE_MISMATCH", "signing certificate mismatch");
        }
    }

    @SuppressWarnings("deprecation")
    private PackageInfo readArchive(PackageManager pm, String path) {
        PackageInfo info = null;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            info = pm.getPackageArchiveInfo(path, PackageManager.GET_SIGNING_CERTIFICATES);
            if (info != null && info.signingInfo != null) {
                return info;
            }
        }
        // Some API 28-32 builds return no signingInfo for archives; GET_SIGNATURES still works there.
        return pm.getPackageArchiveInfo(path, PackageManager.GET_SIGNATURES);
    }

    @SuppressWarnings("deprecation")
    private PackageInfo readInstalled(PackageManager pm, String pkg) throws PackageManager.NameNotFoundException {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            return pm.getPackageInfo(pkg, PackageManager.GET_SIGNING_CERTIFICATES);
        }
        return pm.getPackageInfo(pkg, PackageManager.GET_SIGNATURES);
    }

    @SuppressWarnings("deprecation")
    private Set<String> signerDigests(PackageInfo info) throws UpdateException {
        Set<String> out = new HashSet<>();
        if (info == null) {
            return out;
        }
        Signature[] signatures = null;
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P && info.signingInfo != null) {
            signatures = info.signingInfo.hasMultipleSigners()
                ? info.signingInfo.getApkContentsSigners()
                : info.signingInfo.getSigningCertificateHistory();
        }
        if (signatures == null || signatures.length == 0) {
            signatures = info.signatures;
        }
        if (signatures == null) {
            return out;
        }
        for (Signature signature : signatures) {
            MessageDigest digest = sha256Digest();
            out.add(toHex(digest.digest(signature.toByteArray())));
        }
        return out;
    }

    // ---- installer ----------------------------------------------------------------------------------------------

    private boolean canInstallPackages() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            return getContext().getPackageManager().canRequestPackageInstalls();
        }
        return true;
    }

    private void openInstallPermissionSettings() {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) {
            return;
        }
        try {
            Intent intent = new Intent(Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, Uri.parse("package:" + getContext().getPackageName()));
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            getContext().startActivity(intent);
        } catch (Exception ignored) {
            // The JS layer still shows the translated INSTALL_PERMISSION_REQUIRED message.
        }
    }

    private void launchInstaller(File apk) throws UpdateException {
        try {
            Uri uri = FileProvider.getUriForFile(getContext(), getContext().getPackageName() + ".fileprovider", apk);
            Intent intent = new Intent(Intent.ACTION_VIEW);
            intent.setDataAndType(uri, "application/vnd.android.package-archive");
            intent.addFlags(Intent.FLAG_GRANT_READ_URI_PERMISSION);
            intent.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            getContext().startActivity(intent);
        } catch (Exception e) {
            throw new UpdateException("INSTALL_FAILED", e.getMessage());
        }
    }

    // ---- helpers ------------------------------------------------------------------------------------------------

    private void emitProgress(long bytes, long total, String stage) {
        JSObject data = new JSObject();
        data.put("bytes", bytes);
        data.put("total", total);
        data.put("stage", stage);
        notifyListeners("downloadProgress", data);
    }

    private boolean isDebuggable() {
        return (getContext().getApplicationInfo().flags & ApplicationInfo.FLAG_DEBUGGABLE) != 0;
    }

    private static MessageDigest sha256Digest() throws UpdateException {
        try {
            return MessageDigest.getInstance("SHA-256");
        } catch (NoSuchAlgorithmException e) {
            throw new UpdateException("DOWNLOAD_FAILED", "sha-256 unavailable");
        }
    }

    private static String toHex(byte[] bytes) {
        StringBuilder sb = new StringBuilder(bytes.length * 2);
        for (byte b : bytes) {
            sb.append(String.format(Locale.ROOT, "%02x", b));
        }
        return sb.toString();
    }

    private static void clearDir(File dir) {
        File[] files = dir.listFiles();
        if (files == null) {
            return;
        }
        for (File f : files) {
            deleteQuietly(f);
        }
    }

    private static void deleteQuietly(File f) {
        if (f != null && f.exists() && !f.delete()) {
            f.deleteOnExit();
        }
    }
}
