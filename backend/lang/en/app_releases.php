<?php

// APP-3 extension (W2 batch 5): release upload validation, update check texts and the
// app-releases:relocate command. Keep the key set identical to lang/ar/app_releases.php.
return [
    // Upload (POST /api/v1/super-admin/app-versions): 422 messages.
    'version_not_newer' => 'The version code must be higher than every existing :platform release (current highest: :max).',
    'version_code_taken' => 'A :platform release with this version code already exists.',
    'windows_code_mismatch' => 'For Windows the version code must match the version name (:name gives :code).',
    'version_name_format' => 'The version name must look like 1.2.3 (three numbers of up to 3 digits each).',
    'platform_not_supported' => 'Releases can only be published for Android or Windows.',
    'extension_mismatch' => 'The file type does not match the platform: Android needs an .apk file and Windows an .exe installer.',
    'min_version_too_high' => 'The minimum supported version code cannot be higher than the release version code.',
    'invalid_binary' => 'The uploaded file is not a valid release package for this platform.',
    'binary_errors' => [
        'mime_not_allowed' => 'The file content type is not an Android package or a Windows executable.',
        'unreadable' => 'The uploaded file could not be read.',
        'zip_magic' => 'The file is not a ZIP-based Android package.',
        'zip_unreadable' => 'The Android package could not be opened as a ZIP archive.',
        'unsafe_entry' => 'The Android package contains an unsafe file path.',
        'manifest_missing' => 'The Android package has no AndroidManifest.xml.',
        'dex_missing' => 'The Android package has no compiled code (classes.dex).',
        'signing_block_missing' => 'The Android package is not signed with APK Signature Scheme v2 or v3.',
        'signing_block_too_large' => 'The Android package signing block is too large to be a real signature.',
        'mz_magic' => 'The file is not a Windows executable (missing MZ header).',
        'pe_header' => 'The Windows executable has a damaged PE header.',
        'machine_not_allowed' => 'The Windows executable is not built for x64, x86 or ARM64.',
        'not_executable' => 'The Windows file is not marked as an executable program.',
        'is_dll' => 'The Windows file is a library (DLL), not an installer.',
    ],
    'platforms' => [
        'android' => 'Android',
        'windows' => 'Windows',
    ],
    'store_failed' => 'The release file could not be saved. Please try again.',
    'binary_write_failed' => 'The release binary could not be written to the private release disk.',

    // Update check (GET /api/v1/app/check-update).
    'check' => [
        'title_force' => 'Required update available',
        'title_optional' => 'New update available',
        'title_up_to_date' => 'Your app is up to date',
        'message_available' => 'Version :version of the app is available.',
        'message_force' => 'Version :version is required to keep using the app. Please update now.',
        'message_up_to_date' => 'You are using the latest version of the app.',
    ],
    'size' => [
        'bytes' => ':size B',
        'kilobytes' => ':size KB',
        'megabytes' => ':size MB',
    ],

    // php artisan app-releases:relocate
    'relocate' => [
        'description' => 'Copy app release binaries from the public disk to the private release disk; --prune-legacy then deletes verified public copies (idempotent)',
        'tenant_context' => 'Run this command from the central context, not inside a tenant.',
        'dry_run' => 'Dry run: nothing is copied or deleted.',
        'nothing_to_do' => 'No release binaries left on the public disk.',
        'would_copy' => 'Would copy :path',
        'copied' => 'Copied and verified :path (public copy kept)',
        'already_private' => 'Already on the private disk with the same SHA-256: :path',
        'pruned' => 'Deleted the verified public copy of :path',
        'prune_skipped' => 'Public copy of :path kept: the private copy does not match its SHA-256.',
        'conflict' => 'Not copied: :path exists on both disks with different content. Resolve it by hand.',
        'failed' => 'Failed to copy :path',
        'public_binary_found' => 'Still web-served, delete it by hand: public/:file',
        'summary' => 'Copied: :copied, already private: :verified, public copies deleted: :pruned, conflicts: :conflicts, failed: :failed.',
    ],
];
