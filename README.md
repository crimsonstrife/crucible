# Crucible SCM

A self-hosted source control management platform built with Laravel 12, designed for game development teams working with Unreal Engine, Unity, and Godot. Crucible provides Git hosting with first-class support for large binary assets, file locking, visual diffs, and team workflows.

## Features

### Core SCM
- **Git hosting** with bare repository management (native backend)
- **SSH and HTTP** transport protocols
- **Organizations** with member roles and repository grouping
- **Repository browser** with tree view, file contents, commit history
- **Pull requests** with merge strategies (merge commit, squash, rebase, fast-forward)
- **Branch protection** rules with glob patterns, required approvals, and status checks
- **PR reviews** with approval states (approved, changes requested, commented)
- **Draft PRs** with mark-ready workflow

### Binary & Large File Support
- **Git LFS** with pluggable backends (local filesystem, S3)
- **Chunked uploads** for files over 100 MB (configurable threshold)
- **TUS v1.0.0** resumable upload protocol for multi-GB game assets
- **File locking** with auto-expiration and lock policies per file pattern
- **LFS policies** and `.gitattributes` generation from templates
- **Organization storage quotas** with enforcement in LFS batch uploads

### Game Engine Integration
- **Auto-detection** of Unreal Engine, Unity, and Godot projects
- **Unreal `.uasset` parser** — extracts metadata, asset class, dependencies, engine version (UE 4.18-5.5+)
- **Engine-specific templates** for LFS tracking and lock policies
- **Visual diff** for images (side-by-side, overlay, swipe), audio, and video
- **Binary metadata extraction** (image dimensions, WAV headers, GLB/OBJ stats)

### Workspace & Partial Sync
- **Sparse checkout profiles** — named path sets (Art Only, Code Only, Level Design) for partial clones
- **`.crucible/workspace.json`** — repository-level workspace configuration with auto-sync
- **Coverage estimation** — see what percentage of the repo each profile covers
- **Clone command generation** — ready-to-paste `git clone --filter=blob:none --sparse` commands

### Webhooks & CI
- **Outgoing webhooks** with HMAC-SHA256 signed payloads
- **Event subscriptions** — push, PR open/merge/close, reviews, file lock/unlock
- **Commit status API** — CI systems report pending/success/failure/error per context
- **Branch protection integration** — block merges until required status checks pass

### Administration
- **Filament admin panel** with Spatie permissions
- **Storage monitoring** with per-repo and per-org usage tracking
- **Scheduled maintenance** commands for size recalculation and cleanup

---

## Requirements

| Dependency | Version |
|---|---|
| PHP | 8.3+ |
| Composer | 2.x |
| Git | 2.38+ (for `merge-tree --write-tree`) |
| Node.js | 18+ (for asset compilation) |
| Database | MySQL 8.0+ / MariaDB 10.6+ / PostgreSQL 14+ / SQLite 3.35+ |
| Redis | Valkey / Redis 6+ (`phpredis` with TLS is required for DigitalOcean Managed Valkey) |

---

## Installation

### 1. Clone and install dependencies

```bash
git clone https://github.com/your-org/crucible.git
cd crucible

composer install
npm install && npm run build
```

### 2. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your database, mail, and Crucible-specific settings (see [Configuration](#configuration) below).

### 3. Run migrations

```bash
php artisan migrate
```

### 4. Create an admin user

```bash
php artisan tinker
# User::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => bcrypt('password')]);
```

### 5. Start the application

```bash
# Development
php artisan serve

# Production — use your web server (Nginx, Apache, Caddy)
```

---

## Configuration

### Crucible Settings

All Crucible-specific configuration lives in `config/crucible.php` and is controlled via environment variables:

#### Git Backend

```env
# Backend type: stub (testing), native (production), service (external)
CRUCIBLE_GIT_BACKEND=native

# Path where bare repositories are stored
CRUCIBLE_REPOS_PATH=/var/lib/crucible/repositories
```

#### Git LFS

