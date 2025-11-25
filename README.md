# Crucible

A Bitbucket-like code hosting platform built with Laravel 12, PHP 8.3.

## Requirements

- PHP 8.3+
- Composer
- Node.js & NPM
- SQLite (or other database)

## Features

- **Authentication**: Full authentication system powered by Laravel Jetstream with Livewire
- **Teams**: Built-in team management with roles and permissions
- **Organizations**: Create and manage organizations with member access control
- **Repositories**: Git repository management with visibility settings (Public, Private, Internal)
- **Admin Panel**: Filament v4 admin panel with role-based access control
- **Permissions**: Spatie Laravel Permission for granular access control
- **Settings**: Spatie Laravel Settings for application configuration

## Installation

1. Clone the repository:
```bash
git clone https://github.com/crimsonstrife/crucible.git
cd crucible
```

2. Install dependencies:
```bash
composer install
npm install
```

3. Set up environment:
```bash
cp .env.example .env
php artisan key:generate
```

4. Run migrations and seed:
```bash
php artisan migrate --seed
```

5. Build assets:
```bash
npm run build
```

6. Start the development server:
```bash
php artisan serve
```

## Default Users

After seeding, the following users are available:

| Email | Role | Password |
|-------|------|----------|
| admin@example.com | Admin | password |
| test@example.com | User | password |

## Admin Panel

The admin panel is accessible at `/admin` and requires the `access-admin-panel` permission.

Users with the `admin` role have full access to the admin panel.

## Forge OAuth

A placeholder for Forge OAuth integration is available at:
- Redirect: `/auth/forge/redirect`
- Callback: `/auth/forge/callback`

See: https://github.com/crimsonstrife/forge

## Testing

Run the test suite:
```bash
php artisan test
```

## Code Style

This project uses Laravel Pint for code formatting:
```bash
./vendor/bin/pint
```

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
