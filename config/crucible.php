<?php

return [
    'forge' => [
        'enabled'       => env('FORGE_ENABLED', false),
        'url'           => env('FORGE_URL', ''),

        // SSO — OAuth 2.0 authorization code grant (Sign in with Forge).
        // Create a Passport client in Forge: Passport OAuth clients → New Client
        'client_id'     => env('FORGE_CLIENT_ID', ''),
        'client_secret' => env('FORGE_CLIENT_SECRET', ''),
        'redirect_uri'  => env('FORGE_REDIRECT_URI', env('APP_URL', 'http://localhost').'/auth/forge/callback'),

        // M2M — OAuth 2.0 client credentials grant (server-to-server calls).
        // Generate in Forge with: php artisan passport:client --client --name="Crucible M2M"
        'm2m_client_id'     => env('FORGE_M2M_CLIENT_ID', ''),
        'm2m_client_secret' => env('FORGE_M2M_CLIENT_SECRET', ''),

        // Set to true in local dev when Forge uses a self-signed / localhost cert.
        'disable_tls_verification' => env('FORGE_DISABLE_TLS_VERIFICATION', false),
    ],
    'git' => [
        'backend'       => env('CRUCIBLE_GIT_BACKEND', 'stub'), // stub|native|service
        'service_url'   => env('CRUCIBLE_GIT_SERVICE_URL', ''),
        'service_token' => env('CRUCIBLE_GIT_SERVICE_TOKEN', ''),
        'repos_path'    => env('CRUCIBLE_REPOS_PATH', storage_path('repositories')),
    ],
    'lfs' => [
        'enabled'       => env('CRUCIBLE_LFS_ENABLED', true),
        'storage_disk'  => env('CRUCIBLE_LFS_DISK', 'local'),
        'backend'       => env('CRUCIBLE_LFS_BACKEND', 'local'), // local | s3
        's3_disk'       => env('CRUCIBLE_LFS_S3_DISK', 's3'),
        'chunked_threshold_bytes' => env('CRUCIBLE_LFS_CHUNKED_THRESHOLD', 100 * 1024 * 1024), // 100 MB
    ],

    'ssh' => [
        // System user that sshd runs git connections as (used in setup instructions).
        'system_user'   => env('CRUCIBLE_SSH_USER', 'git'),

        // Path to the PHP binary used in authorized_keys forced commands.
        // Defaults to the binary running this process; override if sshd runs as
        // a different user whose PATH doesn't resolve the same php.
        'php_binary'    => env('CRUCIBLE_SSH_PHP_BINARY', PHP_BINARY),

        // Absolute path to the artisan binary (auto-detected from base_path()).
        // Only override if the application lives outside the web root.
        'artisan_path'  => env('CRUCIBLE_SSH_ARTISAN_PATH', ''),
    ],
];