```env
# Enable/disable LFS globally
CRUCIBLE_LFS_ENABLED=true

# Storage backend: local | s3
CRUCIBLE_LFS_BACKEND=local

# Disk name from config/filesystems.php
CRUCIBLE_LFS_DISK=local

# S3 disk name (when using s3 backend)
CRUCIBLE_LFS_S3_DISK=s3

# Threshold for chunked/TUS uploads (bytes, default: 100 MB)
CRUCIBLE_LFS_CHUNKED_THRESHOLD=104857600
```

#### SSH Access

```env
# System user for SSH git operations
CRUCIBLE_SSH_USER=git

# PHP binary path (for authorized_keys forced commands)
CRUCIBLE_SSH_PHP_BINARY=/usr/bin/php

# Artisan path override (auto-detected if empty)
CRUCIBLE_SSH_ARTISAN_PATH=
```

#### Forge Integration (optional)

```env
FORGE_ENABLED=false
FORGE_URL=https://forge.example.com
FORGE_CLIENT_ID=<passport-uuid>
FORGE_CLIENT_SECRET=<secret>
FORGE_REDIRECT_URI=https://crucible.example.com/auth/forge/callback
```

---

## Deployment Guide

### Production Server Setup

#### 1. System packages

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y \
    php8.3-fpm php8.3-cli php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-zip php8.3-bcmath php8.3-gd php8.3-mysql php8.3-dev \
    php-pear pkg-config libssl-dev git nginx mysql-server supervisor

# Install a recent phpredis build with TLS support. The distro package is
# often too old for managed Valkey / Redis services.
sudo pecl install redis
echo "extension=redis.so" | sudo tee /etc/php/8.3/mods-available/redis.ini
sudo phpenmod redis
php --ri redis

# Verify git version (2.38+ required for merge-tree --write-tree)
git --version
```

#### 2. Create the application user

```bash
sudo useradd -m -s /bin/bash crucible
sudo mkdir -p /var/www/crucible
sudo chown crucible:crucible /var/www/crucible
```

#### 3. Deploy the application

```bash
sudo -u crucible bash
cd /var/www/crucible

git clone https://github.com/your-org/crucible.git .
composer install --no-dev --optimize-autoloader
npm install && npm run build

cp .env.example .env
php artisan key:generate
```

#### 4. Configure directories and permissions

```bash
# Repository storage (can be a separate mount/volume for large teams)
sudo mkdir -p /var/lib/crucible/repositories
sudo chown crucible:crucible /var/lib/crucible/repositories

# Laravel storage and cache
chmod -R 775 storage bootstrap/cache
```

#### 5. Database setup

```bash
# Create database
mysql -u root -p -e "CREATE DATABASE crucible CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p -e "CREATE USER 'crucible'@'localhost' IDENTIFIED BY 'your-secure-password';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON crucible.* TO 'crucible'@'localhost';"

# Run migrations
php artisan migrate --force
```

#### 6. Environment configuration

Key `.env` settings for production:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://crucible.example.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=crucible
DB_USERNAME=crucible
DB_PASSWORD=your-secure-password

CACHE_STORE=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_CLIENT=phpredis
REDIS_SCHEME=tls
REDIS_HOST=your-cluster.db.ondigitalocean.com
REDIS_PORT=25061
REDIS_PASSWORD=your-digitalocean-valkey-password
REDIS_DB=0
REDIS_CACHE_DB=1

CRUCIBLE_GIT_BACKEND=native
CRUCIBLE_REPOS_PATH=/var/lib/crucible/repositories
CRUCIBLE_LFS_ENABLED=true
CRUCIBLE_LFS_BACKEND=local
```

For DigitalOcean Managed Valkey, leave `REDIS_HOST` as a plain hostname. Do not prefix it with `tls://`; Laravel reads the TLS setting from `REDIS_SCHEME=tls`. DigitalOcean uses publicly trusted certificates, so you should not need a custom CA bundle or disabled peer verification.

### Web Server (Nginx)

