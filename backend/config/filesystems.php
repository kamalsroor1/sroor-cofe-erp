<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * PKG-2 central (platform) disks. They must NEVER be listed in
         * tenancy.filesystem.disks: FilesystemTenancyBootstrapper re-roots the listed
         * disks and suffixes storage_path() per tenant. The roots below are absolute
         * and resolved when the config loads (central context), so files written from
         * a tenant request still land outside every storage/tenant<id>/ directory.
         * Used only through App\Models\CentralMedia.
         */

        // Payment receipts (ENTI-3.3). Never served directly: signed route only.
        'central_private' => [
            'driver' => 'local',
            'root' => storage_path('app/central/private'),
            'visibility' => 'private',
            'serve' => false,
            'throw' => false,
            'report' => false,
        ],

        // App release binaries (APK / desktop installer) read from a tenant host. Same
        // physical root as the central 'public' disk, where the super-admin uploads them,
        // but never re-rooted by tenancy (not in tenancy.filesystem.disks). Read through
        // App\Models\AppVersion::releaseDisk() only.
        'app_releases' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        // Platform branding assets (BRND-2): logo, favicon, app icon.
        'central_public' => [
            'driver' => 'local',
            'root' => storage_path('app/central/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/central-assets',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        /*
         * OPS-5 backup target (CTO decision D3): Google Drive of the owner's account
         * through OAuth (a service account has no Drive quota). Driver registered in
         * App\Providers\HealthServiceProvider (masbug/flysystem-google-drive-ext).
         * The refresh token is created once with the owner's consent and lives ONLY in
         * the server .env (docs/07-operations/backup-restore.md §2).
         * GOOGLE_DRIVE_FOLDER_ID = the dedicated backups folder; empty = My Drive root
         * (the archives still go under backup.tenants.path_prefix).
         * Never listed in tenancy.filesystem.disks: backups are written from central
         * context only.
         */
        'google' => [
            'driver' => 'google',
            'clientId' => env('GOOGLE_DRIVE_CLIENT_ID'),
            'clientSecret' => env('GOOGLE_DRIVE_CLIENT_SECRET'),
            'refreshToken' => env('GOOGLE_DRIVE_REFRESH_TOKEN'),
            'folderId' => env('GOOGLE_DRIVE_FOLDER_ID'),
            'throw' => true,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
        public_path('central-assets') => storage_path('app/central/public'),
    ],

];