```nginx
server {
    listen 443 ssl http2;
    server_name crucible.example.com;

    root /var/www/crucible/public;
    index index.php;

    ssl_certificate     /etc/ssl/certs/crucible.pem;
    ssl_certificate_key /etc/ssl/private/crucible.key;

    # Increase body size for LFS uploads (adjust as needed)
    client_max_body_size 5G;

    # Increase timeouts for large file operations
    proxy_read_timeout 600;
    proxy_send_timeout 600;
    fastcgi_read_timeout 600;

    # Disable buffering for git and LFS streaming
    location ~ ^/(git|api/v1/.*/info/lfs|api/v1/.*/tus) {
        proxy_buffering off;
        proxy_request_buffering off;
        fastcgi_buffering off;

        try_files $uri /index.php?$query_string;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### Queue Worker (Supervisor)

Crucible uses queued jobs for webhook delivery, repository sync, and background tasks. Use Supervisor to keep the queue worker running:

```ini
; /etc/supervisor/conf.d/crucible-worker.conf
[program:crucible-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/crucible/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasstart=true
numprocs=2
user=crucible
redirect_stderr=true
stdout_logfile=/var/log/crucible/worker.log
stopwaitsecs=3600
```

```bash
sudo mkdir -p /var/log/crucible
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start crucible-worker:*
```

### Scheduled Tasks (Cron)

Add the Laravel scheduler to crontab:

```bash
# /etc/cron.d/crucible
* * * * * crucible cd /var/www/crucible && php artisan schedule:run >> /dev/null 2>&1
```

The scheduler runs these commands automatically:

| Command | Frequency | Purpose |
|---|---|---|
| `crucible:expire-locks` | Every 15 min | Remove expired file locks |
| `crucible:update-repo-sizes` | Hourly | Recalculate repo + LFS sizes |
| `crucible:clean-uploads` | Hourly | Remove expired upload sessions |
| Repository auto-sync | Every 15 min | Sync repositories with `auto_sync` enabled |

### SSH Access Setup

For git-over-SSH support:

```bash
# 1. Create a dedicated git user
sudo useradd -m -s /bin/bash git

# 2. Set up the authorized_keys command
php artisan crucible:ssh-setup

# 3. Ensure the git user can access repositories
sudo usermod -a -G crucible git
sudo chmod g+rwx /var/lib/crucible/repositories
```

### S3 / Object Storage for LFS

For production with large teams, configure S3-compatible storage for LFS objects:

```env
CRUCIBLE_LFS_BACKEND=s3
CRUCIBLE_LFS_S3_DISK=s3

AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=crucible-lfs
```

This keeps LFS objects on durable, scalable storage while git repositories remain on local disk. Any S3-compatible provider works (MinIO, DigitalOcean Spaces, Backblaze B2, Cloudflare R2).

### Organization Storage Quotas

Set per-organization storage limits to prevent runaway usage:

```bash
php artisan tinker
# Organization::where('slug', 'my-studio')->update(['storage_quota_gb' => 100]);
```

Quotas are enforced automatically during LFS uploads. The API returns HTTP 507 when quota is exceeded.

### Updating Crucible

```bash
cd /var/www/crucible

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader
npm install && npm run build

# Run new migrations
php artisan migrate --force

# Clear caches
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart workers
sudo supervisorctl restart crucible-worker:*
```

---

## API Overview

All API endpoints are under `/api/v1/` and require authentication via Sanctum tokens.

### Authentication

```bash
# Use a personal access token
curl https://crucible.example.com/api/v1/me \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Key Endpoints

| Method | Endpoint | Description |
|---|---|---|
| **Repositories** | | |
| `GET` | `/{org}/{repo}` | Repository details |
| `GET` | `/{org}/{repo}/branches` | List branches |
| **Pull Requests** | | |
| `GET` | `/{org}/{repo}/pull-requests` | List PRs |
| `POST` | `/{org}/{repo}/pull-requests` | Open a PR |
| `GET/POST` | `/{org}/{repo}/pull-requests/{n}/reviews` | PR reviews |
| **LFS** | | |
| `POST` | `/{org}/{repo}/info/lfs/objects/batch` | LFS batch API |
| `POST` | `/{org}/{repo}/tus` | TUS upload creation |
| **Branch Protection** | | |
| `GET/POST` | `/{org}/{repo}/branch-protection` | Protection rules |
| **CI / Webhooks** | | |
| `GET/POST` | `/{org}/{repo}/statuses/{sha}` | Commit statuses |
| `GET/POST` | `/{org}/{repo}/webhooks` | Webhook management |
| **Game Engine** | | |
| `POST` | `/{org}/{repo}/engine/detect` | Auto-detect engine |
| `POST` | `/{org}/{repo}/engine/apply` | Apply engine policies |
| `POST` | `/{org}/{repo}/generate-gitattributes` | Commit `.gitattributes` |
| **Workspace** | | |
| `GET/POST` | `/{org}/{repo}/sparse-profiles` | Sparse checkout profiles |
| `GET` | `/{org}/{repo}/workspace` | Workspace config |
| **File Locks** | | |
| `GET/POST` | `/{org}/{repo}/locks` | Lock management |

---

## Artisan Commands

```bash
# Repository management
php artisan crucible:repo-init           # Initialize a new repository
php artisan crucible:ssh-setup           # Configure SSH access
php artisan crucible:authorized-keys     # Rebuild authorized_keys

# Maintenance
php artisan crucible:expire-locks        # Remove expired file locks
php artisan crucible:update-repo-sizes   # Recalculate storage sizes
php artisan crucible:clean-uploads       # Remove expired upload sessions

# Tokens
php artisan crucible:app-token           # Create an API token
```

---

## Architecture

```
crucible/
├── app/
│   ├── Console/Commands/     # Artisan commands (locks, sizes, uploads, SSH)
│   ├── Contracts/            # Interfaces (LfsBackend, RepositoryDriver)
│   ├── Enums/                # EngineType, MergeStrategy, ReviewState, CommitStatusState
│   ├── Events/               # Push, PR, Review, FileLock events
│   ├── Http/Controllers/
│   │   ├── Api/V1/           # REST API controllers
│   │   └── ...               # Web controllers, LFS, TUS
│   ├── Jobs/                 # DeliverWebhook, InitializeRepo, SyncRepo
│   ├── Listeners/            # DispatchWebhooks event subscriber
│   ├── Models/               # Eloquent models (UUID primary keys)
│   ├── Services/             # Business logic layer
│   │   ├── NativeGitRepositoryService.php   # Git operations on bare repos
│   │   ├── LfsService.php                   # LFS batch/store/download
│   │   ├── PullRequestService.php           # PR merge strategies
│   │   ├── BranchProtectionService.php      # Protection + status checks
│   │   ├── GameEngineService.php            # Engine detection + policies
│   │   ├── UnrealAssetService.php           # .uasset binary parser
│   │   ├── WebhookService.php               # Webhook dispatch + HMAC
│   │   ├── StorageQuotaService.php          # Org quota enforcement
│   │   └── WorkspaceService.php             # workspace.json handling
│   └── Support/              # DiffParser, MimeDetector, GameEngineTemplates
├── config/crucible.php       # All Crucible configuration
├── database/migrations/      # Schema (UUID PKs, soft deletes)
├── routes/
│   ├── api.php               # REST API routes
│   └── web.php               # Web UI routes
├── resources/views/          # Blade templates
└── tests/Feature/            # Phase 3-8 test suites (100+ tests)
```

---

## Development

### Running tests

```bash
# Full suite
php artisan test

# Specific phase
php artisan test --filter=Phase3Test
php artisan test --filter=Phase8Test
```

### Local development setup

```bash
cp .env.example .env
php artisan key:generate

# Use SQLite for simplicity
# DB_CONNECTION=sqlite

php artisan migrate
php artisan serve
```

Set `CRUCIBLE_GIT_BACKEND=native` and `CRUCIBLE_REPOS_PATH` to a local directory to test git operations.

---

## License

Crucible SCM is proprietary software. All rights reserved.
